<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Modules\Core\Application\UseCases\AssignRoleToUserUseCase;
use Modules\Core\Application\UseCases\CreateRoleUseCase;
use Modules\Core\Domain\ValueObjects\UserType;
use Modules\Core\Infrastructure\Database\Models\UserModel;
use Modules\Core\Infrastructure\Database\Seeders\PermissionsSeeder;
use Modules\Core\Infrastructure\Database\Seeders\RolesSeeder;
use Modules\Organization\Domain\Events\CircleCreated;
use Modules\Organization\Domain\Events\CircleDeleted;
use Modules\Organization\Domain\Events\CircleUpdated;
use Modules\Organization\Infrastructure\Database\Models\CenterModel;
use Modules\Organization\Infrastructure\Database\Models\CircleModel;

uses(RefreshDatabase::class)->group('organization', 'feature', 'circles');

beforeEach(function (): void {
    (new PermissionsSeeder)->run();
    app(RolesSeeder::class)->run();
});

function circleAdmin(string $email = 'circle-admin@quran.test'): UserModel
{
    return withSuperAdmin(UserModel::query()->create([
        'id' => (string) Str::uuid(),
        'email' => $email,
        'name' => 'Circle Admin',
        'type' => UserType::ADMIN,
        'password_hash' => password_hash('Pass123!', PASSWORD_BCRYPT),
        'is_active' => true,
    ]));
}

function circlePlainUser(string $email): UserModel
{
    return UserModel::query()->create([
        'id' => (string) Str::uuid(),
        'email' => $email,
        'name' => 'Supervisor',
        'type' => UserType::ADMIN,
        'password_hash' => password_hash('Pass123!', PASSWORD_BCRYPT),
        'is_active' => true,
    ]);
}

function circleCountry(string $iso2 = 'JO', string $iso3 = 'JOR'): string
{
    $id = (string) Str::uuid();

    DB::table('countries')->insert([
        'id' => $id,
        'iso_code' => $iso2,
        'iso3_code' => $iso3,
        'phone_code' => '+962',
        'flag_url' => 'https://example.test/flag.svg',
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return $id;
}

/** A centre to hang circles off, created directly: this file is not testing centre creation. */
function circleCenter(string $countryId, string $name = 'Central Centre', string $city = 'Amman'): string
{
    $id = (string) Str::uuid();

    CenterModel::query()->create([
        'id' => $id,
        'name' => $name,
        'country_id' => $countryId,
        'city' => $city,
        'address' => '12 King Hussein Street',
    ]);

    return $id;
}

function circlePayload(string $centerId, array $overrides = []): array
{
    return array_merge([
        'name' => 'Morning Circle',
        'center_id' => $centerId,
    ], $overrides);
}

/*
|--------------------------------------------------------------------------
| Creating
|--------------------------------------------------------------------------
*/

test('a circle is opened at a centre', function (): void {
    $admin = circleAdmin();
    $center = circleCenter(circleCountry());

    $response = $this->actingAs($admin)
        ->postJson('/api/v1/admin/circles', circlePayload($center))
        ->assertCreated();

    expect($response->json('data.name'))->toBe('Morning Circle')
        ->and($response->json('data.center_id'))->toBe($center)
        ->and($response->json('data.supervisor_user_id'))->toBeNull();
});

test('creating records an event', function (): void {
    $admin = circleAdmin();
    $center = circleCenter(circleCountry());

    Event::fake([CircleCreated::class]);

    $this->actingAs($admin)->postJson('/api/v1/admin/circles', circlePayload($center))->assertCreated();

    Event::assertDispatched(CircleCreated::class);
});

test('two circles in one centre may not share a name', function (): void {
    $admin = circleAdmin();
    $center = circleCenter(circleCountry());

    $this->actingAs($admin)->postJson('/api/v1/admin/circles', circlePayload($center))->assertCreated();

    $this->actingAs($admin)->postJson('/api/v1/admin/circles', circlePayload($center))
        ->assertStatus(409)
        ->assertJsonPath('error.code', 'CIRCLE_NAME_TAKEN');
});

test('the same circle name is fine at a different centre', function (): void {
    // The point of scoping uniqueness to the centre rather than wider: every
    // centre in the country may reasonably run a "Morning Circle".
    $admin = circleAdmin();
    $country = circleCountry();
    $first = circleCenter($country, 'Central Centre', 'Amman');
    $second = circleCenter($country, 'Northern Centre', 'Irbid');

    $this->actingAs($admin)->postJson('/api/v1/admin/circles', circlePayload($first))->assertCreated();
    $this->actingAs($admin)->postJson('/api/v1/admin/circles', circlePayload($second))->assertCreated();

    expect(CircleModel::query()->where('name', 'Morning Circle')->count())->toBe(2);
});

test('a circle cannot be opened at a centre that does not exist', function (): void {
    $admin = circleAdmin();

    $this->actingAs($admin)
        ->postJson('/api/v1/admin/circles', circlePayload((string) Str::uuid()))
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'VALIDATION_ERROR')
        ->assertJsonStructure(['error' => ['fields' => ['center_id']]]);
});

test('a blank name is refused', function (): void {
    $admin = circleAdmin();
    $center = circleCenter(circleCountry());

    $this->actingAs($admin)
        ->postJson('/api/v1/admin/circles', circlePayload($center, ['name' => '   ']))
        ->assertStatus(422);
});

/*
|--------------------------------------------------------------------------
| The supervisor — ADR-016 D9
|--------------------------------------------------------------------------
*/

test('a supervisor may be appointed at creation', function (): void {
    $admin = circleAdmin();
    $center = circleCenter(circleCountry());
    $supervisor = circlePlainUser('supervisor@quran.test');

    $response = $this->actingAs($admin)
        ->postJson('/api/v1/admin/circles', circlePayload($center, [
            'supervisor_user_id' => (string) $supervisor->id,
        ]))
        ->assertCreated();

    expect($response->json('data.supervisor_user_id'))->toBe((string) $supervisor->id);
});

test('a supervisor must be a real account', function (): void {
    // D9 makes a supervisor a User rather than a third kind of identity, so
    // this validates against users and not against a supervisors table.
    $admin = circleAdmin();
    $center = circleCenter(circleCountry());

    $this->actingAs($admin)
        ->postJson('/api/v1/admin/circles', circlePayload($center, [
            'supervisor_user_id' => (string) Str::uuid(),
        ]))
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'VALIDATION_ERROR')
        ->assertJsonStructure(['error' => ['fields' => ['supervisor_user_id']]]);
});

test('an appointment can be withdrawn by passing null', function (): void {
    $admin = circleAdmin();
    $center = circleCenter(circleCountry());
    $supervisor = circlePlainUser('supervisor@quran.test');

    $id = $this->actingAs($admin)->postJson('/api/v1/admin/circles', circlePayload($center, [
        'supervisor_user_id' => (string) $supervisor->id,
    ]))->json('data.id');

    $response = $this->actingAs($admin)
        ->patchJson("/api/v1/admin/circles/{$id}", ['name' => 'Morning Circle', 'supervisor_user_id' => null])
        ->assertOk();

    expect($response->json('data.supervisor_user_id'))->toBeNull();
});

test('changing the supervisor is recorded distinctly from a rename', function (): void {
    $admin = circleAdmin();
    $center = circleCenter(circleCountry());
    $supervisor = circlePlainUser('supervisor@quran.test');

    $id = $this->actingAs($admin)->postJson('/api/v1/admin/circles', circlePayload($center))->json('data.id');

    Event::fake([CircleUpdated::class]);

    $this->actingAs($admin)->patchJson("/api/v1/admin/circles/{$id}", [
        'name' => 'Morning Circle',
        'supervisor_user_id' => (string) $supervisor->id,
    ])->assertOk();

    Event::assertDispatched(CircleUpdated::class, function (CircleUpdated $e) use ($supervisor): bool {
        return $e->previousName === $e->name
            && $e->previousSupervisorUserId === null
            && $e->supervisorUserId === (string) $supervisor->id;
    });
});

/*
|--------------------------------------------------------------------------
| Reading — a circle has no location of its own (Q3)
|--------------------------------------------------------------------------
*/

test('a circle carries its centre rather than a copy of its location', function (): void {
    $admin = circleAdmin();
    $country = circleCountry();
    $center = circleCenter($country);

    $this->actingAs($admin)->postJson('/api/v1/admin/circles', circlePayload($center))->assertCreated();

    $row = $this->actingAs($admin)->getJson('/api/v1/admin/circles')->assertOk()->json('data.0');

    // Reads through to the centre...
    expect($row['center']['city'])->toBe('Amman')
        ->and($row['center']['country_id'])->toBe($country);

    // ...and stores none of it itself. This is the assertion that would fail
    // if someone later "helpfully" denormalised a city onto circles.
    expect($row)->not->toHaveKey('city')
        ->and($row)->not->toHaveKey('address')
        ->and($row)->not->toHaveKey('country_id')
        ->and($row)->not->toHaveKey('coordinates');
});

test('circles can be filtered to one centre', function (): void {
    $admin = circleAdmin();
    $country = circleCountry();
    $first = circleCenter($country, 'Central Centre', 'Amman');
    $second = circleCenter($country, 'Northern Centre', 'Irbid');

    $this->actingAs($admin)->postJson('/api/v1/admin/circles', circlePayload($first, ['name' => 'A']));
    $this->actingAs($admin)->postJson('/api/v1/admin/circles', circlePayload($second, ['name' => 'B']));

    $response = $this->actingAs($admin)->getJson("/api/v1/admin/circles?center_id={$first}")->assertOk();

    expect($response->json('meta.pagination.total'))->toBe(1)
        ->and($response->json('data.0.name'))->toBe('A');
});

/*
|--------------------------------------------------------------------------
| Updating
|--------------------------------------------------------------------------
*/

test('a circle can be renamed', function (): void {
    $admin = circleAdmin();
    $center = circleCenter(circleCountry());

    $id = $this->actingAs($admin)->postJson('/api/v1/admin/circles', circlePayload($center))->json('data.id');

    $this->actingAs($admin)->patchJson("/api/v1/admin/circles/{$id}", ['name' => 'Evening Circle'])
        ->assertOk()
        ->assertJsonPath('data.name', 'Evening Circle');
});

test('a circle cannot be moved to another centre', function (): void {
    // Under Q3 a circle's location IS its centre's, so moving one would
    // silently relocate everything that reads through it. `prohibited` means
    // the caller is told rather than quietly overruled.
    $admin = circleAdmin();
    $country = circleCountry();
    $center = circleCenter($country, 'Central Centre', 'Amman');
    $other = circleCenter($country, 'Northern Centre', 'Irbid');

    $id = $this->actingAs($admin)->postJson('/api/v1/admin/circles', circlePayload($center))->json('data.id');

    $this->actingAs($admin)
        ->patchJson("/api/v1/admin/circles/{$id}", ['name' => 'Morning Circle', 'center_id' => $other])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'VALIDATION_ERROR')
        ->assertJsonStructure(['error' => ['fields' => ['center_id']]]);

    expect(CircleModel::query()->find($id)->center_id)->toBe($center);
});

test('renaming onto a name already used in the same centre is refused', function (): void {
    $admin = circleAdmin();
    $center = circleCenter(circleCountry());

    $this->actingAs($admin)->postJson('/api/v1/admin/circles', circlePayload($center, ['name' => 'A']));
    $id = $this->actingAs($admin)->postJson('/api/v1/admin/circles', circlePayload($center, ['name' => 'B']))
        ->json('data.id');

    $this->actingAs($admin)->patchJson("/api/v1/admin/circles/{$id}", ['name' => 'A'])
        ->assertStatus(409)
        ->assertJsonPath('error.code', 'CIRCLE_NAME_TAKEN');
});

test('saving a circle unchanged records no event', function (): void {
    $admin = circleAdmin();
    $center = circleCenter(circleCountry());

    $id = $this->actingAs($admin)->postJson('/api/v1/admin/circles', circlePayload($center))->json('data.id');

    Event::fake([CircleUpdated::class]);

    $this->actingAs($admin)->patchJson("/api/v1/admin/circles/{$id}", ['name' => 'Morning Circle'])->assertOk();

    Event::assertNotDispatched(CircleUpdated::class);
});

/*
|--------------------------------------------------------------------------
| Deleting
|--------------------------------------------------------------------------
*/

test('deleting a circle is soft', function (): void {
    $admin = circleAdmin();
    $center = circleCenter(circleCountry());

    $id = $this->actingAs($admin)->postJson('/api/v1/admin/circles', circlePayload($center))->json('data.id');

    $this->actingAs($admin)->deleteJson("/api/v1/admin/circles/{$id}")->assertOk();

    expect(CircleModel::query()->find($id))->toBeNull();
    expect(CircleModel::withTrashed()->find($id))->not->toBeNull();
});

test('deleting a circle records an event', function (): void {
    $admin = circleAdmin();
    $center = circleCenter(circleCountry());

    $id = $this->actingAs($admin)->postJson('/api/v1/admin/circles', circlePayload($center))->json('data.id');

    Event::fake([CircleDeleted::class]);
    $this->actingAs($admin)->deleteJson("/api/v1/admin/circles/{$id}")->assertOk();

    Event::assertDispatched(CircleDeleted::class);
});

test('a closed circle releases its name', function (): void {
    // The unique index was declared on (center_id, name) with no reference to
    // deleted_at, while every application lookup is scoped to live rows. The
    // use case therefore reported the name free and the insert was refused by
    // the database — a 500 on a legitimate action. Reproduced on SQLite and on
    // MySQL before the fix migration was written.
    $admin = circleAdmin();
    $center = circleCenter(circleCountry());

    $first = $this->actingAs($admin)->postJson('/api/v1/admin/circles', circlePayload($center))
        ->assertCreated()->json('data.id');

    $this->actingAs($admin)->deleteJson("/api/v1/admin/circles/{$first}")->assertOk();

    $second = $this->actingAs($admin)->postJson('/api/v1/admin/circles', circlePayload($center))
        ->assertCreated()->json('data.id');

    expect($second)->not->toBe($first);
    expect(CircleModel::withTrashed()->where('name', 'Morning Circle')->count())->toBe(2);
});

test('a live circle still blocks its name, and says so politely', function (): void {
    // The other half of the same index: scoping uniqueness to live rows must
    // not weaken it among them. This is the case the fix could plausibly have
    // broken, which is why it sits next to the one it fixed.
    $admin = circleAdmin();
    $center = circleCenter(circleCountry());

    $this->actingAs($admin)->postJson('/api/v1/admin/circles', circlePayload($center))->assertCreated();

    $this->actingAs($admin)->postJson('/api/v1/admin/circles', circlePayload($center))
        ->assertStatus(409)
        ->assertJsonPath('error.code', 'CIRCLE_NAME_TAKEN');
});

/*
|--------------------------------------------------------------------------
| The guard Story 1 deferred to this story
|--------------------------------------------------------------------------
|
| DeleteCenterUseCase shipped without a circle guard because circles did not
| exist and nothing could have made the check fail. These are the tests that
| could not be written then — and the second one is what proves the guard
| releases rather than merely refuses.
*/

test('a centre holding a circle cannot be closed', function (): void {
    $admin = circleAdmin();
    $center = circleCenter(circleCountry());

    $this->actingAs($admin)->postJson('/api/v1/admin/circles', circlePayload($center))->assertCreated();

    $this->actingAs($admin)->deleteJson("/api/v1/admin/centers/{$center}")
        ->assertStatus(409)
        ->assertJsonPath('error.code', 'CENTER_HAS_CIRCLES');

    expect(CenterModel::query()->find($center))->not->toBeNull();
});

test('the refusal names how many circles are in the way', function (): void {
    $admin = circleAdmin();
    $center = circleCenter(circleCountry());

    $this->actingAs($admin)->postJson('/api/v1/admin/circles', circlePayload($center, ['name' => 'A']));
    $this->actingAs($admin)->postJson('/api/v1/admin/circles', circlePayload($center, ['name' => 'B']));

    $message = $this->actingAs($admin)->deleteJson("/api/v1/admin/centers/{$center}")
        ->assertStatus(409)
        ->json('error.message');

    expect($message)->toContain('2');
});

test('closing the circles frees the centre', function (): void {
    $admin = circleAdmin();
    $center = circleCenter(circleCountry());

    $circle = $this->actingAs($admin)->postJson('/api/v1/admin/circles', circlePayload($center))->json('data.id');

    $this->actingAs($admin)->deleteJson("/api/v1/admin/centers/{$center}")->assertStatus(409);

    $this->actingAs($admin)->deleteJson("/api/v1/admin/circles/{$circle}")->assertOk();

    // A soft-deleted circle must not keep the centre open — that is the whole
    // reason countInCenter leans on the SoftDeletes scope.
    $this->actingAs($admin)->deleteJson("/api/v1/admin/centers/{$center}")->assertOk();
});

/*
|--------------------------------------------------------------------------
| Authorisation
|--------------------------------------------------------------------------
*/

test('each circle action needs its own permission', function (): void {
    $admin = circleAdmin();
    $center = circleCenter(circleCountry());
    $id = $this->actingAs($admin)->postJson('/api/v1/admin/circles', circlePayload($center))->json('data.id');

    $viewer = circlePlainUser('circle-viewer@quran.test');

    $role = app(CreateRoleUseCase::class)->execute('circle_viewer', ['circles.view'], (string) $admin->id);
    app(AssignRoleToUserUseCase::class)->execute((string) $viewer->id, $role->id->value, (string) $admin->id);

    $this->actingAs($viewer)->getJson('/api/v1/admin/circles')->assertOk();
    $this->actingAs($viewer)->postJson('/api/v1/admin/circles', circlePayload($center, ['name' => 'X']))->assertForbidden();
    $this->actingAs($viewer)->patchJson("/api/v1/admin/circles/{$id}", ['name' => 'X'])->assertForbidden();
    $this->actingAs($viewer)->deleteJson("/api/v1/admin/circles/{$id}")->assertForbidden();
});
