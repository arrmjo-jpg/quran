<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

final class RateLimitingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        RateLimiter::clear('auth-login:127.0.0.1');
    }

    public function test_login_rate_limiting_throttles_after_max_attempts(): void
    {
        // 5 allowed login attempts
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/admin/auth/login', [
                'email' => 'admin@quran.test',
                'password' => 'WrongPass',
            ])->assertStatus(401);
        }

        // 6th attempt MUST return HTTP 429 Too Many Requests
        $response = $this->postJson('/api/v1/admin/auth/login', [
            'email' => 'admin@quran.test',
            'password' => 'WrongPass',
        ]);

        $response->assertStatus(429);
    }

    public function test_general_login_rate_limiting_throttles_after_max_attempts(): void
    {
        // The general /api/v1/auth/login route had no throttle at all —
        // unlimited brute-force was possible against it. Same limiter,
        // same 5/min budget as the admin route.
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/login', [
                'email' => 'nobody@quran.test',
                'password' => 'WrongPass',
            ])->assertStatus(401);
        }

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'nobody@quran.test',
            'password' => 'WrongPass',
        ]);

        $response->assertStatus(429);
    }
}
