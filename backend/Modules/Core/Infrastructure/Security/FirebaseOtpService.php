<?php

declare(strict_types=1);

namespace Modules\Core\Infrastructure\Security;

use Illuminate\Support\Facades\Cache;

final class FirebaseOtpService
{
    private const OTP_TTL_SECONDS = 300; // 5 minutes

    private const MAX_RESENDS = 3;

    private const MAX_ATTEMPTS = 5;

    public function sendOtp(string $phone): array
    {
        $phone = preg_replace('/[^\d+]/', '', $phone);
        $resendKey = "otp_resend_limit:{$phone}";
        $resendCount = (int) Cache::get($resendKey, 0);

        if ($resendCount >= self::MAX_RESENDS) {
            throw new \DomainException('TOO_MANY_OTP_REQUESTS');
        }

        // Generate 6-digit OTP
        $otpCode = (string) random_int(100000, 999999);
        $otpKey = "otp_code:{$phone}";
        $attemptsKey = "otp_attempts:{$phone}";

        Cache::put($otpKey, $otpCode, self::OTP_TTL_SECONDS);
        Cache::put($attemptsKey, 0, self::OTP_TTL_SECONDS);
        Cache::put($resendKey, $resendCount + 1, 900); // 15-minute window

        return [
            'phone' => $phone,
            'expires_in' => self::OTP_TTL_SECONDS,
            'otp_code' => config('app.env') === 'testing' || config('services.otp.debug_mode') ? $otpCode : null,
        ];
    }

    public function verifyOtp(string $phone, string $code): bool
    {
        $phone = preg_replace('/[^\d+]/', '', $phone);
        $otpKey = "otp_code:{$phone}";
        $attemptsKey = "otp_attempts:{$phone}";

        $cachedCode = Cache::get($otpKey);
        if (! $cachedCode) {
            throw new \DomainException('EXPIRED_OTP_CODE');
        }

        $attempts = (int) Cache::get($attemptsKey, 0);
        if ($attempts >= self::MAX_ATTEMPTS) {
            Cache::forget($otpKey);
            Cache::forget($attemptsKey);
            throw new \DomainException('MAX_ATTEMPTS_EXCEEDED');
        }

        if (! hash_equals((string) $cachedCode, trim($code))) {
            $newAttempts = $attempts + 1;
            if ($newAttempts >= self::MAX_ATTEMPTS) {
                Cache::forget($otpKey);
                Cache::forget($attemptsKey);
                throw new \DomainException('MAX_ATTEMPTS_EXCEEDED');
            }
            Cache::put($attemptsKey, $newAttempts, self::OTP_TTL_SECONDS);
            throw new \DomainException('INVALID_OTP_CODE');
        }

        // Burn OTP upon successful verification
        Cache::forget($otpKey);
        Cache::forget($attemptsKey);

        return true;
    }
}
