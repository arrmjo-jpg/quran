<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Modules\Competition\Infrastructure\Database\Models\EvaluationCriteriaModel;
use Modules\Competition\Infrastructure\Database\Models\EvaluationTemplateModel;
use Modules\Competition\Infrastructure\Database\Models\SeasonModel;
use Modules\Competition\Infrastructure\Database\Models\StageModel;
use Modules\Core\Infrastructure\Database\Models\UserModel;
use Modules\Countries\Infrastructure\Database\Models\CountryModel;
use Modules\Evaluations\Infrastructure\Database\Models\EvaluationModel;
use Modules\Judges\Infrastructure\Database\Models\JudgeModel;

uses(RefreshDatabase::class)->group('e2e', 'prg_002_runtime');

beforeEach(function (): void {
    Storage::fake('public');
});

test('E2E Full Journey — Register -> Profile -> Upload -> App -> 5 Judges -> Calculate -> Publish -> Appeal -> Resolve', function (): void {
    // ── 0. SEED CONSTANTS ───────────────────────────────────────────────────
    $country = CountryModel::query()->create([
        'id' => fake()->uuid(),
        'iso_code' => 'SA',
        'iso3_code' => 'SAU',
        'phone_code' => '+966',
        'is_active' => true,
    ]);

    $admin = UserModel::query()->create([
        'id' => fake()->uuid(),
        'email' => 'e2e-admin@quran.test',
        'name' => 'Super Admin',
        'type' => 'admin',
        'password_hash' => password_hash('Pass123!', PASSWORD_BCRYPT),
        'is_active' => true,
    ]);

    // ── 1. REGISTER CONTESTANT ───────────────────────────────────────────────
    $regResponse = $this->postJson('/api/v1/auth/register', [
        'name' => 'Ahmad Reciter',
        'email' => 'ahmad@quran.test',
        'password' => 'Pass123!',
    ]);
    $regResponse->assertStatus(201)->assertJsonPath('success', true);
    $contestantUserId = $regResponse->json('data.id');
    $contestantUser = UserModel::query()->findOrFail($contestantUserId);

    // ── 2. CREATE PROFILE ───────────────────────────────────────────────────
    $profileResponse = $this->actingAs($contestantUser)->postJson('/api/v1/contestant/profile', [
        'country_id' => $country->id,
        'full_name' => 'Ahmad Reciter',
        'date_of_birth' => '1998-05-15',
        'gender' => 'male',
        'national_id' => '1098765432',
        'phone_number' => '+966500000000',
    ]);
    $profileResponse->assertStatus(200)->assertJsonPath('success', true);
    $contestantId = $profileResponse->json('data.id');

    // ── 3. UPLOAD MEDIA (VIDEO) ─────────────────────────────────────────────
    $videoFile = UploadedFile::fake()->create('recitation.mp4', 5000, 'video/mp4');
    $uploadResponse = $this->actingAs($contestantUser)->postJson('/api/v1/contestant/media/upload', [
        'file' => $videoFile,
    ]);
    $uploadResponse->assertStatus(201)->assertJsonPath('success', true);
    $mediaId = $uploadResponse->json('data.id');

    // ── 4. CREATE SEASON & STAGE (ADMIN) ────────────────────────────────────
    $season = SeasonModel::query()->create([
        'id' => fake()->uuid(),
        'slug' => 'season-2026',
        'year' => 2026,
        'registration_start' => now()->subDays(5),
        'registration_end' => now()->addDays(20),
        'start_date' => now()->addDays(21),
        'end_date' => now()->addMonths(2),
        'status' => 'registration_open',
        'is_active' => true,
    ]);

    $template = EvaluationTemplateModel::query()->create([
        'id' => fake()->uuid(),
        'season_id' => $season->id,
        'name' => 'Standard Evaluation Template',
        'max_total_score' => 100.0,
        'is_active' => true,
    ]);

    $stage = StageModel::query()->create([
        'id' => fake()->uuid(),
        'season_id' => $season->id,
        'evaluation_template_id' => $template->id,
        'stage_number' => 1,
        'type' => 'preliminary',
        'start_date' => now(),
        'end_date' => now()->addMonth(),
        'status' => 'active',
    ]);

    $criterion = EvaluationCriteriaModel::query()->create([
        'id' => fake()->uuid(),
        'template_id' => $template->id,
        'name' => 'Tajweed',
        'code' => 'tajweed',
        'max_score' => 100.0,
        'weight_percent' => 100.0,
        'display_order' => 1,
    ]);

    // ── 5. SUBMIT APPLICATION (CONTESTANT) ───────────────────────────────────
    $appResponse = $this->actingAs($contestantUser)->postJson('/api/v1/applications', [
        'season_id' => $season->id,
        'stage_id' => $stage->id,
        'video_media_asset_id' => $mediaId,
    ]);
    $appResponse->assertStatus(201)->assertJsonPath('success', true);
    $appId = $appResponse->json('data.id');

    // ── 6. REVIEW APPLICATION -> READY FOR JUDGING (ADMIN) ──────────────────
    $reviewResponse = $this->actingAs($admin)->postJson("/api/v1/admin/applications/{$appId}/ready-for-judging");
    $reviewResponse->assertStatus(200)->assertJsonPath('success', true);

    // ── 7. ASSIGN 5 JUDGES & SUBMIT EVALUATIONS ─────────────────────────────
    $judgeUsers = [];
    $judgeModels = [];

    for ($i = 1; $i <= 5; $i++) {
        $jUser = UserModel::query()->create([
            'id' => fake()->uuid(),
            'email' => "judge{$i}@quran.test",
            'name' => "Judge {$i}",
            'type' => 'admin',
            'password_hash' => password_hash('Pass123!', PASSWORD_BCRYPT),
            'is_active' => true,
        ]);

        $jModel = JudgeModel::query()->create([
            'id' => fake()->uuid(),
            'user_id' => $jUser->id,
            'full_name' => "Sheikh Judge {$i}",
            'specialization' => 'Tajweed & Qiraat',
            'is_active' => true,
        ]);

        $judgeUsers[] = $jUser;
        $judgeModels[] = $jModel;

        // Create evaluation entry
        $eval = EvaluationModel::query()->create([
            'id' => fake()->uuid(),
            'application_id' => $appId,
            'judge_id' => (string) $jModel->id,
            'status' => 'draft',
            'total_score' => 0,
        ]);

        // Judge submits scores (85 + $i to have varying scores)
        $score = 85.0 + $i;
        $submitResponse = $this->actingAs($jUser)->postJson("/api/v1/judge/evaluations/{$eval->id}/submit", [
            'criteria_scores' => [
                ['criterion_id' => $criterion->id, 'score' => $score],
            ],
            'notes' => 'Excellent recitation by contestant',
        ]);
        $submitResponse->assertStatus(200)->assertJsonPath('success', true);
    }

    // Verify all 5 evaluations submitted
    $submittedCount = EvaluationModel::query()
        ->where('application_id', $appId)
        ->where('status', 'submitted')
        ->count();
    expect($submittedCount)->toBe(5);

    // ── 8. CALCULATE RESULTS (ADMIN) ─────────────────────────────────────────
    $calcResponse = $this->actingAs($admin)->postJson("/api/v1/admin/stages/{$stage->id}/calculate-results", [
        'min_qualification_threshold' => 80.0,
    ]);
    $calcResponse->assertStatus(200)->assertJsonPath('success', true);

    $this->assertDatabaseHas('stage_results', [
        'stage_id' => $stage->id,
    ]);

    // ── 9. PUBLISH RESULTS (ADMIN) ───────────────────────────────────────────
    $pubResponse = $this->actingAs($admin)->postJson("/api/v1/admin/stages/{$stage->id}/publish-results");
    $pubResponse->assertStatus(200)->assertJsonPath('success', true);

    // ── 10. SUBMIT APPEAL (CONTESTANT) ───────────────────────────────────────
    $appealResponse = $this->actingAs($contestantUser)->postJson('/api/v1/contestant/appeals', [
        'application_id' => $appId,
        'reason' => 'Requesting review of final score calculation for stage 1',
    ]);
    $appealResponse->assertStatus(201)->assertJsonPath('success', true);
    $appealId = $appealResponse->json('data.id');

    // ── 11. RESOLVE APPEAL (ADMIN ACCEPT) ─────────────────────────────────────
    $acceptResponse = $this->actingAs($admin)->postJson("/api/v1/admin/appeals/{$appealId}/accept", [
        'admin_response' => 'Appeal accepted after administrative review of recitation notes',
    ]);
    $acceptResponse->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.status', 'accepted');

    $this->assertDatabaseHas('appeals', [
        'id' => $appealId,
        'status' => 'accepted',
    ]);
});
