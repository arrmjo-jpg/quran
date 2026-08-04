<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class)->group('core_gate', 'e2e');

test('Core API Readiness Gate: complete end-to-end user authentication & profile journey', function (): void {
    // 1. Register User
    $regResponse = $this->postJson('/api/v1/auth/register', [
        'name' => 'Tariq Al-Hashemi',
        'email' => 'tariq@example.com',
        'password' => 'SecurePass123!',
        'locale' => 'ar',
    ]);

    $regResponse->assertStatus(201)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.email', 'tariq@example.com');

    // 2. Login User
    $loginResponse = $this->postJson('/api/v1/auth/login', [
        'email' => 'tariq@example.com',
        'password' => 'SecurePass123!',
    ]);

    $loginResponse->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonStructure(['data' => ['token', 'user']]);

    $token = $loginResponse->json('data.token');

    // 3. Fetch Profile GET /me with Bearer Token
    $meResponse = $this->withToken($token)->getJson('/api/v1/me');
    $meResponse->assertStatus(200)
        ->assertJsonPath('data.email', 'tariq@example.com');

    // 4. Update Profile PATCH /me
    $updateResponse = $this->withToken($token)->patchJson('/api/v1/me', [
        'name' => 'Tariq Al-Hashemi (Updated)',
    ]);
    $updateResponse->assertStatus(200)
        ->assertJsonPath('data.name', 'Tariq Al-Hashemi (Updated)');

    // 5. Logout
    $logoutResponse = $this->withToken($token)->postJson('/api/v1/auth/logout');
    $logoutResponse->assertStatus(200);
});

test('Core API Readiness Gate: unauthenticated request to /me is rejected with 401', function (): void {
    $response = $this->getJson('/api/v1/me');
    $response->assertStatus(401);
});

test('Core API Readiness Gate: public languages and settings endpoints return valid responses', function (): void {
    $langResponse = $this->getJson('/api/v1/languages');
    $langResponse->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonStructure(['data' => [['code', 'name', 'direction', 'is_default']]]);

    $settingsResponse = $this->getJson('/api/v1/settings/public');
    $settingsResponse->assertStatus(200)
        ->assertJsonPath('data.max_video_size_mb', 500);
});
