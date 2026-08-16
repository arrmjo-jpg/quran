<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Competition\Application\UseCases\ReopenSeasonRegistrationUseCase;
use Modules\Competition\Domain\Events\SeasonRegistrationReopened;
use Modules\Competition\Domain\Exceptions\SeasonNotReopenableException;
use Modules\Competition\Infrastructure\Database\Models\SeasonModel;
use Modules\Core\Infrastructure\Database\Models\UserModel;

/**
 * Reopening is an administrative correction, not a lifecycle step: the
 * competition's own path runs one way. It is legal only from
 * registration_closed, it never unfreezes the season, and it reclaims the
 * single active slot — which is what most of these tests are about.
 */
uses(RefreshDatabase::class)->group('competition', 'feature', 'season-reopen');

function reopenAdmin(): UserModel
{
    return UserModel::query()->create([
        'id' => (string) Str::uuid(),
        'email' => 'reopen-admin-'.Str::random(8).'@quran.test',
        'name' => 'Reopen Admin',
        'type' => 'admin',
        'password_hash' => password_hash('Pass123!', PASSWORD_BCRYPT),
        'is_active' => true,
    ]);
}

/** @param array<string, mixed> $overrides */
function reopenSeasonRow(array $overrides = []): string
{
    return SeasonModel::query()->create(array_merge([
        'id' => (string) Str::uuid(),
        'slug' => 'reopen-'.Str::random(8),
        'year' => 2045,
        'registration_start' => '2045-01-01 00:00:00',
        'registration_end' => '2045-01-15 00:00:00',
        'start_date' => '2045-01-16 00:00:00',
        'end_date' => '2045-03-01 00:00:00',
        'status' => 'registration_closed',
        'is_active' => false,
        'frozen_at' => '2045-01-02 00:00:00',
    ], $overrides))->id;
}

test('a closed season reopens, becomes active again, and stays frozen', function (): void {
    $admin = reopenAdmin();
    $seasonId = reopenSeasonRow();

    $season = app(ReopenSeasonRegistrationUseCase::class)
        ->execute($seasonId, 'extending the deadline by one week', $admin->id);

    expect($season->getStatus())->toBe('registration_open');
    expect($season->isActive())->toBeTrue();
    // The rules entrants signed up under must not move.
    expect($season->isFrozen())->toBeTrue();

    $row = SeasonModel::query()->findOrFail($seasonId);
    expect($row->status)->toBe('registration_open');
    expect((bool) $row->is_active)->toBeTrue();
    expect($row->frozen_at)->not->toBeNull();
});

test('reopening writes no new rule snapshot', function (): void {
    $seasonId = reopenSeasonRow();

    DB::table('season_rule_versions')->insert([
        'id' => (string) Str::uuid(), 'season_id' => $seasonId, 'version' => 1,
        'snapshot_json' => json_encode(['version' => 1]), 'created_at' => now(),
    ]);

    app(ReopenSeasonRegistrationUseCase::class)->execute($seasonId, 'more time needed');

    // A second version 1 would violate uk_season_rule_versions_season_version;
    // reopening must not attempt one, because the rules did not change.
    expect(DB::table('season_rule_versions')->where('season_id', $seasonId)->count())->toBe(1);
});

test('every status other than registration_closed is refused', function (string $status): void {
    $seasonId = reopenSeasonRow(['status' => $status]);

    expect(fn () => app(ReopenSeasonRegistrationUseCase::class)->execute($seasonId, 'because'))
        ->toThrow(SeasonNotReopenableException::class);

    expect(SeasonModel::query()->findOrFail($seasonId)->status)->toBe($status);
})->with(['draft', 'registration_open', 'competition_running', 'judging', 'completed', 'archived']);

test('a blank reason is refused', function (): void {
    $seasonId = reopenSeasonRow();

    expect(fn () => app(ReopenSeasonRegistrationUseCase::class)->execute($seasonId, '   '))
        ->toThrow(InvalidArgumentException::class);

    expect(SeasonModel::query()->findOrFail($seasonId)->status)->toBe('registration_closed');
});

test('reopening is refused while another season holds the active slot', function (): void {
    $activeId = reopenSeasonRow([
        'slug' => 'active-'.Str::random(6), 'year' => 2044,
        'status' => 'registration_open', 'is_active' => true,
    ]);
    $closedId = reopenSeasonRow();

    try {
        app(ReopenSeasonRegistrationUseCase::class)->execute($closedId, 'extending');
        $this->fail('expected the reopen to be refused');
    } catch (SeasonNotReopenableException $e) {
        expect($e->reason)->toBe(SeasonNotReopenableException::ANOTHER_SEASON_ACTIVE);
        // Identified well enough to act on: the admin has to go and close
        // that season, and a bare UUID means hunting for it first.
        expect($e->activeSeasonId)->toBe($activeId);
        expect($e->activeSeasonSlug)->toBe(SeasonModel::query()->findOrFail($activeId)->slug);
        expect($e->activeSeasonYear)->toBe(2044);
        expect($e->getMessage())->toContain((string) 2044);
    }

    // Neither season moved: the other one keeps running.
    expect(SeasonModel::query()->findOrFail($closedId)->status)->toBe('registration_closed');
    expect((bool) SeasonModel::query()->findOrFail($activeId)->is_active)->toBeTrue();
});

test('the active-slot holder is never silently deactivated to make room', function (): void {
    $activeId = reopenSeasonRow([
        'slug' => 'holder-'.Str::random(6), 'year' => 2044,
        'status' => 'registration_open', 'is_active' => true,
    ]);
    $closedId = reopenSeasonRow();

    try {
        app(ReopenSeasonRegistrationUseCase::class)->execute($closedId, 'extending');
    } catch (SeasonNotReopenableException) {
        // expected
    }

    // openRegistration deactivates the previous holder because that is a
    // season starting its life. Reopening is a correction, so closing a
    // running season's registration to make room would be an action nobody
    // asked for.
    expect((bool) SeasonModel::query()->findOrFail($activeId)->is_active)->toBeTrue();
    expect(SeasonModel::query()->findOrFail($activeId)->status)->toBe('registration_open');
});

test('a season that already holds the slot may still reopen', function (): void {
    // closeRegistration() leaves is_active untouched, so a closed season can
    // still be the active one — that must not block its own reopen.
    $seasonId = reopenSeasonRow(['is_active' => true]);

    $season = app(ReopenSeasonRegistrationUseCase::class)->execute($seasonId, 'reopening the window');

    expect($season->getStatus())->toBe('registration_open');
});

test('reopening records the reason and who did it', function (): void {
    Event::fake([SeasonRegistrationReopened::class]);

    $admin = reopenAdmin();
    $seasonId = reopenSeasonRow();

    app(ReopenSeasonRegistrationUseCase::class)->execute($seasonId, '  deadline extended by a week  ', $admin->id);

    // The row keeps no trace of a reopen — the next close overwrites the
    // status — so this event is the only record it happened.
    Event::assertDispatched(SeasonRegistrationReopened::class, function (SeasonRegistrationReopened $e) use ($admin, $seasonId): bool {
        return $e->seasonId === $seasonId
            && $e->byUserId === $admin->id
            && $e->reason === 'deadline extended by a week';
    });
});

test('POST reopen-registration returns the open season', function (): void {
    $admin = reopenAdmin();
    $seasonId = reopenSeasonRow();

    $this->actingAs($admin)->postJson("/api/v1/admin/seasons/{$seasonId}/reopen-registration", [
        'reason' => 'extending the deadline',
    ])->assertStatus(200)
        ->assertJsonPath('data.status', 'registration_open')
        ->assertJsonPath('data.is_active', true)
        ->assertJsonPath('data.is_frozen', true);
});

test('POST reopen-registration requires a reason', function (): void {
    $admin = reopenAdmin();

    $this->actingAs($admin)->postJson('/api/v1/admin/seasons/'.reopenSeasonRow().'/reopen-registration', [])
        ->assertStatus(422);
});

test('POST reopen-registration answers 409 naming the blocking condition', function (): void {
    $admin = reopenAdmin();

    $this->actingAs($admin)->postJson('/api/v1/admin/seasons/'.reopenSeasonRow(['status' => 'draft']).'/reopen-registration', [
        'reason' => 'trying anyway',
    ])->assertStatus(409)->assertJsonPath('error.code', SeasonNotReopenableException::NOT_CLOSED);

    $activeId = reopenSeasonRow([
        'slug' => 'active-'.Str::random(6), 'year' => 2044,
        'status' => 'registration_open', 'is_active' => true,
    ]);

    $this->actingAs($admin)->postJson('/api/v1/admin/seasons/'.reopenSeasonRow().'/reopen-registration', [
        'reason' => 'trying anyway',
    ])->assertStatus(409)
        ->assertJsonPath('error.code', SeasonNotReopenableException::ANOTHER_SEASON_ACTIVE)
        ->assertJsonPath('error.active_season_id', $activeId)
        ->assertJsonPath('error.active_season_slug', SeasonModel::query()->findOrFail($activeId)->slug)
        ->assertJsonPath('error.active_season_year', 2044);
});

test('a reopened season can be closed again, so the window is genuinely reusable', function (): void {
    $admin = reopenAdmin();
    $seasonId = reopenSeasonRow();

    $this->actingAs($admin)->postJson("/api/v1/admin/seasons/{$seasonId}/reopen-registration", [
        'reason' => 'extending',
    ])->assertStatus(200);

    // Proves the reopen produced a real registration_open state rather than
    // a status string the rest of the lifecycle would choke on.
    $this->actingAs($admin)->postJson("/api/v1/admin/seasons/{$seasonId}/close-registration")
        ->assertStatus(200)
        ->assertJsonPath('data.status', 'registration_closed');
});
