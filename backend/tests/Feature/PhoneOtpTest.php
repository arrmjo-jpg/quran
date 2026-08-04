<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

final class PhoneOtpTest extends TestCase
{
    use RefreshDatabase;

    private string $testPhone = '+962791234567';

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_phone_otp_can_be_sent_successfully(): void
    {
        $response = $this->postJson('/api/v1/auth/phone/send-otp', [
            'phone' => $this->testPhone,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['phone', 'expires_in', 'otp_code']]);

        $otpCode = $response->json('data.otp_code');
        $this->assertEquals(6, strlen($otpCode));
    }

    public function test_phone_otp_valid_verification_succeeds(): void
    {
        $sendResponse = $this->postJson('/api/v1/auth/phone/send-otp', [
            'phone' => $this->testPhone,
        ]);

        $otpCode = $sendResponse->json('data.otp_code');

        $verifyResponse = $this->postJson('/api/v1/auth/phone/verify-otp', [
            'phone' => $this->testPhone,
            'code' => $otpCode,
        ]);

        $verifyResponse->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['token', 'user']]);
    }

    public function test_phone_otp_invalid_code_fails(): void
    {
        $this->postJson('/api/v1/auth/phone/send-otp', [
            'phone' => $this->testPhone,
        ]);

        $verifyResponse = $this->postJson('/api/v1/auth/phone/verify-otp', [
            'phone' => $this->testPhone,
            'code' => '000000',
        ]);

        $verifyResponse->assertStatus(422)
            ->assertJsonPath('error.code', 'INVALID_OTP_CODE');
    }

    public function test_phone_otp_expired_code_fails(): void
    {
        $verifyResponse = $this->postJson('/api/v1/auth/phone/verify-otp', [
            'phone' => '+962799999999',
            'code' => '123456',
        ]);

        $verifyResponse->assertStatus(422)
            ->assertJsonPath('error.code', 'EXPIRED_OTP_CODE');
    }

    public function test_phone_otp_resend_rate_limit_exceeded_fails(): void
    {
        // 3 successful sends
        for ($i = 0; $i < 3; $i++) {
            $this->postJson('/api/v1/auth/phone/send-otp', ['phone' => $this->testPhone])
                ->assertStatus(200);
        }

        // 4th send must be throttled with 429 TOO_MANY_OTP_REQUESTS
        $response = $this->postJson('/api/v1/auth/phone/send-otp', ['phone' => $this->testPhone]);
        $response->assertStatus(429)
            ->assertJsonPath('error.code', 'TOO_MANY_OTP_REQUESTS');
    }

    public function test_phone_otp_max_verification_attempts_exceeded_fails(): void
    {
        $this->postJson('/api/v1/auth/phone/send-otp', ['phone' => $this->testPhone]);

        // 4 failed verification attempts
        for ($i = 0; $i < 4; $i++) {
            $this->postJson('/api/v1/auth/phone/verify-otp', [
                'phone' => $this->testPhone,
                'code' => '000000',
            ])->assertStatus(422);
        }

        // 5th failed verification attempt exceeds limit and burns OTP (429 MAX_ATTEMPTS_EXCEEDED)
        $response = $this->postJson('/api/v1/auth/phone/verify-otp', [
            'phone' => $this->testPhone,
            'code' => '000000',
        ]);

        $response->assertStatus(429)
            ->assertJsonPath('error.code', 'MAX_ATTEMPTS_EXCEEDED');
    }
}
