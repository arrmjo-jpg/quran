<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Infrastructure\Database\Models\UserModel;
use Modules\Evaluations\Infrastructure\Database\Models\AppealModel;
use Modules\Evaluations\Infrastructure\Database\Models\EvaluationModel;
use Modules\Evaluations\Infrastructure\Database\Models\StageResultModel;
use Modules\Judges\Infrastructure\Database\Models\JudgeModel;

uses(RefreshDatabase::class)->group('evaluations', 'irg_001');

// SQLite FK checks disabled for in-memory test DB.
// FK integrity is enforced at the application/domain layer (controllers + use cases),
// not by the DB engine in tests — this is standard Laravel test practice.
beforeEach(function (): void {
    if (DB::getDriverName() === 'sqlite') {
        DB::statement('PRAGMA foreign_keys = OFF');
    } else {
        Schema::disableForeignKeyConstraints();
    }
});

// ─────────────────────────────────────────────────────────────────────────────
// Helpers
// ─────────────────────────────────────────────────────────────────────────────

/** Create a UserModel directly (no factory). */
function eval_user(string $email, string $type = 'user'): UserModel
{
    return UserModel::query()->create([
        'id' => fake()->uuid(),
        'email' => $email,
        'name' => 'Eval Test User',
        'type' => $type,
        'password_hash' => password_hash('Pass123!', PASSWORD_BCRYPT),
        'is_active' => true,
    ]);
}

/** Create a JudgeModel for a user. */
function eval_judge(string $userId): JudgeModel
{
    return JudgeModel::query()->create([
        'id' => fake()->uuid(),
        'user_id' => $userId,
        'full_name' => 'Judge Test',
        'title' => 'Sheikh',
        'specialization' => 'Tajweed',
        'is_active' => true,
    ]);
}

/** Create an EvaluationModel directly. */
function eval_evaluation(string $appId, string $judgeId, string $status = 'draft'): EvaluationModel
{
    return EvaluationModel::query()->create([
        'id' => fake()->uuid(),
        'application_id' => $appId,
        'judge_id' => $judgeId,
        'total_score' => 0.0,
        'status' => $status,
    ]);
}

/** Insert a country + contestant + user into DB for appeal tests. */
function eval_contestant_user(string $email): array
{
    $countryId = fake()->uuid();
    $contestantId = fake()->uuid();

    DB::table('countries')->insert([
        'id' => $countryId,
        'iso_code' => strtoupper(substr(md5($email), 0, 2)),
        'iso3_code' => strtoupper(substr(md5($email), 0, 3)),
        'phone_code' => '000',
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $user = eval_user($email);

    DB::table('contestants')->insert([
        'id' => $contestantId,
        'user_id' => $user->id,
        'country_id' => $countryId,
        'full_name' => 'Test Contestant',
        'date_of_birth' => '2000-01-01',
        'gender' => 'male',
        'phone_number' => '+962700000000',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return ['user' => $user, 'contestant_id' => $contestantId];
}

// ─────────────────────────────────────────────────────────────────────────────
// JUDGE BLINDNESS TESTS
// ─────────────────────────────────────────────────────────────────────────────

test('Evaluations 16.7.1 — Judge Blindness: judge cannot access another judge\'s evaluation (403)', function (): void {
    $user1 = eval_user('judge1@eval.test');
    $user2 = eval_user('judge2@eval.test');
    $judge1 = eval_judge($user1->id);
    $judge2 = eval_judge($user2->id);

    $appId = fake()->uuid();
    // Create an evaluation assigned to judge1
    $evaluation = eval_evaluation($appId, $judge1->id, 'draft');

    // judge2 tries to access judge1's evaluation — must be 403
    $this->actingAs($user2)
        ->getJson("/api/v1/judge/evaluations/{$evaluation->id}")
        ->assertStatus(403)
        ->assertJsonPath('error.code', 'FORBIDDEN');
});

test('Evaluations 16.7.2 — Judge Blindness: judge can only see own evaluations in list', function (): void {
    $user1 = eval_user('judge1-list@eval.test');
    $user2 = eval_user('judge2-list@eval.test');
    $judge1 = eval_judge($user1->id);
    $judge2 = eval_judge($user2->id);

    $appId1 = fake()->uuid();
    $appId2 = fake()->uuid();
    eval_evaluation($appId1, $judge1->id, 'draft');   // belongs to judge1
    eval_evaluation($appId2, $judge2->id, 'draft');   // belongs to judge2

    $response = $this->actingAs($user1)->getJson('/api/v1/judge/evaluations');
    $response->assertStatus(200)->assertJsonPath('success', true);

    $ids = collect($response->json('data'))->pluck('application_id');
    // judge1 sees only their evaluation (appId1), not judge2's (appId2)
    expect($ids)->toContain($appId1);
    expect($ids)->not->toContain($appId2);
});

test('Evaluations 16.7.3 — Double submission blocked: judge cannot submit locked evaluation', function (): void {
    $user = eval_user('judge-double@eval.test');
    $judge = eval_judge($user->id);
    $appId = fake()->uuid();

    // Create already-submitted evaluation
    $evaluation = eval_evaluation($appId, $judge->id, 'submitted');

    $this->actingAs($user)
        ->postJson("/api/v1/judge/evaluations/{$evaluation->id}/submit", [
            'criteria_scores' => [
                ['criterion_id' => fake()->uuid(), 'score' => 25],
                ['criterion_id' => fake()->uuid(), 'score' => 25],
            ],
        ])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'DOMAIN_ERROR');
});

test('Evaluations 16.7.4 — Judge can start evaluation (draft→in_progress)', function (): void {
    $user = eval_user('judge-start@eval.test');
    $judge = eval_judge($user->id);
    $appId = fake()->uuid();

    $evaluation = eval_evaluation($appId, $judge->id, 'draft');

    $this->actingAs($user)
        ->postJson("/api/v1/judge/evaluations/{$evaluation->id}/start")
        ->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.status', 'in_progress');
});

test('Evaluations 16.7.5 — Judge cannot start already-started evaluation (409)', function (): void {
    $user = eval_user('judge-restart@eval.test');
    $judge = eval_judge($user->id);
    $appId = fake()->uuid();

    $evaluation = eval_evaluation($appId, $judge->id, 'in_progress');

    $this->actingAs($user)
        ->postJson("/api/v1/judge/evaluations/{$evaluation->id}/start")
        ->assertStatus(409)
        ->assertJsonPath('error.code', 'INVALID_STATE');
});

// ─────────────────────────────────────────────────────────────────────────────
// ADMIN RESULTS TESTS
// ─────────────────────────────────────────────────────────────────────────────

test('Evaluations 16.7.6 — Admin: publish-results fails when no calculated results exist (422)', function (): void {
    $admin = eval_user('admin-noresults@eval.test', 'admin');
    $stageId = fake()->uuid();

    $this->actingAs($admin)
        ->postJson("/api/v1/admin/stages/{$stageId}/publish-results")
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'RESULTS_NOT_CALCULATED');
});

test('Evaluations 16.7.7 — Admin: can publish draft stage results', function (): void {
    $admin = eval_user('admin-publish@eval.test', 'admin');
    $stageId = fake()->uuid();

    // Create a draft stage_result header
    StageResultModel::query()->create([
        'id' => fake()->uuid(),
        'stage_id' => $stageId,
        'status' => 'draft',
    ]);

    $this->actingAs($admin)
        ->postJson("/api/v1/admin/stages/{$stageId}/publish-results")
        ->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.status', 'published');

    $this->assertDatabaseHas('stage_results', ['stage_id' => $stageId, 'status' => 'published']);
});

test('Evaluations 16.7.8 — Admin: cannot publish already-published results (409)', function (): void {
    $admin = eval_user('admin-republish@eval.test', 'admin');
    $stageId = fake()->uuid();

    StageResultModel::query()->create([
        'id' => fake()->uuid(),
        'stage_id' => $stageId,
        'status' => 'published',
    ]);

    $this->actingAs($admin)
        ->postJson("/api/v1/admin/stages/{$stageId}/publish-results")
        ->assertStatus(409)
        ->assertJsonPath('error.code', 'ALREADY_PUBLISHED');
});

test('Evaluations 16.7.9 — Admin: can reopen published results (reverts to draft)', function (): void {
    $admin = eval_user('admin-reopen@eval.test', 'admin');
    $stageId = fake()->uuid();

    StageResultModel::query()->create([
        'id' => fake()->uuid(),
        'stage_id' => $stageId,
        'status' => 'published',
        'published_at' => now(),
    ]);

    $this->actingAs($admin)
        ->postJson("/api/v1/admin/stages/{$stageId}/reopen-results")
        ->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.status', 'draft');

    $this->assertDatabaseHas('stage_results', ['stage_id' => $stageId, 'status' => 'draft']);
});

test('Evaluations 16.7.10 — Admin: reopen fails when results not yet published (422)', function (): void {
    $admin = eval_user('admin-reopen-fail@eval.test', 'admin');
    $stageId = fake()->uuid();

    // No stage_result exists
    $this->actingAs($admin)
        ->postJson("/api/v1/admin/stages/{$stageId}/reopen-results")
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'NOT_PUBLISHED');
});

// ─────────────────────────────────────────────────────────────────────────────
// APPEALS TESTS
// ─────────────────────────────────────────────────────────────────────────────

test('Evaluations 16.7.11 — Contestant can submit appeal', function (): void {
    $data = eval_contestant_user('contestant-appeal@eval.test');
    $user = $data['user'];
    $contestantId = $data['contestant_id'];
    $appId = fake()->uuid();

    $this->actingAs($user)
        ->postJson('/api/v1/contestant/appeals', [
            'application_id' => $appId,
            'reason' => 'The evaluation was unfair and did not consider my memorization level correctly.',
        ])
        ->assertStatus(201)
        ->assertJsonPath('success', true);

    $this->assertDatabaseHas('appeals', [
        'contestant_id' => $contestantId,
        'application_id' => $appId,
        'status' => 'pending',
    ]);
});

test('Evaluations 16.7.12 — Admin can accept pending appeal', function (): void {
    $admin = eval_user('admin-accept@eval.test', 'admin');

    $appeal = AppealModel::query()->create([
        'id' => fake()->uuid(),
        'application_id' => fake()->uuid(),
        'contestant_id' => fake()->uuid(),
        'reason' => 'The evaluation was not conducted correctly according to the rulebook.',
        'status' => 'pending',
    ]);

    $this->actingAs($admin)
        ->postJson("/api/v1/admin/appeals/{$appeal->id}/accept", [
            'admin_response' => 'After reviewing, we accept this appeal and will re-evaluate.',
        ])
        ->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.status', 'accepted');

    $this->assertDatabaseHas('appeals', ['id' => $appeal->id, 'status' => 'accepted']);
});

test('Evaluations 16.7.13 — Admin cannot accept already-resolved appeal (409)', function (): void {
    $admin = eval_user('admin-conflict@eval.test', 'admin');

    $appeal = AppealModel::query()->create([
        'id' => fake()->uuid(),
        'application_id' => fake()->uuid(),
        'contestant_id' => fake()->uuid(),
        'reason' => 'The evaluation was not conducted correctly according to the rulebook.',
        'status' => 'rejected', // already resolved
    ]);

    $this->actingAs($admin)
        ->postJson("/api/v1/admin/appeals/{$appeal->id}/accept", [
            'admin_response' => 'Trying to accept a rejected appeal.',
        ])
        ->assertStatus(409)
        ->assertJsonPath('error.code', 'ALREADY_RESOLVED');
});

test('Evaluations 16.7.14 — Admin can reject pending appeal', function (): void {
    $admin = eval_user('admin-reject@eval.test', 'admin');

    $appeal = AppealModel::query()->create([
        'id' => fake()->uuid(),
        'application_id' => fake()->uuid(),
        'contestant_id' => fake()->uuid(),
        'reason' => 'The evaluation was not conducted correctly according to the rulebook.',
        'status' => 'pending',
    ]);

    $this->actingAs($admin)
        ->postJson("/api/v1/admin/appeals/{$appeal->id}/reject", [
            'admin_response' => 'After careful review, this appeal does not meet the criteria for acceptance.',
        ])
        ->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.status', 'rejected');

    $this->assertDatabaseHas('appeals', ['id' => $appeal->id, 'status' => 'rejected']);
});

test('Evaluations 16.7.15 — Admin evaluations list is accessible with filtering', function (): void {
    $admin = eval_user('admin-list@eval.test', 'admin');

    $this->actingAs($admin)
        ->getJson('/api/v1/admin/evaluations?status=draft')
        ->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonStructure(['success', 'data', 'meta' => ['total', 'per_page', 'current_page']]);
});
