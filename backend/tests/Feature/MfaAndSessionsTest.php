<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Modules\Core\Infrastructure\Database\Models\UserModel;
use Modules\Core\Infrastructure\Security\TotpService;
use Tests\TestCase;

final class MfaAndSessionsTest extends TestCase
{
    use RefreshDatabase;

    private UserModel $adminUser;

    private UserModel $otherUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminUser = UserModel::query()->create([
            'id' => '00000000-0000-0000-0000-000000000099',
            'email' => 'mfa-admin@quran.test',
            'name' => 'MFA Admin',
            'type' => 'admin',
            'password_hash' => password_hash('Pass123!', PASSWORD_BCRYPT),
            'is_active' => true,
        ]);

        $this->otherUser = UserModel::query()->create([
            'id' => '00000000-0000-0000-0000-000000000088',
            'email' => 'other-admin@quran.test',
            'name' => 'Other Admin',
            'type' => 'admin',
            'password_hash' => password_hash('Pass123!', PASSWORD_BCRYPT),
            'is_active' => true,
        ]);
    }

    // ── Positive Tests ─────────────────────────────────────────────────────────

    public function test_admin_can_setup_mfa_and_receive_secret(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/v1/admin/auth/mfa/setup');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'data' => ['secret', 'qr_code_url'],
            ]);
    }

    public function test_admin_can_verify_mfa_and_encrypt_secret_at_rest(): void
    {
        $totpService = new TotpService;

        // The secret is now issued and stored server-side by mfaSetup() —
        // mfaVerify() no longer trusts a client-supplied secret, so the
        // test must go through the real setup step to get one.
        $setupResponse = $this->actingAs($this->adminUser)
            ->postJson('/api/v1/admin/auth/mfa/setup');
        $secret = $setupResponse->json('data.secret');

        $reflection = new \ReflectionClass($totpService);
        $method = $reflection->getMethod('calculateTotp');
        $method->setAccessible(true);
        $validCode = $method->invoke($totpService, $secret, (int) floor(time() / 30));

        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/v1/admin/auth/mfa/verify', [
                'code' => $validCode,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.mfa_enabled', true)
            ->assertJsonCount(8, 'data.recovery_codes');

        $this->adminUser->refresh();
        $this->assertTrue($this->adminUser->mfa_enabled);
        $this->assertNotEquals($secret, $this->adminUser->mfa_secret);
        $this->assertEquals($secret, Crypt::decryptString($this->adminUser->mfa_secret));
    }

    public function test_mfa_enabled_user_requires_challenge_flow_on_login(): void
    {
        $totpService = new TotpService;
        $secret = $totpService->generateSecret();

        $this->adminUser->update([
            'mfa_enabled' => true,
            'mfa_secret' => Crypt::encryptString($secret),
        ]);

        $loginResponse = $this->postJson('/api/v1/admin/auth/login', [
            'email' => 'mfa-admin@quran.test',
            'password' => 'Pass123!',
        ]);

        $loginResponse->assertStatus(200)
            ->assertJsonPath('data.mfa_required', true)
            ->assertJsonStructure(['data' => ['challenge_token']]);

        $challengeToken = $loginResponse->json('data.challenge_token');

        $reflection = new \ReflectionClass($totpService);
        $method = $reflection->getMethod('calculateTotp');
        $method->setAccessible(true);
        $validCode = $method->invoke($totpService, $secret, (int) floor(time() / 30));

        $challengeResponse = $this->withHeader('Authorization', "Bearer {$challengeToken}")
            ->postJson('/api/v1/admin/auth/login/mfa-challenge', [
                'code' => $validCode,
            ]);

        $challengeResponse->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['token', 'user']]);
    }

    public function test_admin_can_use_recovery_code(): void
    {
        $totpService = new TotpService;
        $recoveryCodes = $totpService->generateRecoveryCodes(8);
        $hashedCodes = array_map(fn ($c) => password_hash($c, PASSWORD_BCRYPT), $recoveryCodes);

        $this->adminUser->update([
            'mfa_enabled' => true,
            'mfa_recovery_codes' => $hashedCodes,
        ]);

        $codeToUse = $recoveryCodes[0];

        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/v1/admin/auth/mfa/recovery', [
                'code' => $codeToUse,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.remaining_recovery_codes', 7);
    }

    public function test_admin_can_list_and_revoke_active_sessions(): void
    {
        $token1 = $this->adminUser->createToken('session_1')->plainTextToken;
        $token2 = $this->adminUser->createToken('session_2')->plainTextToken;

        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/v1/admin/auth/sessions');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'data');

        $otherTokenModel = $this->adminUser->tokens()->where('name', 'session_1')->first();
        $otherTokenId = (string) $otherTokenModel->id;

        $revokeResponse = $this->actingAs($this->adminUser)
            ->deleteJson("/api/v1/admin/auth/sessions/{$otherTokenId}");

        $revokeResponse->assertStatus(200)
            ->assertJsonPath('success', true);

        Auth::forgetGuards();

        $unauthorizedResponse = $this->withHeader('Authorization', "Bearer {$token1}")
            ->getJson('/api/v1/admin/auth/me');

        $unauthorizedResponse->assertStatus(401);
    }

    // ── Authorization & Guard Tests (Guests Restricted) ──────────────────────

    public function test_unauthenticated_guests_cannot_access_mfa_or_session_endpoints(): void
    {
        $endpoints = [
            ['method' => 'postJson', 'uri' => '/api/v1/admin/auth/mfa/setup'],
            ['method' => 'postJson', 'uri' => '/api/v1/admin/auth/mfa/verify'],
            ['method' => 'postJson', 'uri' => '/api/v1/admin/auth/mfa/recovery'],
            ['method' => 'getJson',  'uri' => '/api/v1/admin/auth/sessions'],
            ['method' => 'deleteJson', 'uri' => '/api/v1/admin/auth/sessions/9999'],
            ['method' => 'deleteJson', 'uri' => '/api/v1/admin/auth/sessions/other'],
        ];

        foreach ($endpoints as $e) {
            $method = $e['method'];
            $response = $this->$method($e['uri']);
            $response->assertStatus(401);
        }
    }

    // ── Negative Security Tests ───────────────────────────────────────────────

    public function test_mfa_negative_cases_reject_invalid_code_length_and_malformed_input(): void
    {
        // 1. Invalid TOTP code length (less than 6 digits) -> 422 Unprocessable
        $this->actingAs($this->adminUser)
            ->postJson('/api/v1/admin/auth/mfa/verify', ['code' => '123'])
            ->assertStatus(422);

        // 2. Alphabetic string code -> 422 Unprocessable
        $this->actingAs($this->adminUser)
            ->postJson('/api/v1/admin/auth/mfa/verify', ['code' => 'abcdef'])
            ->assertStatus(422);

        // 3. No pending setup at all -> 422 MFA_SETUP_EXPIRED
        $this->actingAs($this->adminUser)
            ->postJson('/api/v1/admin/auth/mfa/verify', ['code' => '000000'])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'MFA_SETUP_EXPIRED');

        // 4. A pending setup exists, but the submitted code doesn't match it -> 422 INVALID_MFA_CODE
        $this->actingAs($this->adminUser)
            ->postJson('/api/v1/admin/auth/mfa/setup');

        $this->actingAs($this->adminUser)
            ->postJson('/api/v1/admin/auth/mfa/verify', ['code' => '000000'])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'INVALID_MFA_CODE');
    }

    public function test_single_use_recovery_code_reuse_is_denied(): void
    {
        $totpService = new TotpService;
        $recoveryCodes = $totpService->generateRecoveryCodes(8);
        $hashedCodes = array_map(fn ($c) => password_hash($c, PASSWORD_BCRYPT), $recoveryCodes);

        $this->adminUser->update([
            'mfa_enabled' => true,
            'mfa_recovery_codes' => $hashedCodes,
        ]);

        $codeToUse = $recoveryCodes[0];

        // First use: PASS (200 OK)
        $this->actingAs($this->adminUser)
            ->postJson('/api/v1/admin/auth/mfa/recovery', ['code' => $codeToUse])
            ->assertStatus(200);

        // Reuse attempt: REJECTED (422 INVALID_RECOVERY_CODE)
        $this->actingAs($this->adminUser)
            ->postJson('/api/v1/admin/auth/mfa/recovery', ['code' => $codeToUse])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'INVALID_RECOVERY_CODE');
    }

    public function test_user_cannot_revoke_session_owned_by_another_user(): void
    {
        $otherToken = $this->otherUser->createToken('other_session')->plainTextToken;
        $otherTokenModel = $this->otherUser->tokens()->first();
        $otherTokenId = (string) $otherTokenModel->id;

        // AdminUser attempts to delete OtherUser's token ID
        $response = $this->actingAs($this->adminUser)
            ->deleteJson("/api/v1/admin/auth/sessions/{$otherTokenId}");

        $response->assertStatus(200);

        // Verify otherUser's token was NOT deleted
        $this->assertEquals(1, $this->otherUser->tokens()->count());
    }
}
