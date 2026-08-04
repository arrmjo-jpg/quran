<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Infrastructure\Database\Models\UserModel;

uses(RefreshDatabase::class)->group('judges_gate', 'api');

test('Judges API Readiness Gate: admin judge creation, specialization filtering, and judge profile endpoint', function (): void {
    $admin = UserModel::query()->create([
        'id' => fake()->uuid(),
        'email' => 'admin-judge@quranplatform.com',
        'name' => 'Judge Admin',
        'type' => 'admin',
        'password_hash' => password_hash('AdminPass123!', PASSWORD_BCRYPT),
        'is_active' => true,
    ]);

    $judgeUser = UserModel::query()->create([
        'id' => fake()->uuid(),
        'email' => 'judge1@quranplatform.com',
        'name' => 'Sheikh Abdullah Al-Ghamdi',
        'type' => 'user',
        'password_hash' => password_hash('JudgePass123!', PASSWORD_BCRYPT),
        'is_active' => true,
    ]);

    // 1. Admin Create Judge Profile
    $createResponse = $this->actingAs($admin)->postJson('/api/v1/admin/judges', [
        'user_id' => $judgeUser->id,
        'full_name' => 'Sheikh Abdullah Al-Ghamdi',
        'specialization' => 'tajweed',
        'title' => 'Senior Tajweed Scholar',
        'bio' => 'Certified Qari in Hafs reading with 20 years experience.',
    ]);

    $createResponse->assertStatus(201)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.specialization', 'tajweed');

    // 2. Admin List Judges filtered by specialization
    $listResponse = $this->actingAs($admin)->getJson('/api/v1/admin/judges?specialization=tajweed');
    $listResponse->assertStatus(200)
        ->assertJsonPath('data.0.full_name', 'Sheikh Abdullah Al-Ghamdi');

    // 3. Judge GET /judge/profile
    $profileResponse = $this->actingAs($judgeUser)->getJson('/api/v1/judge/profile');
    $profileResponse->assertStatus(200)
        ->assertJsonPath('data.user_id', $judgeUser->id);
});
