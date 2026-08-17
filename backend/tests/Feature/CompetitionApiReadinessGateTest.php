<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Competition\Domain\Repositories\SeasonRepositoryContract;
use Modules\Competition\Domain\ValueObjects\SeasonTranslation;
use Modules\Competition\Infrastructure\Database\Models\JudgeScoreSystemModel;
use Modules\Competition\Infrastructure\Database\Models\ParticipationTypeModel;
use Modules\Competition\Infrastructure\Database\Models\StageModel;
use Modules\Competition\Infrastructure\Database\Models\TajweedLevelModel;
use Modules\Core\Infrastructure\Database\Models\UserModel;
use Modules\Countries\Infrastructure\Database\Models\CountryModel;

uses(RefreshDatabase::class)->group('competition_gate', 'api');

/**
 * OpenSeasonRegistrationUseCase requires a season's rules to be fully
 * resolved before it may open registration. There is no admin API yet
 * to set any of this (a known, separate gap — see Step 9's verification
 * report), so this configures it directly through the domain/repository,
 * same as SeasonsTest::configureSeasonRules().
 */
function cargt_configureSeasonRules(string $seasonId): void
{
    $repository = app(SeasonRepositoryContract::class);
    $season = $repository->findOrFail($seasonId);

    $participationType = ParticipationTypeModel::query()->create(['id' => fake()->uuid(), 'code' => 'mixed-'.Str::random(6), 'display_order' => 1, 'is_active' => true]);
    $tajweedLevel = TajweedLevelModel::query()->create(['id' => fake()->uuid(), 'code' => 'advanced-'.Str::random(6), 'display_order' => 1, 'is_active' => true]);
    $country = CountryModel::query()->create(['id' => fake()->uuid(), 'iso_code' => Str::upper(Str::random(2)), 'iso3_code' => Str::upper(Str::random(3)), 'phone_code' => '+1', 'is_active' => true]);

    $season->setAgeRange(10, 18);
    $season->setParticipationType($participationType->id);
    $season->setTajweedLevel($tajweedLevel->id);
    $season->setTranslation(new SeasonTranslation('ar', 'موسم', 'موسم القرآن'));
    $season->setTranslation(new SeasonTranslation('en', 'Season', 'Quran Season'));
    $season->setTranslation(new SeasonTranslation('es', 'Temporada', 'Temporada del Corán'));
    $repository->save($season);

    DB::table('season_countries')->insert(['season_id' => $seasonId, 'country_id' => $country->id]);

    $stage = StageModel::query()->create([
        'id' => fake()->uuid(), 'season_id' => $seasonId, 'stage_number' => 1, 'type' => 'final',
        'start_date' => now(), 'end_date' => now()->addDay(), 'status' => 'pending',
    ]);

    $scoreSystem = JudgeScoreSystemModel::query()->create(['id' => fake()->uuid(), 'code' => 'out_of_100-'.Str::random(6), 'max_score' => 100, 'display_order' => 1, 'is_active' => true]);

    DB::table('season_stage_rules')->insert([
        'id' => fake()->uuid(), 'season_id' => $seasonId, 'stage_id' => $stage->id,
        'judge_score_system_id' => $scoreSystem->id, 'qualification_percentage' => 80,
        'created_at' => now(), 'updated_at' => now(),
    ]);
}

test('Competition API Readiness Gate: complete season lifecycle state machine and dry-run simulation endpoints', function (): void {
    $admin = withSuperAdmin(UserModel::query()->create([
        'id' => fake()->uuid(),
        'email' => 'admin-comp@quranplatform.com',
        'name' => 'Competition Admin',
        'type' => 'admin',
        'password_hash' => password_hash('AdminPass123!', PASSWORD_BCRYPT),
        'is_active' => true,
    ]));

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
        'title_es' => 'Temporada 2026',
        'public_name_ar' => 'موسم القرآن 2026',
        'public_name_en' => 'Quran Season 2026',
        'public_name_es' => 'Temporada del Corán 2026',
    ]);

    $createResponse->assertStatus(201)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.status', 'draft');

    $seasonId = $createResponse->json('data.id');
    cargt_configureSeasonRules($seasonId);

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
