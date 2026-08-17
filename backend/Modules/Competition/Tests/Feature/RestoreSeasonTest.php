<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Competition\Application\UseCases\RestoreSeasonUseCase;
use Modules\Competition\Domain\Events\SeasonRestored;
use Modules\Competition\Domain\Exceptions\SeasonNotRestorableException;
use Modules\Competition\Infrastructure\Database\Models\SeasonModel;
use Modules\Competition\Infrastructure\Database\Models\StageModel;
use Modules\Core\Infrastructure\Database\Models\UserModel;
use Modules\Countries\Infrastructure\Database\Models\CountryModel;

/**
 * Restoring is not the inverse of archiving. It exists only to undo an
 * archival that should never have happened — a draft season cancelled by
 * mistake — and every other archived season must stay archived.
 *
 * These tests are written around what makes a restore *refuse*, because
 * that is the part protecting the record.
 */
uses(RefreshDatabase::class)->group('competition', 'feature', 'season-restore');

function restoreAdmin(): UserModel
{
    return withSuperAdmin(UserModel::query()->create([
        'id' => (string) Str::uuid(),
        'email' => 'restore-admin-'.Str::random(8).'@quran.test',
        'name' => 'Restore Admin',
        'type' => 'admin',
        'password_hash' => password_hash('Pass123!', PASSWORD_BCRYPT),
        'is_active' => true,
    ]));
}

/** @param array<string, mixed> $overrides */
function restoreSeasonRow(array $overrides = []): string
{
    return SeasonModel::query()->create(array_merge([
        'id' => (string) Str::uuid(),
        'slug' => 'restore-'.Str::random(8),
        'year' => 2040,
        'registration_start' => '2040-01-01 00:00:00',
        'registration_end' => '2040-01-15 00:00:00',
        'start_date' => '2040-01-16 00:00:00',
        'end_date' => '2040-03-01 00:00:00',
        'status' => 'archived',
        'is_active' => false,
        'archived_at' => '2040-04-01 00:00:00',
        'archived_by_user_id' => null,
        'archive_reason' => 'cancelled by mistake',
    ], $overrides))->id;
}

function restoreStage(string $seasonId): string
{
    return StageModel::query()->create([
        'id' => (string) Str::uuid(), 'season_id' => $seasonId,
        'stage_number' => 1, 'type' => 'final',
        'start_date' => '2040-02-01 00:00:00', 'end_date' => '2040-02-10 00:00:00',
        'status' => 'pending',
    ])->id;
}

test('an accidentally cancelled draft season goes back to draft with its archive metadata cleared', function (): void {
    $admin = restoreAdmin();
    $seasonId = restoreSeasonRow(['archived_by_user_id' => $admin->id]);

    $season = app(RestoreSeasonUseCase::class)->execute($seasonId, $admin->id);

    expect($season->getStatus())->toBe('draft');
    expect($season->isActive())->toBeFalse();
    expect($season->getArchivedAtIso())->toBeNull();
    expect($season->getArchivedByUserId())->toBeNull();
    expect($season->getArchiveReason())->toBeNull();

    $row = SeasonModel::query()->findOrFail($seasonId);
    expect($row->status)->toBe('draft');
    expect($row->archived_at)->toBeNull();
    expect($row->archived_by_user_id)->toBeNull();
    expect($row->archive_reason)->toBeNull();
});

test('restoring keeps the season configuration it already had', function (): void {
    $seasonId = restoreSeasonRow();
    $stageId = restoreStage($seasonId);

    app(RestoreSeasonUseCase::class)->execute($seasonId);

    // Stages, stage rules and eligible countries are the season's own
    // setup, not evidence it ran — a restored draft keeps them.
    expect(StageModel::query()->find($stageId))->not->toBeNull();
});

test('a frozen season is refused, even if nothing else references it', function (): void {
    $seasonId = restoreSeasonRow(['frozen_at' => '2040-01-02 00:00:00']);

    expect(fn () => app(RestoreSeasonUseCase::class)->execute($seasonId))
        ->toThrow(SeasonNotRestorableException::class);

    expect(SeasonModel::query()->findOrFail($seasonId)->status)->toBe('archived');
});

test('a season that was never archived is refused', function (): void {
    $seasonId = restoreSeasonRow(['status' => 'draft', 'archived_at' => null, 'archive_reason' => null]);

    expect(fn () => app(RestoreSeasonUseCase::class)->execute($seasonId))
        ->toThrow(SeasonNotRestorableException::class);
});

test('a rule snapshot blocks the restore and is reported as such', function (): void {
    $seasonId = restoreSeasonRow();

    DB::table('season_rule_versions')->insert([
        'id' => (string) Str::uuid(), 'season_id' => $seasonId, 'version' => 1,
        'snapshot_json' => json_encode(['version' => 1]), 'created_at' => now(),
    ]);

    try {
        app(RestoreSeasonUseCase::class)->execute($seasonId);
        $this->fail('expected the restore to be refused');
    } catch (SeasonNotRestorableException $e) {
        expect($e->reason)->toBe(SeasonNotRestorableException::HAS_SNAPSHOTS);
        expect($e->details)->toContain('season_rule_versions: 1');
    }
});

test('stage results block the restore through the season stages', function (): void {
    $seasonId = restoreSeasonRow();
    $stageId = restoreStage($seasonId);

    // One row per stage, holding publication state — not one per contestant.
    DB::table('stage_results')->insert([
        'id' => (string) Str::uuid(), 'stage_id' => $stageId, 'status' => 'published',
        'published_at' => now(), 'created_at' => now(), 'updated_at' => now(),
    ]);

    try {
        app(RestoreSeasonUseCase::class)->execute($seasonId);
        $this->fail('expected the restore to be refused');
    } catch (SeasonNotRestorableException $e) {
        expect($e->reason)->toBe(SeasonNotRestorableException::HAS_DEPENDENTS);
        expect($e->details)->toContain('stage_results: 1');
    }
});

test('the restore is refused while the season still holds the active slot', function (): void {
    $seasonId = restoreSeasonRow(['is_active' => true]);

    expect(fn () => app(RestoreSeasonUseCase::class)->execute($seasonId))
        ->toThrow(SeasonNotRestorableException::class);
});

test('restoring records SeasonRestored carrying the archival it just erased', function (): void {
    Event::fake([SeasonRestored::class]);

    $admin = restoreAdmin();
    $archiver = restoreAdmin();

    $seasonId = restoreSeasonRow([
        'archived_by_user_id' => $archiver->id,
        'archive_reason' => 'cancelled by mistake',
    ]);

    app(RestoreSeasonUseCase::class)->execute($seasonId, $admin->id);

    // The row's archive columns are null from here on, so anything this
    // event does not carry is gone for good.
    Event::assertDispatched(SeasonRestored::class, function (SeasonRestored $e) use ($admin, $archiver, $seasonId): bool {
        return $e->seasonId === $seasonId
            && $e->byUserId === $admin->id
            && $e->previousArchivedByUserId === $archiver->id
            && $e->previousArchiveReason === 'cancelled by mistake'
            && $e->previousArchivedAt !== null;
    });
});

test('the restored event payload is complete enough to reconstruct the archival', function (): void {
    $admin = restoreAdmin();
    $archiver = restoreAdmin();

    $season = app(RestoreSeasonUseCase::class)->execute(
        restoreSeasonRow(['archived_by_user_id' => $archiver->id, 'archive_reason' => 'wrong button']),
        $admin->id,
    );

    // releaseEvents() was already drained by the Use Case, so assert on a
    // freshly built event rather than the aggregate's queue.
    $payload = (new SeasonRestored(
        seasonId: $season->id,
        occurredAt: '2040-05-01T00:00:00+00:00',
        byUserId: $admin->id,
        previousArchivedAt: '2040-04-01T00:00:00+00:00',
        previousArchivedByUserId: $archiver->id,
        previousArchiveReason: 'wrong button',
    ))->toPayload();

    expect($payload)->toHaveKeys([
        'season_id', 'by_user_id', 'occurred_at',
        'previous_archived_at', 'previous_archived_by_user_id', 'previous_archive_reason',
    ]);
    expect($payload['previous_archive_reason'])->toBe('wrong button');
});

test('a refused restore leaves the season exactly as it was', function (): void {
    $admin = restoreAdmin();
    $seasonId = restoreSeasonRow(['frozen_at' => '2040-01-02 00:00:00', 'archived_by_user_id' => $admin->id]);

    try {
        app(RestoreSeasonUseCase::class)->execute($seasonId);
    } catch (SeasonNotRestorableException) {
        // expected
    }

    $row = SeasonModel::query()->findOrFail($seasonId);
    expect($row->status)->toBe('archived');
    expect($row->archived_at)->not->toBeNull();
    expect($row->archived_by_user_id)->toBe($admin->id);
    expect($row->archive_reason)->toBe('cancelled by mistake');
});

test('POST restore returns the draft season and records who restored it', function (): void {
    $admin = restoreAdmin();
    $seasonId = restoreSeasonRow(['archived_by_user_id' => $admin->id]);

    $this->actingAs($admin)->postJson("/api/v1/admin/seasons/{$seasonId}/restore")
        ->assertStatus(200)
        ->assertJsonPath('data.status', 'draft')
        ->assertJsonPath('data.archived_at', null)
        ->assertJsonPath('data.archive_reason', null)
        ->assertJsonPath('data.is_frozen', false);
});

test('the restore response carries country_ids like every other admin season response', function (): void {
    $admin = restoreAdmin();
    $seasonId = restoreSeasonRow();

    $country = CountryModel::query()->create([
        'id' => (string) Str::uuid(), 'iso_code' => 'JO', 'iso3_code' => 'JOR',
        'phone_code' => '+962', 'is_active' => true,
    ]);
    DB::table('season_countries')->insert(['season_id' => $seasonId, 'country_id' => $country->id]);

    // Restore was written on a branch that predated country_ids, so this
    // pins that it uses the same projection as the rest — reporting an
    // empty set here would look like "no eligible countries" and the next
    // PATCH .../rules would wipe them.
    $this->actingAs($admin)->postJson("/api/v1/admin/seasons/{$seasonId}/restore")
        ->assertStatus(200)
        ->assertJsonPath('data.country_ids', [$country->id]);
});

test('POST restore answers 409 with the blocking reason, not a generic refusal', function (): void {
    $admin = restoreAdmin();
    $frozenId = restoreSeasonRow(['frozen_at' => '2040-01-02 00:00:00']);

    $this->actingAs($admin)->postJson("/api/v1/admin/seasons/{$frozenId}/restore")
        ->assertStatus(409)
        ->assertJsonPath('error.code', SeasonNotRestorableException::FROZEN);

    $withSnapshot = restoreSeasonRow();
    DB::table('season_rule_versions')->insert([
        'id' => (string) Str::uuid(), 'season_id' => $withSnapshot, 'version' => 1,
        'snapshot_json' => json_encode(['version' => 1]), 'created_at' => now(),
    ]);

    $this->actingAs($admin)->postJson("/api/v1/admin/seasons/{$withSnapshot}/restore")
        ->assertStatus(409)
        ->assertJsonPath('error.code', SeasonNotRestorableException::HAS_SNAPSHOTS)
        ->assertJsonPath('error.details', ['season_rule_versions: 1']);
});

test('a restored season can be configured and opened again like any draft', function (): void {
    $admin = restoreAdmin();
    $seasonId = restoreSeasonRow();

    app(RestoreSeasonUseCase::class)->execute($seasonId);

    // The proof that the restore produced a genuine draft rather than a
    // half-reset row: the ordinary editing path accepts it.
    $this->actingAs($admin)->patchJson("/api/v1/admin/seasons/{$seasonId}", [
        'slug' => 'restored-'.Str::random(6), 'year' => 2041,
        'registration_start' => '2041-01-01T00:00:00+00:00',
        'registration_end' => '2041-01-15T00:00:00+00:00',
        'start_date' => '2041-01-16T00:00:00+00:00',
        'end_date' => '2041-03-01T00:00:00+00:00',
        'title_ar' => 'موسم', 'public_name_ar' => 'علني',
        'title_en' => 'Season', 'public_name_en' => 'Public',
        'title_es' => 'Temporada', 'public_name_es' => 'Publico',
    ])->assertStatus(200)->assertJsonPath('data.year', 2041);
});

test('restoring one season does not disturb the season holding the active slot', function (): void {
    $admin = restoreAdmin();

    $activeId = restoreSeasonRow([
        'slug' => 'active-'.Str::random(6), 'year' => 2039,
        'status' => 'registration_open', 'is_active' => true,
        'archived_at' => null, 'archive_reason' => null,
        'frozen_at' => '2039-01-02 00:00:00',
    ]);
    $archivedId = restoreSeasonRow();

    app(RestoreSeasonUseCase::class)->execute($archivedId, $admin->id);

    $active = SeasonModel::query()->findOrFail($activeId);
    expect($active->status)->toBe('registration_open');
    expect((bool) $active->is_active)->toBeTrue();
    expect((bool) SeasonModel::query()->findOrFail($archivedId)->is_active)->toBeFalse();
});
