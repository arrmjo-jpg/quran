<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Contestants\Domain\Events\ContestantUpdated;
use Modules\Core\Domain\ValueObjects\UserType;
use Modules\Core\Infrastructure\ActivityLog\ActivityEventRegistry;
use Modules\Core\Infrastructure\Database\Models\ActivityLogModel;
use Modules\Core\Infrastructure\Database\Models\UserModel;

uses(RefreshDatabase::class)->group('core', 'feature', 'activity-log');

/*
|--------------------------------------------------------------------------
| The activity log — Epic 5 (ADR-017 D3, D4, D5)
|--------------------------------------------------------------------------
|
| This is the platform's first consumer of a domain event. What is worth
| testing is not that a row appears — that is one insert — but the four
| decisions that are easy to get wrong later:
|
|   D3  occurred_at is the event's time, not the write time
|   D4  the actor falls back event -> request -> system, and `system` is a
|       type rather than an invented user
|   D5  correlation_id is captured from the request, never from the event
|   D6  an unregistered event is not logged, and says so
*/

function activityUser(string $type = UserType::ADMIN): UserModel
{
    return UserModel::query()->create([
        'id' => (string) Str::uuid(),
        'email' => 'activity-'.Str::random(8).'@quran.test',
        'name' => 'Activity Subject',
        'type' => $type,
        'password_hash' => password_hash('Pass123!', PASSWORD_BCRYPT),
        'is_active' => true,
    ]);
}

/*
|--------------------------------------------------------------------------
| The row
|--------------------------------------------------------------------------
*/

test('a dispatched domain event becomes one activity row', function (): void {
    $contestantId = (string) Str::uuid();

    event(new ContestantUpdated(
        contestantId: $contestantId,
        changed: ['full_name', 'phone_number'],
        byUserId: null,
        occurredAt: now()->toIso8601String(),
    ));

    $row = ActivityLogModel::query()->firstOrFail();

    expect($row->action)->toBe('contestant_updated');
    expect($row->entity_type)->toBe('contestant');
    expect($row->entity_id)->toBe($contestantId);

    // The payload is the event's own, verbatim.
    expect($row->payload['changed'])->toBe(['full_name', 'phone_number']);
});

test('occurred_at is the event time, not the write time', function (): void {
    // The whole reason both columns exist. A membership entered late happened
    // when it happened, and was recorded today.
    $backdated = now()->subMonths(6)->startOfSecond();

    event(new ContestantUpdated(
        contestantId: (string) Str::uuid(),
        changed: ['full_name'],
        byUserId: null,
        occurredAt: $backdated->toIso8601String(),
    ));

    $row = ActivityLogModel::query()->firstOrFail();

    expect($row->occurred_at->equalTo($backdated))->toBeTrue();
    expect($row->created_at->greaterThan($row->occurred_at))->toBeTrue();
});

test('the payload carries what the event carries and nothing more', function (): void {
    // ContestantUpdated deliberately carries field NAMES and not values, so a
    // national identity document cannot reach an event payload. The log
    // inherits that judgement rather than enriching the row from the database.
    event(new ContestantUpdated(
        contestantId: (string) Str::uuid(),
        changed: ['national_id'],
        byUserId: null,
        occurredAt: now()->toIso8601String(),
    ));

    $row = ActivityLogModel::query()->firstOrFail();

    expect($row->payload['changed'])->toBe(['national_id']);
    expect(json_encode($row->payload))->not->toContain('9990');
    expect(array_keys($row->payload))->toEqualCanonicalizing([
        'contestant_id', 'changed', 'by_user_id', 'occurred_at',
    ]);
});

/*
|--------------------------------------------------------------------------
| The actor — D4
|--------------------------------------------------------------------------
*/

test('the actor comes from the event when the event knows it', function (): void {
    $actor = activityUser();

    event(new ContestantUpdated(
        contestantId: (string) Str::uuid(),
        changed: ['full_name'],
        byUserId: (string) $actor->id,
        occurredAt: now()->toIso8601String(),
    ));

    $row = ActivityLogModel::query()->firstOrFail();

    expect($row->actor_id)->toBe((string) $actor->id);
    expect($row->actor_type)->toBe('user');
});

test('the actor falls back to the authenticated user of the request', function (): void {
    $actor = activityUser();
    $this->actingAs($actor);

    // The event carries no actor; the listener runs inside the request, so it
    // can still say who did it — which is why 27 event contracts did not have
    // to change.
    event(new ContestantUpdated(
        contestantId: (string) Str::uuid(),
        changed: ['full_name'],
        byUserId: null,
        occurredAt: now()->toIso8601String(),
    ));

    $row = ActivityLogModel::query()->firstOrFail();

    expect($row->actor_id)->toBe((string) $actor->id);
    expect($row->actor_type)->toBe('user');
});

test('with no event actor and nobody signed in, the actor is system', function (): void {
    event(new ContestantUpdated(
        contestantId: (string) Str::uuid(),
        changed: ['full_name'],
        byUserId: null,
        occurredAt: now()->toIso8601String(),
    ));

    $row = ActivityLogModel::query()->firstOrFail();

    // Null AND typed. A reader can tell "a job did this" from "we lost track
    // of who", which a bare null could not say.
    expect($row->actor_id)->toBeNull();
    expect($row->actor_type)->toBe('system');
});

test('the event beats the request when both know an actor', function (): void {
    $signedIn = activityUser();
    $onBehalfOf = activityUser();
    $this->actingAs($signedIn);

    event(new ContestantUpdated(
        contestantId: (string) Str::uuid(),
        changed: ['full_name'],
        byUserId: (string) $onBehalfOf->id,
        occurredAt: now()->toIso8601String(),
    ));

    // The event is the more reliable source: it is right even when there is
    // no request at all.
    expect(ActivityLogModel::query()->firstOrFail()->actor_id)->toBe((string) $onBehalfOf->id);
});

/*
|--------------------------------------------------------------------------
| correlation_id — D5
|--------------------------------------------------------------------------
*/

test('correlation_id is null for an event dispatched outside a request', function (): void {
    event(new ContestantUpdated(
        contestantId: (string) Str::uuid(),
        changed: ['full_name'],
        byUserId: null,
        occurredAt: now()->toIso8601String(),
    ));

    // Information, not a gap: a console command has no correlation id because
    // it has no request.
    expect(ActivityLogModel::query()->firstOrFail()->correlation_id)->toBeNull();
});

/*
|--------------------------------------------------------------------------
| What is NOT logged — D6
|--------------------------------------------------------------------------
*/

test('an event the registry does not know is not logged', function (): void {
    // The listener is a wildcard and sees every framework event Laravel
    // dispatches — dozens per request. Only registered ones are activity.
    event('some.framework.event', ['payload']);

    expect(ActivityLogModel::query()->count())->toBe(0);
});

test('the log survives its own failure without breaking the caller', function (): void {
    // Observability is not business logic. AuditLoggingMiddleware makes the
    // same promise, for the same reason.
    DB::statement('DROP TABLE activity_logs');

    event(new ContestantUpdated(
        contestantId: (string) Str::uuid(),
        changed: ['full_name'],
        byUserId: null,
        occurredAt: now()->toIso8601String(),
    ));

    // Reaching this line is the assertion: dispatching did not throw.
    expect(true)->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| The registry is complete — D6
|--------------------------------------------------------------------------
*/

test('every domain event in the platform is registered', function (): void {
    // The guard that makes the explicit list maintainable: adding an event
    // without deciding how it is logged fails here, rather than producing an
    // event that is silently never recorded.
    $onDisk = [];

    foreach (glob(base_path('Modules/*/Domain/Events/*.php')) ?: [] as $file) {
        $code = (string) file_get_contents($file);

        preg_match('/namespace ([^;]+);/', $code, $namespace);
        preg_match('/class (\w+)/', $code, $class);

        if (isset($namespace[1], $class[1])) {
            $onDisk[] = $namespace[1].'\\'.$class[1];
        }
    }

    sort($onDisk);

    $unregistered = array_values(array_diff($onDisk, ActivityEventRegistry::registered()));
    $stale = array_values(array_diff(ActivityEventRegistry::registered(), $onDisk));

    expect($onDisk)->not->toBeEmpty();

    expect($unregistered)->toBeEmpty(
        'Domain events missing from ActivityEventRegistry — decide the entity '
        ."they are about, or they will never be logged:\n  ".implode("\n  ", $unregistered)
    );

    expect($stale)->toBeEmpty(
        'ActivityEventRegistry names events that no longer exist — delete these '
        ."entries:\n  ".implode("\n  ", $stale)
    );
});
