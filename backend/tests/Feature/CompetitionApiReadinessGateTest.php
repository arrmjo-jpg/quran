<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Infrastructure\Database\Models\UserModel;

uses(RefreshDatabase::class)->group('competition_gate', 'api');

test('Competition API Readiness Gate: complete season lifecycle state machine and dry-run simulation endpoints', function (): void {
    $admin = UserModel::query()->create([
        'id' => fake()->uuid(),
        'email' => 'admin-comp@quranplatform.com',
        'name' => 'Competition Admin',
        'type' => 'admin',
        'password_hash' => password_hash('AdminPass123!', PASSWORD_BCRYPT),
        'is_active' => true,
    ]);

    // 1. Create Season
    $createResponse = $this->actingAs($admin)->postJson('/api/v1/admin/seasons', [
        'slug' => 'season-2026',
        'year' => 2026,
        'registration_start' => '2026-08-01 00:00:00',
        'registration_end' => '2026-08-15 23:59:59',
        'start_date' => '2026-08-16 00:00:00',
        'end_date' => '2026-09-30 23:59:59',
        'title_ar' => 'موسم عام 2026',
        'title_en' => 'Season 2026',
    ]);

    $createResponse->assertStatus(201)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.status', 'draft');

    $seasonId = $createResponse->json('data.id');

    // 2. Open Registration
    $openResponse = $this->actingAs($admin)->postJson("/api/v1/admin/seasons/{$seasonId}/open-registration");
    $openResponse->assertStatus(200)
        ->assertJsonPath('data.status', 'registration_open')
        ->assertJsonPath('data.is_active', true);

    // 3. Close Registration
    $closeResponse = $this->actingAs($admin)->postJson("/api/v1/admin/seasons/{$seasonId}/close-registration");
    $closeResponse->assertStatus(200)
        ->assertJsonPath('data.status', 'registration_closed');

    // 4. Simulate Ranking Dry Run (Zero DB persistence)
    $simulateResponse = $this->actingAs($admin)->postJson('/api/v1/admin/stages/stage-101/simulate-ranking', [
        'scores' => [
            [
                'application_id' => 'app-1',
                'total_score' => 95.0,
                'tajweed_score' => 30.0,
                'memorization_score' => 28.0,
                'voice_score' => 18.0,
            ],
            [
                'application_id' => 'app-2',
                'total_score' => 95.0,
                'tajweed_score' => 28.0,
                'memorization_score' => 30.0,
                'voice_score' => 19.0,
            ],
        ],
    ]);

    $simulateResponse->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.simulation.0.application_id', 'app-1');
});
