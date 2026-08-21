<?php

declare(strict_types=1);

use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Modules\Core\Domain\ValueObjects\UserType;
use Modules\Core\Infrastructure\Database\Models\UserModel;
use Modules\Core\Infrastructure\Database\Seeders\PermissionsSeeder;
use Modules\Core\Infrastructure\Database\Seeders\RolesSeeder;
use Modules\Organization\Domain\Events\ContestantTransferred;
use Modules\Organization\Domain\Events\MembershipEnded;
use Modules\Organization\Domain\Events\MembershipStarted;
use Modules\Organization\Infrastructure\Database\Models\CenterModel;
use Modules\Organization\Infrastructure\Database\Models\CircleModel;
use Modules\Organization\Infrastructure\Database\Models\ContestantMembershipModel;

uses(RefreshDatabase::class)->group('organization', 'feature', 'memberships');

beforeEach(function (): void {
    (new PermissionsSeeder)->run();
    app(RolesSeeder::class)->run();
});

function membershipAdmin(string $email = 'membership-admin@quran.test'): UserModel
{
    return withSuperAdmin(UserModel::query()->create([
        'id' => (string) Str::uuid(),
        'email' => $email,
        'name' => 'Membership Admin',
        'type' => UserType::ADMIN,
        'password_hash' => password_hash('Pass123!', PASSWORD_BCRYPT),
        'is_active' => true,
    ]));
}

/**
 * The country every centre in this file hangs off.
 *
 * Reused from the database rather than inserted blindly: `iso_code` and
 * `iso3_code` are both UNIQUE, and this helper writes fixed values. It was
 * only ever safe because membershipCircle() held a `static` that stopped it
 * being called twice — and that static was itself the isolation bug fixed
 * below. Removing one without the other trades a stale id for a duplicate key.
 *
 * The lookup is the fix, not a workaround: within a test the row either
 * exists in this transaction or it does not, and RefreshDatabase rolls it
 * back either way. State lives in the database, where the framework can
 * manage it.
 */
function membershipCountry(): string
{
    $existing = DB::table('countries')->value('id');

    if ($existing !== null) {
        return (string) $existing;
    }

    $id = (string) Str::uuid();

    DB::table('countries')->insert([
        'id' => $id,
        'iso_code' => 'JO',
        'iso3_code' => 'JOR',
        'phone_code' => '+962',
        'flag_url' => 'https://example.test/flag.svg',
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return $id;
}

/**
 * A circle to enrol into, created directly: this file is not testing circle
 * creation.
 *
 * THE CENTRE IS LOOKED UP, NOT CACHED IN A `static`. It was cached, and that
 * was a real test-isolation defect rather than a fixture nicety: a PHP static
 * survives for the whole process, while RefreshDatabase rolls its row back
 * after every test. So the first test created a centre, and the other twenty
 * inserted circles pointing at a `center_id` that no longer existed —
 * invisible only because the suite runs with foreign keys disabled.
 *
 * Reading it back from the database each time is what makes the helper honest
 * under FK enforcement, and it is the only `static` that was in any test file
 * in the repository.
 */
function membershipCircle(string $name = 'Morning Circle'): string
{
    $centerId = CenterModel::query()->value('id');

    if ($centerId === null) {
        $centerId = (string) Str::uuid();

        CenterModel::query()->create([
            'id' => $centerId,
            'name' => 'Central Centre',
            'country_id' => membershipCountry(),
            'city' => 'Amman',
            'address' => '12 King Hussein Street',
        ]);
    }

    $id = (string) Str::uuid();

    CircleModel::query()->create([
        'id' => $id,
        'name' => $name,
        'center_id' => (string) $centerId,
    ]);

    return $id;
}

/** A contestant needs a user: contestants.user_id is NOT NULL UNIQUE. */
function membershipContestant(string $email = 'contestant@quran.test'): string
{
    $user = UserModel::query()->create([
        'id' => (string) Str::uuid(),
        'email' => $email,
        'name' => 'Test Contestant',
        'type' => UserType::CONTESTANT,
        'password_hash' => password_hash('Pass123!', PASSWORD_BCRYPT),
        'is_active' => true,
    ]);

    $id = (string) Str::uuid();

    DB::table('contestants')->insert([
        'id' => $id,
        'user_id' => (string) $user->id,
        'country_id' => DB::table('countries')->value('id') ?? membershipCountry(),
        'full_name' => 'Test Contestant',
        'date_of_birth' => '2010-01-01',
        'gender' => 'male',
        'phone_number' => '+962790000000',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return $id;
}

/*
|--------------------------------------------------------------------------
| Enrolling
|--------------------------------------------------------------------------
*/

test('a contestant is enrolled in a circle', function (): void {
    $admin = membershipAdmin();
    $circle = membershipCircle();
    $contestant = membershipContestant();

    $response = $this->actingAs($admin)
        ->postJson('/api/v1/admin/memberships', [
            'contestant_id' => $contestant,
            'circle_id' => $circle,
        ])
        ->assertCreated();

    expect($response->json('data.circle_id'))->toBe($circle)
        ->and($response->json('data.is_active'))->toBeTrue()
        ->and($response->json('data.left_at'))->toBeNull()
        ->and($response->json('data.reason'))->toBeNull();
});

test('enrolling records an event', function (): void {
    $admin = membershipAdmin();
    $circle = membershipCircle();
    $contestant = membershipContestant();

    Event::fake([MembershipStarted::class]);

    $this->actingAs($admin)->postJson('/api/v1/admin/memberships', [
        'contestant_id' => $contestant,
        'circle_id' => $circle,
    ])->assertCreated();

    Event::assertDispatched(MembershipStarted::class);
});

test('enrolment may be backdated but not postdated', function (): void {
    $admin = membershipAdmin();
    $circle = membershipCircle();
    $contestant = membershipContestant();

    $this->actingAs($admin)->postJson('/api/v1/admin/memberships', [
        'contestant_id' => $contestant,
        'circle_id' => $circle,
        'joined_at' => now()->subMonths(3)->toIso8601String(),
    ])->assertCreated();

    $this->actingAs($admin)->postJson('/api/v1/admin/memberships', [
        'contestant_id' => membershipContestant('second@quran.test'),
        'circle_id' => $circle,
        'joined_at' => now()->addDay()->toIso8601String(),
    ])->assertStatus(422);
});

test('a circle that does not exist is refused', function (): void {
    $admin = membershipAdmin();

    $this->actingAs($admin)->postJson('/api/v1/admin/memberships', [
        'contestant_id' => membershipContestant(),
        'circle_id' => (string) Str::uuid(),
    ])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'VALIDATION_ERROR')
        ->assertJsonStructure(['error' => ['fields' => ['circle_id']]]);
});

/*
|--------------------------------------------------------------------------
| G1 — one active membership per contestant
|--------------------------------------------------------------------------
|
| Enforced in StartMembershipUseCase and again by a unique index. The two are
| tested separately on purpose: going through the API can only ever prove
| whichever fires first, so the index gets a test that bypasses the use case
| entirely. This is what Story 2 could NOT do for the centre foreign key —
| that RESTRICT can never fire, because closing a centre is an UPDATE. A second
| open membership is an INSERT, so here both layers are reachable.
*/

test('G1 use case: a second enrolment is refused, and names the circle', function (): void {
    $admin = membershipAdmin();
    $first = membershipCircle('First Circle');
    $second = membershipCircle('Second Circle');
    $contestant = membershipContestant();

    $this->actingAs($admin)->postJson('/api/v1/admin/memberships', [
        'contestant_id' => $contestant,
        'circle_id' => $first,
    ])->assertCreated();

    $message = $this->actingAs($admin)->postJson('/api/v1/admin/memberships', [
        'contestant_id' => $contestant,
        'circle_id' => $second,
    ])
        ->assertStatus(409)
        ->assertJsonPath('error.code', 'ALREADY_ENROLLED')
        ->json('error.message');

    // The circle they are already in has to be in the message: without it the
    // operator cannot tell whether to transfer or to abandon the enrolment.
    expect($message)->toContain($first);
});

test('G1 index: a second open membership is refused below the use case', function (): void {
    // Deliberately bypasses StartMembershipUseCase and writes through the
    // model, which is the only way to reach the index. If this test ever
    // passes because the use case caught it, it is testing the wrong layer.
    $circle = membershipCircle();
    $contestant = membershipContestant();

    ContestantMembershipModel::query()->create([
        'id' => (string) Str::uuid(),
        'contestant_id' => $contestant,
        'circle_id' => $circle,
        'joined_at' => now(),
    ]);

    expect(fn () => ContestantMembershipModel::query()->create([
        'id' => (string) Str::uuid(),
        'contestant_id' => $contestant,
        'circle_id' => membershipCircle('Another Circle'),
        'joined_at' => now(),
    ]))->toThrow(UniqueConstraintViolationException::class);
});

test('G1 index: closed memberships do not count, so a contestant may rejoin', function (): void {
    // The other half of the same index: scoping uniqueness to open rows must
    // not make a contestant's history block their future.
    $admin = membershipAdmin();
    $circle = membershipCircle();
    $contestant = membershipContestant();

    $id = $this->actingAs($admin)->postJson('/api/v1/admin/memberships', [
        'contestant_id' => $contestant,
        'circle_id' => $circle,
    ])->json('data.id');

    $this->actingAs($admin)->postJson("/api/v1/admin/memberships/{$id}/end", [
        'reason' => 'Moved away for a term.',
    ])->assertOk();

    $this->actingAs($admin)->postJson('/api/v1/admin/memberships', [
        'contestant_id' => $contestant,
        'circle_id' => $circle,
    ])->assertCreated();

    expect(ContestantMembershipModel::query()->where('contestant_id', $contestant)->count())->toBe(2);
});

/*
|--------------------------------------------------------------------------
| Ending
|--------------------------------------------------------------------------
*/

test('ending a membership keeps the row', function (): void {
    $admin = membershipAdmin();
    $circle = membershipCircle();
    $contestant = membershipContestant();

    $id = $this->actingAs($admin)->postJson('/api/v1/admin/memberships', [
        'contestant_id' => $contestant,
        'circle_id' => $circle,
    ])->json('data.id');

    $response = $this->actingAs($admin)->postJson("/api/v1/admin/memberships/{$id}/end", [
        'reason' => 'Completed the programme.',
    ])->assertOk();

    expect($response->json('data.is_active'))->toBeFalse()
        ->and($response->json('data.left_at'))->not->toBeNull()
        ->and($response->json('data.reason'))->toBe('Completed the programme.');

    // Historical, not deleted: Q4's whole point.
    expect(ContestantMembershipModel::query()->find($id))->not->toBeNull();
});

test('a reason is required to end a membership', function (): void {
    $admin = membershipAdmin();
    $circle = membershipCircle();
    $contestant = membershipContestant();

    $id = $this->actingAs($admin)->postJson('/api/v1/admin/memberships', [
        'contestant_id' => $contestant,
        'circle_id' => $circle,
    ])->json('data.id');

    $this->actingAs($admin)->postJson("/api/v1/admin/memberships/{$id}/end", [])
        ->assertStatus(422)
        ->assertJsonStructure(['error' => ['fields' => ['reason']]]);
});

test('a membership cannot be ended twice', function (): void {
    $admin = membershipAdmin();
    $circle = membershipCircle();
    $contestant = membershipContestant();

    $id = $this->actingAs($admin)->postJson('/api/v1/admin/memberships', [
        'contestant_id' => $contestant,
        'circle_id' => $circle,
    ])->json('data.id');

    $this->actingAs($admin)->postJson("/api/v1/admin/memberships/{$id}/end", [
        'reason' => 'First ending.',
    ])->assertOk();

    $this->actingAs($admin)->postJson("/api/v1/admin/memberships/{$id}/end", [
        'reason' => 'Second ending.',
    ])
        ->assertStatus(409)
        ->assertJsonPath('error.code', 'MEMBERSHIP_ALREADY_ENDED');
});

test('a membership cannot end before it began', function (): void {
    $admin = membershipAdmin();
    $circle = membershipCircle();
    $contestant = membershipContestant();

    $id = $this->actingAs($admin)->postJson('/api/v1/admin/memberships', [
        'contestant_id' => $contestant,
        'circle_id' => $circle,
        'joined_at' => now()->subDays(2)->toIso8601String(),
    ])->json('data.id');

    $this->actingAs($admin)->postJson("/api/v1/admin/memberships/{$id}/end", [
        'reason' => 'Backwards.',
        'left_at' => now()->subDays(5)->toIso8601String(),
    ])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'INVALID_MEMBERSHIP');
});

test('ending records an event carrying the reason', function (): void {
    $admin = membershipAdmin();
    $circle = membershipCircle();
    $contestant = membershipContestant();

    $id = $this->actingAs($admin)->postJson('/api/v1/admin/memberships', [
        'contestant_id' => $contestant,
        'circle_id' => $circle,
    ])->json('data.id');

    Event::fake([MembershipEnded::class]);

    $this->actingAs($admin)->postJson("/api/v1/admin/memberships/{$id}/end", [
        'reason' => 'Completed the programme.',
    ])->assertOk();

    Event::assertDispatched(
        MembershipEnded::class,
        fn (MembershipEnded $e): bool => $e->reason === 'Completed the programme.'
    );
});

/*
|--------------------------------------------------------------------------
| Transferring
|--------------------------------------------------------------------------
*/

test('a transfer closes one membership and opens another', function (): void {
    $admin = membershipAdmin();
    $from = membershipCircle('From Circle');
    $to = membershipCircle('To Circle');
    $contestant = membershipContestant();

    $this->actingAs($admin)->postJson('/api/v1/admin/memberships', [
        'contestant_id' => $contestant,
        'circle_id' => $from,
    ])->assertCreated();

    $response = $this->actingAs($admin)->postJson('/api/v1/admin/memberships/transfer', [
        'contestant_id' => $contestant,
        'to_circle_id' => $to,
        'reason' => 'Moved to the advanced circle.',
    ])->assertCreated();

    expect($response->json('data.circle_id'))->toBe($to)
        ->and($response->json('data.is_active'))->toBeTrue();

    $rows = ContestantMembershipModel::query()->where('contestant_id', $contestant)->get();

    expect($rows)->toHaveCount(2)
        ->and($rows->whereNull('left_at')->count())->toBe(1)
        ->and($rows->firstWhere('circle_id', $from)->reason)->toBe('Moved to the advanced circle.');
});

test('a transfer records the pair as one act', function (): void {
    $admin = membershipAdmin();
    $from = membershipCircle('From Circle');
    $to = membershipCircle('To Circle');
    $contestant = membershipContestant();

    $this->actingAs($admin)->postJson('/api/v1/admin/memberships', [
        'contestant_id' => $contestant,
        'circle_id' => $from,
    ])->assertCreated();

    Event::fake([ContestantTransferred::class]);

    $this->actingAs($admin)->postJson('/api/v1/admin/memberships/transfer', [
        'contestant_id' => $contestant,
        'to_circle_id' => $to,
        'reason' => 'Moved.',
    ])->assertCreated();

    // Without this event a transfer is indistinguishable from a departure
    // followed later by an unrelated enrolment.
    Event::assertDispatched(
        ContestantTransferred::class,
        fn (ContestantTransferred $e): bool => $e->fromCircleId === $from && $e->toCircleId === $to
    );
});

test('transferring a contestant with no membership is refused', function (): void {
    $admin = membershipAdmin();

    $this->actingAs($admin)->postJson('/api/v1/admin/memberships/transfer', [
        'contestant_id' => membershipContestant(),
        'to_circle_id' => membershipCircle(),
        'reason' => 'Nowhere to move from.',
    ])
        ->assertStatus(409)
        ->assertJsonPath('error.code', 'TRANSFER_REFUSED');
});

test('transferring into the same circle is refused', function (): void {
    $admin = membershipAdmin();
    $circle = membershipCircle();
    $contestant = membershipContestant();

    $this->actingAs($admin)->postJson('/api/v1/admin/memberships', [
        'contestant_id' => $contestant,
        'circle_id' => $circle,
    ])->assertCreated();

    $this->actingAs($admin)->postJson('/api/v1/admin/memberships/transfer', [
        'contestant_id' => $contestant,
        'to_circle_id' => $circle,
        'reason' => 'Nowhere to go.',
    ])
        ->assertStatus(409)
        ->assertJsonPath('error.code', 'TRANSFER_REFUSED');
});

/*
|--------------------------------------------------------------------------
| G2 — the guard Story 2 deferred to this story
|--------------------------------------------------------------------------
|
| DeleteCircleUseCase shipped without a membership guard because memberships
| did not exist and nothing could have made the check fail. These are the tests
| that could not be written then.
*/

test('a circle with enrolled contestants cannot be closed', function (): void {
    $admin = membershipAdmin();
    $circle = membershipCircle();

    $this->actingAs($admin)->postJson('/api/v1/admin/memberships', [
        'contestant_id' => membershipContestant(),
        'circle_id' => $circle,
    ])->assertCreated();

    $this->actingAs($admin)->deleteJson("/api/v1/admin/circles/{$circle}")
        ->assertStatus(409)
        ->assertJsonPath('error.code', 'CIRCLE_HAS_MEMBERS');

    expect(CircleModel::query()->find($circle))->not->toBeNull();
});

test('the refusal names how many contestants are in the way', function (): void {
    $admin = membershipAdmin();
    $circle = membershipCircle();

    foreach (['a@quran.test', 'b@quran.test'] as $email) {
        $this->actingAs($admin)->postJson('/api/v1/admin/memberships', [
            'contestant_id' => membershipContestant($email),
            'circle_id' => $circle,
        ])->assertCreated();
    }

    $message = $this->actingAs($admin)->deleteJson("/api/v1/admin/circles/{$circle}")
        ->assertStatus(409)
        ->json('error.message');

    expect($message)->toContain('2');
});

test('a circle whose contestants have all left can be closed', function (): void {
    // Only OPEN memberships hold a circle. Its history stays, which is what
    // soft deletion is for — otherwise no circle could ever be closed once
    // anyone had passed through it.
    $admin = membershipAdmin();
    $circle = membershipCircle();

    $id = $this->actingAs($admin)->postJson('/api/v1/admin/memberships', [
        'contestant_id' => membershipContestant(),
        'circle_id' => $circle,
    ])->json('data.id');

    $this->actingAs($admin)->deleteJson("/api/v1/admin/circles/{$circle}")->assertStatus(409);

    $this->actingAs($admin)->postJson("/api/v1/admin/memberships/{$id}/end", [
        'reason' => 'Left the programme.',
    ])->assertOk();

    $this->actingAs($admin)->deleteJson("/api/v1/admin/circles/{$circle}")->assertOk();

    // The membership survives the circle being closed: it is history, and D8
    // freezes circle names onto applications that will still join against it.
    expect(ContestantMembershipModel::query()->find($id))->not->toBeNull();
});

/*
|--------------------------------------------------------------------------
| Listing
|--------------------------------------------------------------------------
*/

test('a circle roster and a contestant history are both readable', function (): void {
    $admin = membershipAdmin();
    $from = membershipCircle('From Circle');
    $to = membershipCircle('To Circle');
    $contestant = membershipContestant();

    $this->actingAs($admin)->postJson('/api/v1/admin/memberships', [
        'contestant_id' => $contestant,
        'circle_id' => $from,
    ])->assertCreated();

    $this->actingAs($admin)->postJson('/api/v1/admin/memberships/transfer', [
        'contestant_id' => $contestant,
        'to_circle_id' => $to,
        'reason' => 'Moved.',
    ])->assertCreated();

    // History: every period, closed ones included. That is the default, since
    // a history that hides its closed rows is a history nobody sees.
    $history = $this->actingAs($admin)
        ->getJson("/api/v1/admin/memberships?contestant_id={$contestant}")
        ->assertOk();

    expect($history->json('meta.pagination.total'))->toBe(2);

    // Roster: who is in the old circle now. Nobody.
    $roster = $this->actingAs($admin)
        ->getJson("/api/v1/admin/memberships?circle_id={$from}&active=1")
        ->assertOk();

    expect($roster->json('meta.pagination.total'))->toBe(0);
});

/*
|--------------------------------------------------------------------------
| Authorisation
|--------------------------------------------------------------------------
*/

test('each membership action needs its own permission', function (): void {
    $plain = UserModel::query()->create([
        'id' => (string) Str::uuid(),
        'email' => 'nopermissions@quran.test',
        'name' => 'No Permissions',
        'type' => UserType::ADMIN,
        'password_hash' => password_hash('Pass123!', PASSWORD_BCRYPT),
        'is_active' => true,
    ]);

    $circle = membershipCircle();
    $contestant = membershipContestant();

    $this->actingAs($plain)->getJson('/api/v1/admin/memberships')->assertForbidden();

    $this->actingAs($plain)->postJson('/api/v1/admin/memberships', [
        'contestant_id' => $contestant,
        'circle_id' => $circle,
    ])->assertForbidden();

    $this->actingAs($plain)->postJson('/api/v1/admin/memberships/transfer', [
        'contestant_id' => $contestant,
        'to_circle_id' => $circle,
        'reason' => 'Nope.',
    ])->assertForbidden();
});
