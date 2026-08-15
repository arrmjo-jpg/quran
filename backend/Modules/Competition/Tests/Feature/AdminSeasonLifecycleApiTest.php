<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Modules\Competition\Application\UseCases\CreateStageUseCase;
use Modules\Competition\Domain\Repositories\SeasonRepositoryContract;
use Modules\Competition\Infrastructure\Database\Models\JudgeScoreSystemModel;
use Modules\Competition\Infrastructure\Database\Models\JudgeScoreSystemTranslationModel;
use Modules\Competition\Infrastructure\Database\Models\SeasonModel;
use Modules\Core\Infrastructure\Database\Models\UserModel;

/**
 * Covers the endpoints added once the API audit found them missing:
 * archive and cancel (whose Use Cases existed, fully tested, but were
 * unreachable over HTTP), the read side of the stage rules, and editing a
 * season's own descriptive data.
 */
uses(RefreshDatabase::class)->group('competition', 'feature', 'season-lifecycle-api');

function lifecycleAdmin(): UserModel
{
    return UserModel::query()->create([
        'id' => (string) Str::uuid(),
        'email' => 'lifecycle-admin-'.Str::random(8).'@quran.test',
        'name' => 'Lifecycle Admin',
        'type' => 'admin',
        'password_hash' => password_hash('Pass123!', PASSWORD_BCRYPT),
        'is_active' => true,
    ]);
}

function lifecycleSeason(string $status = 'draft'): string
{
    return SeasonModel::query()->create([
        'id' => (string) Str::uuid(), 'slug' => 'life-'.Str::random(8), 'year' => 2032,
        'registration_start' => '2032-01-01 00:00:00', 'registration_end' => '2032-01-15 00:00:00',
        'start_date' => '2032-01-16 00:00:00', 'end_date' => '2032-03-01 00:00:00',
        'status' => $status, 'is_active' => false,
    ])->id;
}

/** @return array<string, mixed> */
function lifecycleUpdatePayload(string $slug): array
{
    return [
        'slug' => $slug, 'year' => 2033,
        'registration_start' => '2033-01-01T00:00:00+00:00',
        'registration_end' => '2033-01-15T00:00:00+00:00',
        'start_date' => '2033-01-16T00:00:00+00:00',
        'end_date' => '2033-03-01T00:00:00+00:00',
        'title_ar' => 'موسم معدّل', 'public_name_ar' => 'الاسم العلني',
        'title_en' => 'Updated Season', 'public_name_en' => 'Public Name',
        'title_es' => 'Temporada', 'public_name_es' => 'Nombre Público',
    ];
}

test('POST archive archives a completed season', function (): void {
    $admin = lifecycleAdmin();
    $seasonId = lifecycleSeason('completed');

    $this->actingAs($admin)->postJson("/api/v1/admin/seasons/{$seasonId}/archive", [
        'reason' => 'season concluded',
    ])->assertStatus(200)
        ->assertJsonPath('data.status', 'archived')
        ->assertJsonPath('data.archive_reason', 'season concluded')
        ->assertJsonPath('data.is_active', false);

    expect($this->actingAs($admin)->getJson("/api/v1/seasons/{$seasonId}")->json('data.archived_by_user_id'))
        ->toBe($admin->id);
});

test('POST archive is refused for a season that has not completed', function (): void {
    $admin = lifecycleAdmin();
    $seasonId = lifecycleSeason('draft');

    $this->actingAs($admin)->postJson("/api/v1/admin/seasons/{$seasonId}/archive", [])
        ->assertStatus(409)
        ->assertJsonPath('error.code', 'INVALID_STATE_TRANSITION');
});

test('POST archive accepts an omitted reason', function (): void {
    $admin = lifecycleAdmin();
    $seasonId = lifecycleSeason('completed');

    $this->actingAs($admin)->postJson("/api/v1/admin/seasons/{$seasonId}/archive", [])
        ->assertStatus(200)
        ->assertJsonPath('data.archive_reason', null);
});

test('POST cancel cancels a draft season and records who did it', function (): void {
    $admin = lifecycleAdmin();
    $seasonId = lifecycleSeason('draft');

    $this->actingAs($admin)->postJson("/api/v1/admin/seasons/{$seasonId}/cancel", [
        'reason' => 'insufficient interest',
    ])->assertStatus(200)
        ->assertJsonPath('data.status', 'archived')
        ->assertJsonPath('data.archive_reason', 'insufficient interest')
        ->assertJsonPath('data.archived_by_user_id', $admin->id);
});

test('POST cancel requires a reason and refuses a season past draft', function (): void {
    $admin = lifecycleAdmin();

    $this->actingAs($admin)->postJson('/api/v1/admin/seasons/'.lifecycleSeason('draft').'/cancel', [])
        ->assertStatus(422);

    $this->actingAs($admin)->postJson('/api/v1/admin/seasons/'.lifecycleSeason('registration_closed').'/cancel', [
        'reason' => 'too late',
    ])->assertStatus(409)->assertJsonPath('error.code', 'INVALID_STATE_TRANSITION');
});

test('GET stage-rules returns the stored rule set', function (): void {
    $admin = lifecycleAdmin();
    $seasonId = lifecycleSeason('draft');

    $scoreSystem = JudgeScoreSystemModel::query()->create([
        'id' => (string) Str::uuid(), 'code' => 'out_of_50', 'max_score' => 50,
        'display_order' => 1, 'is_active' => true,
    ]);
    JudgeScoreSystemTranslationModel::query()->create([
        'id' => (string) Str::uuid(), 'judge_score_system_id' => $scoreSystem->id,
        'locale' => 'en', 'name' => 'Out of 50',
    ]);

    $stage = app(CreateStageUseCase::class)->execute(
        id: (string) Str::uuid(), seasonId: $seasonId, type: 'final',
        startDateIso: '2032-02-01T00:00:00+00:00', endDateIso: '2032-02-10T00:00:00+00:00',
        translations: ['en' => ['name' => 'Final', 'public_name' => 'Final Public']],
    );

    // Empty before anything is written.
    $this->actingAs($admin)->getJson("/api/v1/admin/seasons/{$seasonId}/stage-rules")
        ->assertStatus(200)->assertJsonCount(0, 'data');

    $this->actingAs($admin)->patchJson("/api/v1/admin/seasons/{$seasonId}/stage-rules", [
        'rules' => [
            ['stage_id' => $stage->id, 'judge_score_system_id' => $scoreSystem->id, 'qualification_percentage' => 80],
        ],
    ])->assertStatus(200);

    $this->actingAs($admin)->getJson("/api/v1/admin/seasons/{$seasonId}/stage-rules")
        ->assertStatus(200)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.stage_id', $stage->id)
        ->assertJsonPath('data.0.qualification_percentage', 80)
        ->assertJsonPath('data.0.judge_score_system.max_score', 50)
        ->assertJsonPath('data.0.required_score', 40);
});

test('PATCH season edits slug, year, dates, and translations', function (): void {
    $admin = lifecycleAdmin();
    $seasonId = lifecycleSeason('draft');
    $newSlug = 'renamed-'.Str::random(6);

    $this->actingAs($admin)->patchJson("/api/v1/admin/seasons/{$seasonId}", lifecycleUpdatePayload($newSlug))
        ->assertStatus(200)
        ->assertJsonPath('data.slug', $newSlug)
        ->assertJsonPath('data.year', 2033)
        ->assertJsonPath('data.translations.en.title', 'Updated Season')
        ->assertJsonPath('data.translations.ar.public_name', 'الاسم العلني');

    $reloaded = app(SeasonRepositoryContract::class)->findOrFail($seasonId);
    expect($reloaded->getSlug())->toBe($newSlug);
    expect($reloaded->getYear())->toBe(2033);
});

test('PATCH season allows a season to keep its own slug', function (): void {
    $admin = lifecycleAdmin();
    $seasonId = lifecycleSeason('draft');
    $currentSlug = SeasonModel::query()->findOrFail($seasonId)->slug;

    $this->actingAs($admin)->patchJson("/api/v1/admin/seasons/{$seasonId}", lifecycleUpdatePayload($currentSlug))
        ->assertStatus(200)
        ->assertJsonPath('data.slug', $currentSlug);
});

test('PATCH season rejects a slug another season already holds', function (): void {
    $admin = lifecycleAdmin();
    $seasonId = lifecycleSeason('draft');
    $takenSlug = SeasonModel::query()->findOrFail(lifecycleSeason('draft'))->slug;

    $this->actingAs($admin)->patchJson("/api/v1/admin/seasons/{$seasonId}", lifecycleUpdatePayload($takenSlug))
        ->assertStatus(422);
});

test('PATCH season is refused once the season is frozen', function (): void {
    $admin = lifecycleAdmin();
    $seasonId = lifecycleSeason('draft');

    // Freeze by hand: the point here is the guard, not the freeze path,
    // which OpenRegistrationEndToEndTest already drives over HTTP.
    SeasonModel::query()->where('id', $seasonId)->update(['frozen_at' => now(), 'status' => 'registration_open']);

    $this->actingAs($admin)->patchJson("/api/v1/admin/seasons/{$seasonId}", lifecycleUpdatePayload('frozen-'.Str::random(6)))
        ->assertStatus(409)
        ->assertJsonPath('error.code', 'SEASON_ALREADY_FROZEN');
});

test('PATCH season validates the date ordering', function (): void {
    $admin = lifecycleAdmin();
    $seasonId = lifecycleSeason('draft');

    $payload = lifecycleUpdatePayload('dates-'.Str::random(6));
    $payload['end_date'] = '2033-01-01T00:00:00+00:00'; // before start_date

    $this->actingAs($admin)->patchJson("/api/v1/admin/seasons/{$seasonId}", $payload)
        ->assertStatus(422);
});
