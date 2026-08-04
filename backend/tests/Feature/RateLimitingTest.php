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
}
