<?php

declare(strict_types=1);

namespace Modules\Core\Presentation\HTTP\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Laravel\Sanctum\PersonalAccessToken;
use Modules\Core\Application\Commands\CreateUserCommand;
use Modules\Core\Application\UseCases\CreateUserUseCase;
use Modules\Core\Domain\ValueObjects\Email;
use Modules\Core\Domain\ValueObjects\Locale;
use Modules\Core\Domain\ValueObjects\PasswordHash;
use Modules\Core\Domain\ValueObjects\UserId;
use Modules\Core\Domain\ValueObjects\UserType;
use Modules\Core\Infrastructure\Database\Models\UserModel;
use Modules\Core\Infrastructure\Security\DeviceTrustService;
use Modules\Core\Infrastructure\Security\FirebaseOtpService;
use Modules\Core\Infrastructure\Security\TotpService;
use Modules\Core\Presentation\HTTP\Requests\LoginRequest;
use Modules\Core\Presentation\HTTP\Requests\RegisterUserRequest;
use Modules\Core\Presentation\HTTP\Requests\UpdateProfileRequest;
use Modules\Core\Presentation\HTTP\Resources\UserResource;

final class AuthController extends Controller
{
    public function register(RegisterUserRequest $request, CreateUserUseCase $useCase): JsonResponse
    {
        $command = new CreateUserCommand(
            id: UserId::generate(),
            email: new Email($request->validated('email')),
            name: $request->validated('name'),
            type: UserType::contestant(),
            passwordHash: PasswordHash::fromPlainPassword($request->validated('password')),
            preferredLocale: new Locale($request->validated('locale', 'ar'))
        );

        $user = $useCase->execute($command);

        return response()->json([
            'success' => true,
            'message' => __('Registration successful.'),
            'data' => new UserResource($user),
        ], 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        /** @var UserModel|null $user */
        $user = UserModel::query()->where('email', $request->validated('email'))->first();

        if (! $user || ! password_verify($request->validated('password'), $user->password_hash)) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'INVALID_CREDENTIALS',
                    'message' => __('Invalid login credentials.'),
                    'correlation_id' => $request->header('X-Correlation-ID'),
                ],
            ], 401);
        }

        if (! $user->is_active) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'ACCOUNT_DEACTIVATED',
                    'message' => __('This account has been deactivated.'),
                    'correlation_id' => $request->header('X-Correlation-ID'),
                ],
            ], 403);
        }

        // Enforcement: If MFA is enabled, issue challenge token instead of full auth token
        if ($user->mfa_enabled) {
            $challengeToken = $user->createToken('mfa_challenge', ['mfa-challenge'])->plainTextToken;

            return response()->json([
                'success' => true,
                'message' => __('MFA verification required.'),
                'data' => [
                    'mfa_required' => true,
                    'challenge_token' => $challengeToken,
                ],
            ]);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => __('Login successful.'),
            'data' => [
                'token' => $token,
                'user' => new UserResource($user),
            ],
        ]);
    }

    public function loginMfaChallenge(Request $request, TotpService $totpService): JsonResponse
    {
        $request->validate([
            'code' => ['required', 'string'],
        ]);

        /** @var UserModel $user */
        $user = $request->user();
        $code = trim($request->input('code'));

        $decryptedSecret = $user->mfa_secret ? Crypt::decryptString($user->mfa_secret) : '';
        $isValidTotp = $decryptedSecret && $totpService->verifyCode($decryptedSecret, $code);

        $isValidRecovery = false;
        if (! $isValidTotp && strlen($code) === 8) {
            $codeUpper = strtoupper($code);
            $savedCodes = $user->mfa_recovery_codes ?? [];
            foreach ($savedCodes as $index => $hashedCode) {
                if (password_verify($codeUpper, $hashedCode)) {
                    $isValidRecovery = true;
                    unset($savedCodes[$index]);
                    $user->update(['mfa_recovery_codes' => array_values($savedCodes)]);
                    break;
                }
            }
        }

        if (! $isValidTotp && ! $isValidRecovery) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'INVALID_MFA_CODE', 'message' => __('Invalid 6-digit TOTP or recovery code.')],
            ], 422);
        }

        // Revoke challenge token and issue final auth token
        $user->currentAccessToken()?->delete();
        $finalToken = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => __('MFA verification successful.'),
            'data' => [
                'token' => $finalToken,
                'user' => new UserResource($user),
            ],
        ]);
    }

    public function mfaSetup(Request $request, TotpService $totpService): JsonResponse
    {
        $user = $request->user();
        $secret = $totpService->generateSecret();

        // Stored server-side and consulted by mfaVerify() instead of
        // trusting a client-supplied "secret" field — otherwise anything
        // able to influence the verify request body could register its
        // own attacker-controlled secret as the account's real MFA seed.
        Cache::put(self::mfaPendingSecretKey((string) $user->id), $secret, now()->addMinutes(10));

        $qrCodeUrl = sprintf(
            'otpauth://totp/QuranPlatform:%s?secret=%s&issuer=QuranPlatform',
            urlencode($user->email),
            $secret
        );

        return response()->json([
            'success' => true,
            'data' => [
                'secret' => $secret,
                'qr_code_url' => $qrCodeUrl,
            ],
        ]);
    }

    public function mfaVerify(Request $request, TotpService $totpService): JsonResponse
    {
        $request->validate([
            'code' => ['required', 'string', 'size:6'],
        ]);

        $user = $request->user();
        $code = $request->input('code');

        $secret = Cache::get(self::mfaPendingSecretKey((string) $user->id));

        if (! $secret) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'MFA_SETUP_EXPIRED', 'message' => __('MFA setup session expired. Please start again.')],
            ], 422);
        }

        if (! $totpService->verifyCode($secret, $code)) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'INVALID_MFA_CODE', 'message' => __('Invalid 6-digit MFA code.')],
            ], 422);
        }

        Cache::forget(self::mfaPendingSecretKey((string) $user->id));

        $recoveryCodes = $totpService->generateRecoveryCodes(8);
        $hashedRecoveryCodes = array_map(fn ($c) => password_hash($c, PASSWORD_BCRYPT), $recoveryCodes);

        // Security Hardening: Encrypt MFA secret at rest
        $user->update([
            'mfa_enabled' => true,
            'mfa_secret' => Crypt::encryptString($secret),
            'mfa_recovery_codes' => $hashedRecoveryCodes,
        ]);

        return response()->json([
            'success' => true,
            'message' => __('MFA enabled successfully.'),
            'data' => [
                'mfa_enabled' => true,
                'recovery_codes' => $recoveryCodes,
            ],
        ]);
    }

    public function mfaRecovery(Request $request): JsonResponse
    {
        $request->validate([
            'code' => ['required', 'string', 'size:8'],
        ]);

        /** @var UserModel $user */
        $user = $request->user();
        $code = strtoupper(trim($request->input('code')));
        $savedCodes = $user->mfa_recovery_codes ?? [];

        $matchedIndex = null;
        foreach ($savedCodes as $index => $hashedCode) {
            if (password_verify($code, $hashedCode)) {
                $matchedIndex = $index;
                break;
            }
        }

        if ($matchedIndex === null) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'INVALID_RECOVERY_CODE', 'message' => __('Invalid or already used recovery code.')],
            ], 422);
        }

        // Burn the used code
        unset($savedCodes[$matchedIndex]);
        $user->update(['mfa_recovery_codes' => array_values($savedCodes)]);

        return response()->json([
            'success' => true,
            'message' => __('Recovery code verified.'),
            'data' => ['remaining_recovery_codes' => count($savedCodes)],
        ]);
    }

    public function listSessions(Request $request): JsonResponse
    {
        $user = $request->user();
        $currentToken = $user->currentAccessToken();
        $currentTokenId = $currentToken instanceof PersonalAccessToken ? $currentToken->id : null;

        $tokens = $user->tokens()->latest()->get()->map(function ($token) use ($currentTokenId) {
            return [
                'id' => (string) $token->id,
                'name' => $token->name,
                'is_current' => $token->id === $currentTokenId,
                'last_used_at' => $token->last_used_at,
                'created_at' => $token->created_at,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $tokens,
        ]);
    }

    public function revokeSession(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $user->tokens()->where('id', $id)->delete();

        return response()->json([
            'success' => true,
            'message' => __('Session revoked successfully.'),
        ]);
    }

    public function revokeOtherSessions(Request $request): JsonResponse
    {
        $user = $request->user();
        $currentToken = $user->currentAccessToken();
        $currentTokenId = $currentToken instanceof PersonalAccessToken ? $currentToken->id : null;

        if ($currentTokenId) {
            $user->tokens()->where('id', '!=', $currentTokenId)->delete();
        } else {
            $user->tokens()->delete();
        }

        return response()->json([
            'success' => true,
            'message' => __('All other sessions revoked successfully.'),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => __('User profile retrieved.'),
            'data' => new UserResource($request->user()),
        ]);
    }

    public function updateProfile(UpdateProfileRequest $request): JsonResponse
    {
        /** @var UserModel $user */
        $user = $request->user();
        $user->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => __('Profile updated successfully.'),
            'data' => new UserResource($user),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        return response()->json([
            'success' => true,
            'message' => __('Successfully logged out.'),
        ]);
    }

    public function sendPhoneOtp(Request $request, FirebaseOtpService $otpService): JsonResponse
    {
        $request->validate([
            'phone' => ['required', 'string', 'min:8', 'max:20'],
        ]);

        try {
            $data = $otpService->sendOtp($request->input('phone'));

            return response()->json([
                'success' => true,
                'message' => __('OTP sent successfully.'),
                'data' => $data,
            ]);
        } catch (\DomainException $e) {
            $code = $e->getMessage();
            $status = $code === 'TOO_MANY_OTP_REQUESTS' ? 429 : 422;

            return response()->json([
                'success' => false,
                'error' => ['code' => $code, 'message' => __('OTP rate limit exceeded or invalid.')],
            ], $status);
        }
    }

    public function verifyPhoneOtp(Request $request, FirebaseOtpService $otpService): JsonResponse
    {
        $request->validate([
            'phone' => ['required', 'string', 'min:8', 'max:20'],
            'code' => ['required', 'string', 'size:6'],
        ]);

        try {
            $otpService->verifyOtp($request->input('phone'), $request->input('code'));

            $phone = preg_replace('/[^\d+]/', '', $request->input('phone'));
            $user = UserModel::query()->firstOrCreate(
                ['email' => "phone_{$phone}@quran.test"],
                [
                    'id' => UserId::generate()->value,
                    'name' => "User {$phone}",
                    'type' => 'contestant',
                    'is_active' => true,
                ]
            );

            $token = $user->createToken('phone_auth_token')->plainTextToken;

            return response()->json([
                'success' => true,
                'message' => __('Phone OTP verified successfully.'),
                'data' => [
                    'token' => $token,
                    'user' => new UserResource($user),
                ],
            ]);
        } catch (\DomainException $e) {
            $code = $e->getMessage();
            $status = in_array($code, ['MAX_ATTEMPTS_EXCEEDED']) ? 429 : 422;

            return response()->json([
                'success' => false,
                'error' => ['code' => $code, 'message' => __('OTP verification failed.')],
            ], $status);
        }
    }

    public function trustDevice(Request $request, DeviceTrustService $trustService): JsonResponse
    {
        $user = $request->user();
        $device = $trustService->trustDevice(
            $user,
            $request->ip() ?? '127.0.0.1',
            $request->userAgent() ?? 'Unknown',
            $request->header('X-Device-ID')
        );

        return response()->json([
            'success' => true,
            'message' => __('Device registered as trusted for 30 days.'),
            'data' => $device,
        ]);
    }

    public function listTrustedDevices(Request $request, DeviceTrustService $trustService): JsonResponse
    {
        $user = $request->user();
        $devices = $trustService->listDevices($user);

        return response()->json([
            'success' => true,
            'data' => $devices,
        ]);
    }

    public function revokeTrustedDevice(Request $request, string $id, DeviceTrustService $trustService): JsonResponse
    {
        $user = $request->user();
        $trustService->revokeDevice($user, $id);

        return response()->json([
            'success' => true,
            'message' => __('Device trust revoked successfully.'),
        ]);
    }

    private static function mfaPendingSecretKey(string $userId): string
    {
        return "mfa_pending_secret:{$userId}";
    }
}
