<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Competition\Domain\Repositories\SeasonRepositoryContract;
use Modules\Competition\Domain\ValueObjects\SeasonTranslation;
use Modules\Competition\Infrastructure\Database\Models\JudgeScoreSystemModel;
use Modules\Competition\Infrastructure\Database\Models\ParticipationTypeModel;
use Modules\Competition\Infrastructure\Database\Models\StageModel;
use Modules\Competition\Infrastructure\Database\Models\TajweedLevelModel;
use Modules\Core\Infrastructure\Database\Models\UserModel;
use Modules\Countries\Domain\Repositories\CountryRepositoryContract;
use Modules\Countries\Domain\ValueObjects\CountryIso2;
use Modules\Countries\Infrastructure\Database\Models\CountryModel;
use Modules\Countries\Infrastructure\Database\Seeders\CountriesSeeder;

uses(RefreshDatabase::class)->group('irg_001', 'system_integration');

/**
 * IRG-001 System Integration Readiness Gate
 *
 * Validates platform integration across 4 in-memory testable pillars:
 *   Pillar 1: Contestant E2E — Register → Login → Profile → Eligibility → Application → Ready For Judging
 *   Pillar 3: Admin Season Lifecycle — Create → Open → Close → Simulate Ranking
 *   Pillar 3B: State Machine Guard — Invalid transitions return 409 Conflict
 *   Pillar 5: Cache & ETag — Countries endpoint returns ETag and honours 304 Not Modified
 *
 * Pillars 2 (Judge Journey), 6 (R2 Media), 7 (FFmpeg HLS), 8 (Ranking Publish)
 * require live Docker infrastructure and are verified per the Docker procedure in IRG-001.md.
 *
 * NOTE: Uses actingAs() throughout to avoid Sanctum token resolution conflicts in-process.
 * The Login endpoint itself is tested separately in CoreApiReadinessGateTest.
 */

// ─────────────────────────────────────────────────────────────────────────────
// Helper: seed countries and return Jordan domain entity
// ─────────────────────────────────────────────────────────────────────────────
function irg_seed_jordan(): string
{
    $repo = app(CountryRepositoryContract::class);
    (new CountriesSeeder)->run($repo);
    $country = $repo->findByIso2(new CountryIso2('JO'));

    return (string) $country->id;
}

// ─────────────────────────────────────────────────────────────────────────────
// Helper: create a UserModel (contestant or admin)
// ─────────────────────────────────────────────────────────────────────────────
function irg_user(string $email, string $type = 'user'): UserModel
{
    return UserModel::query()->create([
        'id' => fake()->uuid(),
        'email' => $email,
        'name' => 'IRG Test User',
        'type' => $type,
        'password_hash' => password_hash('Pass123!', PASSWORD_BCRYPT),
        'is_active' => true,
    ]);
}

// ─────────────────────────────────────────────────────────────────────────────
// Helper: create season via admin and return its ID
// ─────────────────────────────────────────────────────────────────────────────
function irg_create_season(mixed $testCase, UserModel $admin, string $slug): string
{
    $response = $testCase->actingAs($admin)->postJson('/api/v1/admin/seasons', [
        'slug' => $slug,
        'year' => 2026,
        'registration_start' => '2026-08-01 00:00:00',
        'registration_end' => '2026-08-31 23:59:59',
        'start_date' => '2026-09-01 00:00:00',
        'end_date' => '2026-09-30 23:59:59',
        'title_ar' => 'موسم اختبار',
        'title_en' => 'Test Season',
    ]);
    $response->assertStatus(201);
    $seasonId = $response->json('data.id');

    // OpenSeasonRegistrationUseCase (the v2 replacement for the old, naive
    // openRegistration()) requires a season's rules to be fully resolved
    // before it may open registration. There is no admin API yet to set
    // any of this (a known, separate gap — see Step 9's verification
    // report), so every season this gate creates is configured directly
    // through the domain/repository, same as SeasonsTest's helper. This
    // stage/stage-rule pair is only for rule-completeness — callers that
    // need a stage to submit applications against still call
    // irg_create_stage() separately.
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

    $ruleStage = StageModel::query()->create([
        'id' => fake()->uuid(), 'season_id' => $seasonId, 'stage_number' => 99, 'type' => 'final',
        'start_date' => now(), 'end_date' => now()->addDay(), 'status' => 'pending',
    ]);

    $scoreSystem = JudgeScoreSystemModel::query()->create(['id' => fake()->uuid(), 'code' => 'out_of_100-'.Str::random(6), 'max_score' => 100, 'display_order' => 1, 'is_active' => true]);

    DB::table('season_stage_rules')->insert([
        'id' => fake()->uuid(), 'season_id' => $seasonId, 'stage_id' => $ruleStage->id,
        'judge_score_system_id' => $scoreSystem->id, 'qualification_percentage' => 80,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    return $seasonId;
}

// ─────────────────────────────────────────────────────────────────────────────
// Helper: insert a stage directly into DB and return its ID
// ─────────────────────────────────────────────────────────────────────────────
function irg_create_stage(string $seasonId): string
{
    $stageId = fake()->uuid();
    DB::table('stages')->insert([
        'id' => $stageId,
        'season_id' => $seasonId,
        'stage_number' => 1,
        'type' => 'preliminary',
        'start_date' => now()->format('Y-m-d H:i:s'),
        'end_date' => now()->addDays(30)->format('Y-m-d H:i:s'),
        'status' => 'active',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return $stageId;
}

// ─────────────────────────────────────────────────────────────────────────────
// PILLAR 1: Contestant E2E Journey
// ─────────────────────────────────────────────────────────────────────────────
test('IRG-001 Pillar 1 — Contestant E2E: Register → Profile → Eligibility → Submit Application → Ready For Judging', function (): void {
    // ── Seed countries ────────────────────────────────────────────────────────
    $countryId = irg_seed_jordan();

    // ── Step 1: Verify Registration endpoint works (auth gate test) ───────────
    $registerResponse = $this->postJson('/api/v1/auth/register', [
        'name' => 'Khalid Al-Fahad',
        'email' => 'irg-p1-contestant@quranplatform.test',
        'password' => 'SecurePass123!',
    ]);
    $registerResponse->assertStatus(201)->assertJsonPath('success', true);

    // ── Step 2: Verify Login endpoint returns token ───────────────────────────
    $loginResponse = $this->postJson('/api/v1/auth/login', [
        'email' => 'irg-p1-contestant@quranplatform.test',
        'password' => 'SecurePass123!',
    ]);
    $loginResponse->assertStatus(200)->assertJsonPath('success', true);
    expect($loginResponse->json('data.token'))->not->toBeNull();

    // ── From here: use actingAs to avoid Sanctum in-process token conflicts ───
    $contestantUser = UserModel::query()
        ->where('email', 'irg-p1-contestant@quranplatform.test')
        ->firstOrFail();

    // ── Step 3: Create Contestant Profile ─────────────────────────────────────
    $profileResponse = $this->actingAs($contestantUser)->postJson('/api/v1/contestant/profile', [
        'country_id' => $countryId,
        'full_name' => 'Khalid Al-Fahad',
        'date_of_birth' => '1998-06-15',
        'gender' => 'male',
        'phone_number' => '+962791234567',
    ]);
    $profileResponse->assertStatus(200)->assertJsonPath('success', true);

    // ── Setup Admin + Season + Stage ──────────────────────────────────────────
    // Must happen before the eligibility check below: eligibility is evaluated
    // against the active season's start date, so a season needs to be open.
    $admin = irg_user('irg-p1-admin@quranplatform.test', 'admin');
    $seasonId = irg_create_season($this, $admin, 'irg-p1-season');

    $this->actingAs($admin)
        ->postJson("/api/v1/admin/seasons/{$seasonId}/open-registration")
        ->assertStatus(200)
        ->assertJsonPath('data.status', 'registration_open');

    $stageId = irg_create_stage($seasonId);

    // ── Step 4: Check Eligibility ─────────────────────────────────────────────
    $eligibilityResponse = $this->actingAs($contestantUser)->getJson('/api/v1/contestant/eligibility');
    $eligibilityResponse->assertStatus(200)->assertJsonPath('success', true);
    expect($eligibilityResponse->json('data.is_eligible'))->toBeTrue();

    // ── Step 5: Upload Media & Submit Application ────────────────────────────
    $videoFile = UploadedFile::fake()->create('recitation.mp4', 5000, 'video/mp4');
    $uploadResponse = $this->actingAs($contestantUser)->postJson('/api/v1/contestant/media/upload', [
        'file' => $videoFile,
    ]);
    $uploadResponse->assertStatus(201)->assertJsonPath('success', true);
    $mediaId = $uploadResponse->json('data.id');

    $applicationResponse = $this->actingAs($contestantUser)->postJson('/api/v1/applications', [
        'season_id' => $seasonId,
        'stage_id' => $stageId,
        'video_media_asset_id' => $mediaId,
    ]);
    $applicationResponse->assertStatus(201)->assertJsonPath('success', true);
    $applicationId = $applicationResponse->json('data.id');
    expect($applicationId)->not->toBeNull();

    // ── Step 6: Application appears in Admin Review Queue ─────────────────────
    $queueResponse = $this->actingAs($admin)->getJson('/api/v1/admin/applications');
    $queueResponse->assertStatus(200)->assertJsonPath('success', true);
    $appIds = collect($queueResponse->json('data'))->pluck('id');
    expect($appIds)->toContain($applicationId);

    // ── Step 7: Admin marks Ready For Judging ─────────────────────────────────
    $this->actingAs($admin)
        ->postJson("/api/v1/admin/applications/{$applicationId}/ready-for-judging")
        ->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.status', 'ready_for_judging');
});

// ─────────────────────────────────────────────────────────────────────────────
// PILLAR 3: Admin Season Lifecycle
// ─────────────────────────────────────────────────────────────────────────────
test('IRG-001 Pillar 3 — Admin Lifecycle: Create Season → Open → Close → Dry-Run Simulation with Tie-Breaker', function (): void {
    $admin = irg_user('irg-p3-admin@quranplatform.test', 'admin');
    $seasonId = irg_create_season($this, $admin, 'irg-p3-season');

    // Open Registration
    $this->actingAs($admin)
        ->postJson("/api/v1/admin/seasons/{$seasonId}/open-registration")
        ->assertStatus(200)
        ->assertJsonPath('data.status', 'registration_open');

    // Close Registration
    $this->actingAs($admin)
        ->postJson("/api/v1/admin/seasons/{$seasonId}/close-registration")
        ->assertStatus(200)
        ->assertJsonPath('data.status', 'registration_closed');

    // Create stage
    $stageId = irg_create_stage($seasonId);

    // Dry-Run Ranking Simulation with deliberate tie to test Tie-Breaker
    $appId1 = fake()->uuid();
    $appId2 = fake()->uuid();
    $appId3 = fake()->uuid();

    $simulationResponse = $this->actingAs($admin)->postJson("/api/v1/admin/stages/{$stageId}/simulate-ranking", [
        'scores' => [
            // App2: tied on total with App1, but LOWER tajweed → rank 2
            ['application_id' => $appId2, 'total_score' => 98.5, 'tajweed_score' => 28.0, 'memorization_score' => 29.5, 'voice_score' => 20.0],
            // App1: tied on total with App2, but HIGHER tajweed → rank 1
            ['application_id' => $appId1, 'total_score' => 98.5, 'tajweed_score' => 30.0, 'memorization_score' => 28.5, 'voice_score' => 20.0],
            // App3: lowest score → rank 3
            ['application_id' => $appId3, 'total_score' => 91.0, 'tajweed_score' => 27.0, 'memorization_score' => 27.0, 'voice_score' => 18.0],
        ],
    ]);

    $simulationResponse->assertStatus(200)->assertJsonPath('success', true);

    $ranked = $simulationResponse->json('data.simulation');
    expect($ranked)->not->toBeEmpty();

    // App1 should be rank 1 (tied total, highest tajweed wins tie-breaker)
    $result1 = collect($ranked)->firstWhere('application_id', $appId1);
    $result2 = collect($ranked)->firstWhere('application_id', $appId2);
    $result3 = collect($ranked)->firstWhere('application_id', $appId3);

    expect($result1['rank'])->toBe(1);
    expect($result2['rank'])->toBe(2);
    expect($result3['rank'])->toBe(3);
});

// ─────────────────────────────────────────────────────────────────────────────
// PILLAR 3B: State Machine Guard
// ─────────────────────────────────────────────────────────────────────────────
test('IRG-001 Pillar 3B — State Machine Guard: invalid transitions return 409 Conflict', function (): void {
    $admin = irg_user('irg-p3b-admin@quranplatform.test', 'admin');
    $seasonId = irg_create_season($this, $admin, 'irg-p3b-season');

    // Cannot close from draft (not yet opened)
    $this->actingAs($admin)
        ->postJson("/api/v1/admin/seasons/{$seasonId}/close-registration")
        ->assertStatus(409)
        ->assertJsonPath('error.code', 'INVALID_STATE_TRANSITION');

    // Open registration successfully
    $this->actingAs($admin)
        ->postJson("/api/v1/admin/seasons/{$seasonId}/open-registration")
        ->assertStatus(200);

    // Cannot open registration again (already open)
    $this->actingAs($admin)
        ->postJson("/api/v1/admin/seasons/{$seasonId}/open-registration")
        ->assertStatus(409)
        ->assertJsonPath('error.code', 'INVALID_STATE_TRANSITION');
});

// ─────────────────────────────────────────────────────────────────────────────
// PILLAR 5: Tagged Cache & ETag Invalidation
// ─────────────────────────────────────────────────────────────────────────────
test('IRG-001 Pillar 5 — Cache & ETag: Countries endpoint returns ETag and honours 304 Not Modified', function (): void {
    irg_seed_jordan();

    // ── Request 1: Fresh → 200 OK with ETag ──────────────────────────────────
    $firstResponse = $this->getJson('/api/v1/countries');
    $firstResponse->assertStatus(200)->assertJsonPath('success', true);
    $etag = $firstResponse->headers->get('ETag');
    expect($etag)->not->toBeNull();

    // ── Request 2: Matching ETag → 304 Not Modified ───────────────────────────
    $cachedResponse = $this
        ->withHeader('If-None-Match', $etag)
        ->getJson('/api/v1/countries');
    $cachedResponse->assertStatus(304);

    // ── Request 3: Stale ETag → 200 OK with same ETag (data unchanged) ────────
    $staleResponse = $this
        ->withHeader('If-None-Match', '"stale-etag-value-that-does-not-match"')
        ->getJson('/api/v1/countries');
    $staleResponse->assertStatus(200);
    $newEtag = $staleResponse->headers->get('ETag');
    expect($newEtag)->toBe($etag); // same data → same ETag
});
