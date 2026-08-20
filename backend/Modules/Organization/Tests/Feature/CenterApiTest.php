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
use Modules\Organization\Domain\Events\CenterCreated;
use Modules\Organization\Domain\Events\CenterDeleted;
use Modules\Organization\Domain\Events\CenterUpdated;
use Modules\Organization\Domain\ValueObjects\Coordinates;
use Modules\Organization\Infrastructure\Database\Models\CenterModel;

uses(RefreshDatabase::class)->group('organization', 'feature', 'centers');

beforeEach(function (): void {
    (new PermissionsSeeder)->run();
    app(RolesSeeder::class)->run();
});

function centerAdmin(string $email = 'center-admin@quran.test'): UserModel
{
    return withSuperAdmin(UserModel::query()->create([
        'id' => (string) Str::uuid(),
        'email' => $email,
        'name' => 'Centre Admin',
        'type' => UserType::ADMIN,
        'password_hash' => password_hash('Pass123!', PASSWORD_BCRYPT),
        'is_active' => true,
    ]));
}

function centerCountry(string $iso2 = 'JO', string $iso3 = 'JOR'): string
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

function centerPayload(string $countryId, array $overrides = []): array
{
    return array_merge([
        'name' => 'Central Centre',
        'country_id' => $countryId,
        'city' => 'Amman',
        'address' => '12 King Hussein Street',
    ], $overrides);
}

/*
|--------------------------------------------------------------------------
| Creating
|--------------------------------------------------------------------------
*/

test('a centre is created with its full location', function (): void {
    $country = centerCountry();

    $this->actingAs(centerAdmin())
        ->postJson('/api/v1/admin/centers', centerPayload($country, [
            'latitude' => 31.9539,
            'longitude' => 35.9106,
        ]))
        ->assertCreated()
        ->assertJsonPath('data.name', 'Central Centre')
        ->assertJsonPath('data.city', 'Amman')
        ->assertJsonPath('data.coordinates.latitude', 31.9539);
});

test('coordinates are optional', function (): void {
    $country = centerCountry();

    $this->actingAs(centerAdmin())
        ->postJson('/api/v1/admin/centers', centerPayload($country))
        ->assertCreated()
        ->assertJsonPath('data.coordinates', null);
});

test('half a coordinate is refused', function (): void {
    // Latitude without longitude is not half a location — it is no location,
    // and storing it would let a map plot the point on the prime meridian.
    $country = centerCountry();

    $this->actingAs(centerAdmin())
        ->postJson('/api/v1/admin/centers', centerPayload($country, ['latitude' => 31.9539]))
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'VALIDATION_ERROR');

    expect(CenterModel::query()->count())->toBe(0);
});

test('an out-of-range coordinate is refused', function (): void {
    $country = centerCountry();

    $this->actingAs(centerAdmin())
        ->postJson('/api/v1/admin/centers', centerPayload($country, [
            'latitude' => 31.9539,
            'longitude' => 359.0,
        ]))
        ->assertStatus(422);
});

test('the domain refuses an out-of-range coordinate even without the form', function (): void {
    // The FormRequest is the readable refusal; this is the guarantee. A value
    // object that trusted its caller would let any other entry point through.
    expect(fn () => new Coordinates(31.9539, 359.0))->toThrow(InvalidArgumentException::class);
    expect(fn () => new Coordinates(91.0, 35.9))->toThrow(InvalidArgumentException::class);
    expect(fn () => Coordinates::fromNullable(31.9, null))->toThrow(InvalidArgumentException::class);
    expect(Coordinates::fromNullable(null, null))->toBeNull();
});

test('creating records an event', function (): void {
    Event::fake([CenterCreated::class]);
    $country = centerCountry();

    $this->actingAs(centerAdmin())
        ->postJson('/api/v1/admin/centers', centerPayload($country))
        ->assertCreated();

    Event::assertDispatched(CenterCreated::class, fn (CenterCreated $e): bool => $e->name === 'Central Centre');
});

/*
|--------------------------------------------------------------------------
| Names are unique per city, not per country and not globally
|--------------------------------------------------------------------------
*/

test('two centres in one city cannot share a name', function (): void {
    $country = centerCountry();
    $admin = centerAdmin();

    $this->actingAs($admin)->postJson('/api/v1/admin/centers', centerPayload($country))->assertCreated();

    $this->actingAs($admin)
        ->postJson('/api/v1/admin/centers', centerPayload($country))
        ->assertStatus(409)
        ->assertJsonPath('error.code', 'CENTER_NAME_TAKEN');

    expect(CenterModel::query()->count())->toBe(1);
});

test('the same name in two cities is allowed', function (): void {
    // One "Central Centre" per town is ordinary. Scoping uniqueness to the
    // country would force operators to invent suffixes that mean nothing.
    $admin = centerAdmin();
    $jordan = centerCountry();

    $this->actingAs($admin)->postJson('/api/v1/admin/centers', centerPayload($jordan, ['city' => 'Amman']))->assertCreated();
    $this->actingAs($admin)->postJson('/api/v1/admin/centers', centerPayload($jordan, ['city' => 'Irbid']))->assertCreated();
    $this->actingAs($admin)->postJson('/api/v1/admin/centers', centerPayload($jordan, ['city' => 'Zarqa']))->assertCreated();

    expect(CenterModel::query()->count())->toBe(3);
});

test('the same name in two countries is allowed', function (): void {
    $admin = centerAdmin();
    $jordan = centerCountry('JO', 'JOR');
    $egypt = centerCountry('EG', 'EGY');

    $this->actingAs($admin)->postJson('/api/v1/admin/centers', centerPayload($jordan))->assertCreated();
    $this->actingAs($admin)->postJson('/api/v1/admin/centers', centerPayload($egypt))->assertCreated();

    expect(CenterModel::query()->count())->toBe(2);
});

test('relocating into a city that already has that name is refused', function (): void {
    // The collision the update path must catch: checking the city being left
    // rather than the one being entered would let two centres share a name in
    // the destination town.
    $admin = centerAdmin();
    $country = centerCountry();

    $this->actingAs($admin)->postJson('/api/v1/admin/centers', centerPayload($country, ['city' => 'Amman']))->assertCreated();
    $moving = $this->actingAs($admin)
        ->postJson('/api/v1/admin/centers', centerPayload($country, ['city' => 'Irbid']))
        ->json('data.id');

    $this->actingAs($admin)
        ->patchJson("/api/v1/admin/centers/{$moving}", [
            'name' => 'Central Centre',
            'city' => 'Amman',
            'address' => 'Somewhere',
        ])
        ->assertStatus(409)
        ->assertJsonPath('error.code', 'CENTER_NAME_TAKEN');

    expect(CenterModel::query()->find($moving)->city)->toBe('Irbid');
});

/*
|--------------------------------------------------------------------------
| Updating
|--------------------------------------------------------------------------
*/

test('a centre can be renamed and relocated', function (): void {
    $country = centerCountry();
    $admin = centerAdmin();

    $id = $this->actingAs($admin)->postJson('/api/v1/admin/centers', centerPayload($country))
        ->json('data.id');

    $this->actingAs($admin)
        ->patchJson("/api/v1/admin/centers/{$id}", [
            'name' => 'Renamed Centre',
            'city' => 'Zarqa',
            'address' => '4 New Street',
        ])
        ->assertOk()
        ->assertJsonPath('data.name', 'Renamed Centre')
        ->assertJsonPath('data.city', 'Zarqa');
});

test('renaming a centre to the name it already has is not a collision with itself', function (): void {
    $country = centerCountry();
    $admin = centerAdmin();

    $id = $this->actingAs($admin)->postJson('/api/v1/admin/centers', centerPayload($country))
        ->json('data.id');

    $this->actingAs($admin)
        ->patchJson("/api/v1/admin/centers/{$id}", [
            'name' => 'Central Centre',
            'city' => 'Amman',
            'address' => '12 King Hussein Street',
        ])
        ->assertOk();
});

test('a centre cannot change country', function (): void {
    // A centre that changed country would be a different centre: its circles
    // and every application that froze its name belong to the place it was.
    $admin = centerAdmin();
    $jordan = centerCountry('JO', 'JOR');
    $egypt = centerCountry('EG', 'EGY');

    $id = $this->actingAs($admin)->postJson('/api/v1/admin/centers', centerPayload($jordan))
        ->json('data.id');

    $this->actingAs($admin)
        ->patchJson("/api/v1/admin/centers/{$id}", [
            'name' => 'Central Centre',
            'city' => 'Amman',
            'address' => '12 King Hussein Street',
            'country_id' => $egypt,
        ])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'VALIDATION_ERROR');

    expect(CenterModel::query()->find($id)->country_id)->toBe($jordan);
});

test('an unchanged update records nothing', function (): void {
    $country = centerCountry();
    $admin = centerAdmin();

    $id = $this->actingAs($admin)->postJson('/api/v1/admin/centers', centerPayload($country))
        ->json('data.id');

    Event::fake([CenterUpdated::class]);

    $this->actingAs($admin)->patchJson("/api/v1/admin/centers/{$id}", [
        'name' => 'Central Centre',
        'city' => 'Amman',
        'address' => '12 King Hussein Street',
    ])->assertOk();

    Event::assertNotDispatched(CenterUpdated::class);
});

test('a rename records both names', function (): void {
    // Applications freeze a centre's name at submission, so when a 2024 record
    // disagrees with the current name, this event is the answer.
    $country = centerCountry();
    $admin = centerAdmin();

    $id = $this->actingAs($admin)->postJson('/api/v1/admin/centers', centerPayload($country))
        ->json('data.id');

    Event::fake([CenterUpdated::class]);

    $this->actingAs($admin)->patchJson("/api/v1/admin/centers/{$id}", [
        'name' => 'New Name',
        'city' => 'Amman',
        'address' => '12 King Hussein Street',
    ])->assertOk();

    Event::assertDispatched(CenterUpdated::class, function (CenterUpdated $e): bool {
        return $e->previousName === 'Central Centre' && $e->name === 'New Name';
    });
});

/*
|--------------------------------------------------------------------------
| Deleting
|--------------------------------------------------------------------------
*/

test('deleting is soft: the row survives', function (): void {
    // Applications freeze a centre's name, so destroying the row would leave
    // those records pointing at nothing.
    $country = centerCountry();
    $admin = centerAdmin();

    $id = $this->actingAs($admin)->postJson('/api/v1/admin/centers', centerPayload($country))
        ->json('data.id');

    $this->actingAs($admin)->deleteJson("/api/v1/admin/centers/{$id}")->assertOk();

    expect(CenterModel::query()->find($id))->toBeNull();
    expect(CenterModel::withTrashed()->find($id))->not->toBeNull();
});

test('deleting records an event', function (): void {
    $country = centerCountry();
    $admin = centerAdmin();

    $id = $this->actingAs($admin)->postJson('/api/v1/admin/centers', centerPayload($country))
        ->json('data.id');

    Event::fake([CenterDeleted::class]);
    $this->actingAs($admin)->deleteJson("/api/v1/admin/centers/{$id}")->assertOk();

    Event::assertDispatched(CenterDeleted::class);
});

/*
|--------------------------------------------------------------------------
| Authorisation
|--------------------------------------------------------------------------
*/

test('each centre action needs its own permission', function (): void {
    $admin = centerAdmin();
    $country = centerCountry();
    $id = $this->actingAs($admin)->postJson('/api/v1/admin/centers', centerPayload($country))
        ->json('data.id');

    $viewer = UserModel::query()->create([
        'id' => (string) Str::uuid(),
        'email' => 'center-viewer@quran.test',
        'name' => 'Viewer',
        'type' => UserType::ADMIN,
        'password_hash' => password_hash('Pass123!', PASSWORD_BCRYPT),
        'is_active' => true,
    ]);

    $role = app(CreateRoleUseCase::class)->execute('center_viewer', ['centers.view'], (string) $admin->id);
    app(AssignRoleToUserUseCase::class)->execute((string) $viewer->id, $role->id->value, (string) $admin->id);

    $this->actingAs($viewer)->getJson('/api/v1/admin/centers')->assertOk();
    $this->actingAs($viewer)->postJson('/api/v1/admin/centers', centerPayload($country, ['name' => 'X']))->assertForbidden();
    $this->actingAs($viewer)->patchJson("/api/v1/admin/centers/{$id}", ['name' => 'X', 'city' => 'Y', 'address' => 'Z'])->assertForbidden();
    $this->actingAs($viewer)->deleteJson("/api/v1/admin/centers/{$id}")->assertForbidden();
});

test('managing centres is not granted by managing contestants', function (): void {
    // ADR-016 D6: where people study is not the people. A data-entry operator
    // who may fix a phone number has no business relocating a centre.
    $admin = centerAdmin();
    $country = centerCountry();

    $operator = UserModel::query()->create([
        'id' => (string) Str::uuid(),
        'email' => 'data-entry@quran.test',
        'name' => 'Data Entry',
        'type' => UserType::ADMIN,
        'password_hash' => password_hash('Pass123!', PASSWORD_BCRYPT),
        'is_active' => true,
    ]);

    $role = app(CreateRoleUseCase::class)
        ->execute('contestant_operator', ['contestants.view', 'contestants.update'], (string) $admin->id);
    app(AssignRoleToUserUseCase::class)->execute((string) $operator->id, $role->id->value, (string) $admin->id);

    $this->actingAs($operator)->getJson('/api/v1/admin/centers')->assertForbidden();
    $this->actingAs($operator)->postJson('/api/v1/admin/centers', centerPayload($country))->assertForbidden();
});

test('a contestant cannot reach centre administration', function (): void {
    $contestant = UserModel::query()->create([
        'id' => (string) Str::uuid(),
        'email' => 'contestant@quran.test',
        'name' => 'Contestant',
        'type' => UserType::CONTESTANT,
        'password_hash' => password_hash('Pass123!', PASSWORD_BCRYPT),
        'is_active' => true,
    ]);

    $this->actingAs($contestant)->getJson('/api/v1/admin/centers')->assertForbidden();
});
