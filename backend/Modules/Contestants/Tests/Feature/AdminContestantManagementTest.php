<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Modules\Contestants\Application\UseCases\CreateContestantUseCase;
use Modules\Contestants\Domain\Events\ContestantCreated;
use Modules\Contestants\Domain\Events\ContestantDeleted;
use Modules\Contestants\Domain\Events\ContestantRestored;
use Modules\Contestants\Domain\Events\ContestantUpdated;
use Modules\Contestants\Domain\Exceptions\UserAlreadyHasContestantException;
use Modules\Core\Domain\Repositories\RoleRepositoryContract;
use Modules\Core\Domain\ValueObjects\UserId;
use Modules\Core\Domain\ValueObjects\UserType;
use Modules\Core\Infrastructure\Database\Models\UserModel;
use Modules\Core\Infrastructure\Database\Seeders\PermissionsSeeder;
use Modules\Core\Infrastructure\Database\Seeders\RolesSeeder;
use Modules\Core\Infrastructure\Permissions\EffectivePermissionResolver;

uses(RefreshDatabase::class)->group('contestants', 'feature', 'identity');

/*
|--------------------------------------------------------------------------
| Admin contestant management — Epic 4 Story 1 (ADR-016 D15, D17)
|--------------------------------------------------------------------------
*/

beforeEach(function (): void {
    (new PermissionsSeeder)->run();
    app(RolesSeeder::class)->run();
});

function mgmtUser(string $type = UserType::ADMIN): UserModel
{
    return UserModel::query()->create([
        'id' => (string) Str::uuid(),
        'email' => 'mgmt-'.Str::random(10).'@quran.test',
        'name' => 'Management Test',
        'type' => $type,
        'password_hash' => password_hash('Pass123!', PASSWORD_BCRYPT),
        'is_active' => true,
    ]);
}

/** An admin holding exactly one seeded role. */
function mgmtAs(string $roleName): UserModel
{
    $user = mgmtUser();
    $role = app(RoleRepositoryContract::class)->findByName($roleName);

    DB::table('role_user')->insert(['role_id' => $role->id->value, 'user_id' => (string) $user->id]);

    app(EffectivePermissionResolver::class)
        ->forget(new UserId((string) $user->id));

    return $user->fresh();
}

function mgmtCountry(): string
{
    $existing = DB::table('countries')->value('id');

    if ($existing !== null) {
        return (string) $existing;
    }

    $id = (string) Str::uuid();
    DB::table('countries')->insert([
        'id' => $id, 'iso_code' => 'JO', 'iso3_code' => 'JOR', 'phone_code' => '+962',
        'flag_url' => 'https://example.test/flag.svg', 'is_active' => true,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    return $id;
}

/** @return array{0: string, 1: string} contestant id, user id */
function mgmtContestant(array $overrides = []): array
{
    $account = mgmtUser(UserType::CONTESTANT);
    $id = (string) Str::uuid();

    DB::table('contestants')->insert(array_merge([
        'id' => $id,
        'user_id' => (string) $account->id,
        'country_id' => mgmtCountry(),
        'full_name' => 'Existing Contestant',
        'date_of_birth' => '1999-01-01',
        'gender' => 'male',
        'national_id' => '9990000001',
        'phone_number' => '+962790000000',
        'created_at' => now(),
        'updated_at' => now(),
    ], $overrides));

    return [$id, (string) $account->id];
}

function mgmtPayload(array $overrides = []): array
{
    return array_merge([
        'user_id' => (string) mgmtUser(UserType::CONTESTANT)->id,
        'country_id' => mgmtCountry(),
        'full_name' => 'Khalid Al-Fahad',
        'date_of_birth' => '1998-06-15',
        'gender' => 'male',
        'phone_number' => '+962791234567',
        'national_id' => '9981234567',
    ], $overrides);
}

/*
|--------------------------------------------------------------------------
| Authorization — ADR-016 D17
|--------------------------------------------------------------------------
*/

test('super_admin may list, create, update, delete and restore', function (): void {
    $admin = mgmtAs('super_admin');
    [$id] = mgmtContestant();

    $this->actingAs($admin)->getJson('/api/v1/admin/contestants')->assertStatus(200);
    $this->actingAs($admin)->postJson('/api/v1/admin/contestants', mgmtPayload())->assertStatus(201);
    $this->actingAs($admin)->patchJson("/api/v1/admin/contestants/{$id}", ['full_name' => 'New Name'])->assertStatus(200);
    $this->actingAs($admin)->deleteJson("/api/v1/admin/contestants/{$id}")->assertStatus(200);
    $this->actingAs($admin)->postJson("/api/v1/admin/contestants/{$id}/restore")->assertStatus(200);
});

test('data_entry may list, create and update', function (): void {
    $admin = mgmtAs('data_entry');
    [$id] = mgmtContestant();

    $this->actingAs($admin)->getJson('/api/v1/admin/contestants')->assertStatus(200);
    $this->actingAs($admin)->postJson('/api/v1/admin/contestants', mgmtPayload())->assertStatus(201);
    $this->actingAs($admin)->patchJson("/api/v1/admin/contestants/{$id}", ['full_name' => 'Edited'])->assertStatus(200);
});

test('data_entry may NOT delete or restore — D17', function (): void {
    // The line D17 draws: entering and correcting records is the role's
    // job; removing them is not.
    $admin = mgmtAs('data_entry');
    [$id] = mgmtContestant();

    $this->actingAs($admin)->deleteJson("/api/v1/admin/contestants/{$id}")->assertStatus(403);
    $this->actingAs($admin)->postJson("/api/v1/admin/contestants/{$id}/restore")->assertStatus(403);

    expect(DB::table('contestants')->where('id', $id)->whereNull('deleted_at')->exists())->toBeTrue();
});

test('competition_manager may view but not write', function (): void {
    $admin = mgmtAs('competition_manager');
    [$id] = mgmtContestant();

    $this->actingAs($admin)->getJson('/api/v1/admin/contestants')->assertStatus(200);
    $this->actingAs($admin)->postJson('/api/v1/admin/contestants', mgmtPayload())->assertStatus(403);
    $this->actingAs($admin)->patchJson("/api/v1/admin/contestants/{$id}", ['full_name' => 'x'])->assertStatus(403);
    $this->actingAs($admin)->deleteJson("/api/v1/admin/contestants/{$id}")->assertStatus(403);
});

test('judge, evaluator and moderator see no contestants at all', function (string $roleName): void {
    $admin = mgmtAs($roleName);

    $this->actingAs($admin)->getJson('/api/v1/admin/contestants')->assertStatus(403);
})->with(['judge', 'evaluator', 'moderator']);

test('D11 holds: no seeded role other than these four can read contestants', function (): void {
    // D11 defers row-level security to Epic 14 and withholds contestant
    // visibility from supervisors until then. No supervisor role is seeded,
    // and this asserts that Story 1 did not quietly create one or widen
    // visibility to a role that had none.
    $roles = app(RoleRepositoryContract::class)->all();
    $names = array_map(static fn ($r): string => $r->getName(), $roles);

    expect($names)->not->toContain('supervisor');

    $withView = array_values(array_filter(
        $names,
        static fn (string $n): bool => in_array(
            'contestants.view',
            app(RoleRepositoryContract::class)->findByName($n)->getPermissionNames(),
            true
        )
    ));

    sort($withView);
    expect($withView)->toBe(['competition_manager', 'data_entry', 'super_admin']);
});

/*
|--------------------------------------------------------------------------
| Create
|--------------------------------------------------------------------------
*/

test('creating a contestant persists it and returns 201 with the detail shape', function (): void {
    Event::fake();
    $admin = mgmtAs('super_admin');
    $payload = mgmtPayload();

    $response = $this->actingAs($admin)->postJson('/api/v1/admin/contestants', $payload);

    $response->assertStatus(201)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.full_name', 'Khalid Al-Fahad')
        ->assertJsonPath('data.user_id', $payload['user_id'])
        ->assertJsonPath('data.national_id', '9981234567')
        ->assertJsonPath('data.is_deleted', false);

    expect(DB::table('contestants')->where('user_id', $payload['user_id'])->exists())->toBeTrue();

    Event::assertDispatched(ContestantCreated::class);
});

test('creating trims whitespace from the name and phone', function (): void {
    $admin = mgmtAs('super_admin');

    $response = $this->actingAs($admin)->postJson('/api/v1/admin/contestants', mgmtPayload([
        'full_name' => '  Spaced Name  ',
        'phone_number' => '  +962790000009  ',
    ]));

    $response->assertStatus(201)
        ->assertJsonPath('data.full_name', 'Spaced Name')
        ->assertJsonPath('data.phone_number', '+962790000009');
});

test('creating rejects a user that does not exist', function (): void {
    $admin = mgmtAs('super_admin');

    $this->actingAs($admin)
        ->postJson('/api/v1/admin/contestants', mgmtPayload(['user_id' => (string) Str::uuid()]))
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'VALIDATION_ERROR')
        ->assertJsonStructure(['error' => ['fields' => ['user_id']]]);
});

test('creating rejects a country that does not exist', function (): void {
    $admin = mgmtAs('super_admin');

    $this->actingAs($admin)
        ->postJson('/api/v1/admin/contestants', mgmtPayload(['country_id' => (string) Str::uuid()]))
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'VALIDATION_ERROR')
        ->assertJsonStructure(['error' => ['fields' => ['country_id']]]);
});

test('creating rejects a user that already has a contestant', function (): void {
    $admin = mgmtAs('super_admin');
    [, $userId] = mgmtContestant();

    $this->actingAs($admin)
        ->postJson('/api/v1/admin/contestants', mgmtPayload(['user_id' => $userId]))
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'VALIDATION_ERROR')
        ->assertJsonStructure(['error' => ['fields' => ['user_id']]]);
});

test('creating rejects a user whose contestant was SOFT DELETED — never a 500', function (): void {
    // contestants.user_id is UNIQUE and the index does not exclude deleted
    // rows, so a second insert would hit the constraint and surface as a
    // 500. It does not, because Laravel's `unique` rule queries the table
    // directly and therefore DOES see soft-deleted rows — measured, not
    // assumed; this test was first written expecting the use case to catch
    // it with a 409 and the run showed validation gets there first.
    //
    // 422 with a field error is the better answer anyway: it points a form
    // at the offending input. The use case's own guard remains as
    // defence-in-depth for callers that bypass the request — proved
    // directly in the test below rather than left unexercised.
    $admin = mgmtAs('super_admin');
    [$id, $userId] = mgmtContestant();

    $this->actingAs($admin)->deleteJson("/api/v1/admin/contestants/{$id}")->assertStatus(200);

    $this->actingAs($admin)
        ->postJson('/api/v1/admin/contestants', mgmtPayload(['user_id' => $userId]))
        ->assertStatus(422)
        ->assertJsonStructure(['error' => ['fields' => ['user_id']]]);
});

test('the use case refuses a duplicate even when the request layer is bypassed', function (): void {
    // Seeders, console commands and future callers do not pass through the
    // FormRequest. Without this guard they would meet a constraint
    // violation instead of a domain refusal.
    [, $userId] = mgmtContestant();

    expect(fn () => app(CreateContestantUseCase::class)->execute(
        userId: $userId,
        countryId: mgmtCountry(),
        fullName: 'Duplicate Person',
        dateOfBirth: '1998-06-15',
        gender: 'male',
        phoneNumber: '+962790000001',
    ))->toThrow(UserAlreadyHasContestantException::class);
});

test('creating requires the mandatory fields', function (): void {
    $admin = mgmtAs('super_admin');

    $this->actingAs($admin)
        ->postJson('/api/v1/admin/contestants', [])
        ->assertStatus(422)
        ->assertJsonStructure(['error' => ['fields' => ['user_id', 'country_id', 'full_name', 'date_of_birth', 'gender', 'phone_number']]]);
});

test('creating rejects a bad gender, a bad date and a future birth date', function (): void {
    $admin = mgmtAs('super_admin');

    $this->actingAs($admin)->postJson('/api/v1/admin/contestants', mgmtPayload(['gender' => 'other']))
        ->assertStatus(422)->assertJsonStructure(['error' => ['fields' => ['gender']]]);

    $this->actingAs($admin)->postJson('/api/v1/admin/contestants', mgmtPayload(['date_of_birth' => '15-06-1998']))
        ->assertStatus(422)->assertJsonStructure(['error' => ['fields' => ['date_of_birth']]]);

    $this->actingAs($admin)->postJson('/api/v1/admin/contestants', mgmtPayload(['date_of_birth' => now()->addYear()->format('Y-m-d')]))
        ->assertStatus(422)->assertJsonStructure(['error' => ['fields' => ['date_of_birth']]]);
});

test('national_id is optional', function (): void {
    $admin = mgmtAs('super_admin');
    $payload = mgmtPayload();
    unset($payload['national_id']);

    $this->actingAs($admin)->postJson('/api/v1/admin/contestants', $payload)
        ->assertStatus(201)
        ->assertJsonPath('data.national_id', null);
});

test('a failed creation writes nothing', function (): void {
    $admin = mgmtAs('super_admin');
    $before = DB::table('contestants')->count();

    $this->actingAs($admin)->postJson('/api/v1/admin/contestants', mgmtPayload(['gender' => 'nope']))
        ->assertStatus(422);

    expect(DB::table('contestants')->count())->toBe($before);
});

/*
|--------------------------------------------------------------------------
| Update
|--------------------------------------------------------------------------
*/

test('updating changes the given fields and dispatches the delta', function (): void {
    Event::fake();
    $admin = mgmtAs('super_admin');
    [$id] = mgmtContestant();

    $this->actingAs($admin)
        ->patchJson("/api/v1/admin/contestants/{$id}", ['full_name' => 'Renamed', 'phone_number' => '+962799999999'])
        ->assertStatus(200)
        ->assertJsonPath('data.full_name', 'Renamed')
        ->assertJsonPath('data.phone_number', '+962799999999');

    Event::assertDispatched(ContestantUpdated::class, function (ContestantUpdated $e): bool {
        $changed = $e->changed;
        sort($changed);

        return $changed === ['full_name', 'phone_number'];
    });
});

test('a partial update leaves omitted fields alone', function (): void {
    $admin = mgmtAs('super_admin');
    [$id] = mgmtContestant(['full_name' => 'Original', 'phone_number' => '+962700000000']);

    $this->actingAs($admin)->patchJson("/api/v1/admin/contestants/{$id}", ['full_name' => 'Changed'])
        ->assertStatus(200)
        ->assertJsonPath('data.full_name', 'Changed')
        ->assertJsonPath('data.phone_number', '+962700000000')
        ->assertJsonPath('data.national_id', '9990000001');
});

test('an update that changes nothing dispatches nothing', function (): void {
    Event::fake();
    $admin = mgmtAs('super_admin');
    [$id] = mgmtContestant(['full_name' => 'Same Name']);

    $this->actingAs($admin)->patchJson("/api/v1/admin/contestants/{$id}", ['full_name' => 'Same Name'])
        ->assertStatus(200);

    Event::assertNotDispatched(ContestantUpdated::class);
});

test('user_id and country_id are not editable', function (): void {
    // Re-pointing a contestant at another account would move a person's
    // whole history; country is frozen onto their applications (D8).
    $admin = mgmtAs('super_admin');
    [$id, $userId] = mgmtContestant();
    $otherUser = mgmtUser(UserType::CONTESTANT);

    $this->actingAs($admin)->patchJson("/api/v1/admin/contestants/{$id}", [
        'user_id' => (string) $otherUser->id,
        'country_id' => (string) Str::uuid(),
        'full_name' => 'Only This Changes',
    ])->assertStatus(200)->assertJsonPath('data.user_id', $userId);

    expect(DB::table('contestants')->where('id', $id)->value('user_id'))->toBe($userId);
});

test('updating rejects invalid values with 422, never 500', function (): void {
    $admin = mgmtAs('super_admin');
    [$id] = mgmtContestant();

    $this->actingAs($admin)->patchJson("/api/v1/admin/contestants/{$id}", ['gender' => 'other'])
        ->assertStatus(422)->assertJsonStructure(['error' => ['fields' => ['gender']]]);

    $this->actingAs($admin)->patchJson("/api/v1/admin/contestants/{$id}", ['date_of_birth' => 'yesterday'])
        ->assertStatus(422)->assertJsonStructure(['error' => ['fields' => ['date_of_birth']]]);

    $this->actingAs($admin)->patchJson("/api/v1/admin/contestants/{$id}", ['full_name' => 'x'])
        ->assertStatus(422)->assertJsonStructure(['error' => ['fields' => ['full_name']]]);
});

test('updating a malformed id is 422 and a missing one is 404', function (): void {
    $admin = mgmtAs('super_admin');

    $this->actingAs($admin)->patchJson('/api/v1/admin/contestants/not-a-uuid', ['full_name' => 'Valid Name'])
        ->assertStatus(422)->assertJsonPath('error.code', 'INVALID_CONTESTANT_ID');

    $this->actingAs($admin)->patchJson('/api/v1/admin/contestants/'.Str::uuid(), ['full_name' => 'Valid Name'])
        ->assertStatus(404);
});

test('national_id can be cleared explicitly', function (): void {
    $admin = mgmtAs('super_admin');
    [$id] = mgmtContestant();

    $this->actingAs($admin)->patchJson("/api/v1/admin/contestants/{$id}", ['national_id' => null])
        ->assertStatus(200)
        ->assertJsonPath('data.national_id', null);
});

/*
|--------------------------------------------------------------------------
| Delete and restore
|--------------------------------------------------------------------------
*/

test('deleting is soft and removes the row from the ordinary list', function (): void {
    Event::fake();
    $admin = mgmtAs('super_admin');
    [$id] = mgmtContestant();

    $this->actingAs($admin)->deleteJson("/api/v1/admin/contestants/{$id}")->assertStatus(200);

    expect(DB::table('contestants')->where('id', $id)->exists())->toBeTrue();
    expect(DB::table('contestants')->where('id', $id)->whereNotNull('deleted_at')->exists())->toBeTrue();

    expect($this->actingAs($admin)->getJson('/api/v1/admin/contestants')->json('data'))->toBe([]);
    $this->actingAs($admin)->getJson("/api/v1/admin/contestants/{$id}")->assertStatus(404);

    Event::assertDispatched(ContestantDeleted::class);
});

test('with_deleted brings deleted rows back into the list, flagged', function (): void {
    $admin = mgmtAs('super_admin');
    [$id] = mgmtContestant();

    $this->actingAs($admin)->deleteJson("/api/v1/admin/contestants/{$id}");

    $rows = $this->actingAs($admin)->getJson('/api/v1/admin/contestants?with_deleted=1')->json('data');

    expect($rows)->toHaveCount(1);
    expect($rows[0]['is_deleted'])->toBeTrue();
});

test('deleting twice is a no-op the second time and records one event', function (): void {
    Event::fake();
    $admin = mgmtAs('super_admin');
    [$id] = mgmtContestant();

    $this->actingAs($admin)->deleteJson("/api/v1/admin/contestants/{$id}")->assertStatus(200);
    $this->actingAs($admin)->deleteJson("/api/v1/admin/contestants/{$id}")->assertStatus(200);

    Event::assertDispatchedTimes(ContestantDeleted::class, 1);
});

test('restoring brings the contestant back', function (): void {
    Event::fake();
    $admin = mgmtAs('super_admin');
    [$id] = mgmtContestant();

    $this->actingAs($admin)->deleteJson("/api/v1/admin/contestants/{$id}");
    $this->actingAs($admin)->postJson("/api/v1/admin/contestants/{$id}/restore")
        ->assertStatus(200)
        ->assertJsonPath('data.is_deleted', false);

    $this->actingAs($admin)->getJson("/api/v1/admin/contestants/{$id}")->assertStatus(200);
    expect($this->actingAs($admin)->getJson('/api/v1/admin/contestants')->json('data'))->toHaveCount(1);

    Event::assertDispatched(ContestantRestored::class);
});

test('restoring a live contestant is a no-op that records nothing', function (): void {
    Event::fake();
    $admin = mgmtAs('super_admin');
    [$id] = mgmtContestant();

    $this->actingAs($admin)->postJson("/api/v1/admin/contestants/{$id}/restore")->assertStatus(200);

    Event::assertNotDispatched(ContestantRestored::class);
});

test('restoring a contestant that never existed is 404', function (): void {
    $admin = mgmtAs('super_admin');

    $this->actingAs($admin)->postJson('/api/v1/admin/contestants/'.Str::uuid().'/restore')->assertStatus(404);
});

test('a restored account can be given a contestant again only after restore', function (): void {
    // The pair of rules working together: while deleted, the account is
    // still taken (409); once restored, the original record is back rather
    // than a second one being created.
    $admin = mgmtAs('super_admin');
    [$id, $userId] = mgmtContestant();

    $this->actingAs($admin)->deleteJson("/api/v1/admin/contestants/{$id}");
    $this->actingAs($admin)->postJson('/api/v1/admin/contestants', mgmtPayload(['user_id' => $userId]))
        ->assertStatus(422);

    $this->actingAs($admin)->postJson("/api/v1/admin/contestants/{$id}/restore")->assertStatus(200);

    expect(DB::table('contestants')->where('user_id', $userId)->count())->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Index — pagination, filters, performance
|--------------------------------------------------------------------------
*/

test('the list paginates and per_page is honoured and capped', function (): void {
    $admin = mgmtAs('super_admin');
    for ($i = 0; $i < 25; $i++) {
        mgmtContestant(['full_name' => sprintf('Person %02d', $i)]);
    }

    $first = $this->actingAs($admin)->getJson('/api/v1/admin/contestants')->json();
    expect($first['data'])->toHaveCount(20);
    expect($first['meta']['pagination']['total'])->toBe(25);
    expect($first['meta']['pagination']['last_page'])->toBe(2);

    $second = $this->actingAs($admin)->getJson('/api/v1/admin/contestants?page=2')->json();
    expect($second['data'])->toHaveCount(5);

    expect($this->actingAs($admin)->getJson('/api/v1/admin/contestants?per_page=5')->json('data'))->toHaveCount(5);

    $this->actingAs($admin)->getJson('/api/v1/admin/contestants?per_page=500')
        ->assertStatus(422)->assertJsonStructure(['error' => ['fields' => ['per_page']]]);
});

test('the list filters by country and gender', function (): void {
    $admin = mgmtAs('super_admin');
    mgmtContestant(['gender' => 'male', 'full_name' => 'A Male']);
    mgmtContestant(['gender' => 'female', 'full_name' => 'A Female']);

    $females = $this->actingAs($admin)->getJson('/api/v1/admin/contestants?gender=female')->json('data');
    expect($females)->toHaveCount(1);
    expect($females[0]['full_name'])->toBe('A Female');

    expect($this->actingAs($admin)->getJson('/api/v1/admin/contestants?country_id='.mgmtCountry())->json('data'))
        ->toHaveCount(2);
});

test('the list issues a bounded number of queries regardless of row count', function (): void {
    // The N+1 check. profile_completeness is computed from fields already
    // loaded and issues no query, which is why it survived into the paged
    // list — measured here rather than assumed.
    $admin = mgmtAs('super_admin');
    for ($i = 0; $i < 15; $i++) {
        mgmtContestant(['full_name' => sprintf('Person %02d', $i)]);
    }

    DB::enableQueryLog();
    $this->actingAs($admin)->getJson('/api/v1/admin/contestants?per_page=15')->assertStatus(200);
    $queries = count(DB::getQueryLog());
    DB::disableQueryLog();

    // Count + page + the authorization lookups. Far below one-per-row.
    expect($queries)->toBeLessThan(15);
});

/*
|--------------------------------------------------------------------------
| Privacy — the national_id decision, asserted in both directions
|--------------------------------------------------------------------------
*/

test('national_id is absent from the list and present on the detail', function (): void {
    $admin = mgmtAs('super_admin');
    [$id] = mgmtContestant(['national_id' => '1234567890']);

    $listRow = $this->actingAs($admin)->getJson('/api/v1/admin/contestants')->json('data.0');
    expect($listRow)->not->toHaveKey('national_id');

    $detail = $this->actingAs($admin)->getJson("/api/v1/admin/contestants/{$id}")->json('data');
    expect($detail['national_id'])->toBe('1234567890');
});

test('a national_id cannot be used to find its owner through search', function (): void {
    // The lookup oracle this closes: a caller with a fragment of an identity
    // document could otherwise confirm whose it is.
    $admin = mgmtAs('super_admin');
    mgmtContestant(['national_id' => '5550001111', 'full_name' => 'Hidden Person']);

    expect($this->actingAs($admin)->getJson('/api/v1/admin/contestants?q=5550001111')->json('data'))->toBe([]);
    expect($this->actingAs($admin)->getJson('/api/v1/admin/contestants?q=555000')->json('data'))->toBe([]);
    expect($this->actingAs($admin)->getJson('/api/v1/admin/contestants?search=5550001111')->json('data'))->toBe([]);
});
