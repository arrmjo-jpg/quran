<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Core\Domain\Repositories\RoleRepositoryContract;
use Modules\Core\Domain\ValueObjects\UserId;
use Modules\Core\Domain\ValueObjects\UserType;
use Modules\Core\Infrastructure\Database\Models\UserModel;
use Modules\Core\Infrastructure\Database\Seeders\PermissionsSeeder;
use Modules\Core\Infrastructure\Database\Seeders\RolesSeeder;
use Modules\Core\Infrastructure\Permissions\EffectivePermissionResolver;

uses(RefreshDatabase::class)->group('core', 'feature', 'identity', 'golden-master');

/*
|--------------------------------------------------------------------------
| GET /admin/users/{id} — the contract as it stands BEFORE Epic 4 Story 3
|--------------------------------------------------------------------------
|
| Story 3 adds a `contestant` branch to this endpoint (ADR-016 D22), which
| means changing a response other code already reads. This file records what
| it returns today so that change shows up as a diff against a recorded
| expectation rather than as a test written to match whatever came out.
|
| WHY NOW AND NOT WITH THE CHANGE. UserApiTest touches this route once, and
| asserts two paths inside it — `data.roles` and `data.permissions`. Neither
| pins the key set, so a branch could be added, removed or renamed here and
| the suite would stay green. That is exactly the blind spot a Golden Master
| exists to close, and closing it after the change would prove nothing.
|
| These assertions are DESCRIPTIVE, not prescriptive. Several record things
| that are arguably wrong — see the notes on `email` and on the deleted-user
| lookup. Recording them is not endorsing them.
*/

beforeEach(function (): void {
    (new PermissionsSeeder)->run();
    app(RolesSeeder::class)->run();
});

function gmUser(string $type = UserType::ADMIN, array $overrides = []): UserModel
{
    return UserModel::query()->create(array_merge([
        'id' => (string) Str::uuid(),
        'email' => 'gm-'.Str::random(10).'@quran.test',
        'name' => 'Golden Master Subject',
        'type' => $type,
        'password_hash' => password_hash('Pass123!', PASSWORD_BCRYPT),
        'is_active' => true,
        'preferred_locale' => 'ar',
    ], $overrides));
}

function gmAs(string $roleName): UserModel
{
    $user = gmUser();
    $role = app(RoleRepositoryContract::class)->findByName($roleName);

    DB::table('role_user')->insert(['role_id' => $role->id->value, 'user_id' => (string) $user->id]);

    app(EffectivePermissionResolver::class)->forget(new UserId((string) $user->id));

    return $user->fresh();
}

test('GM: the account detail requires authentication', function (): void {
    $subject = gmUser();

    $this->getJson("/api/v1/admin/users/{$subject->id}")->assertStatus(401);
});

test('GM: the account detail requires users.view', function (): void {
    $subject = gmUser();

    $this->actingAs(gmAs('competition_manager'))
        ->getJson("/api/v1/admin/users/{$subject->id}")
        ->assertStatus(403);
});

test('GM [CHANGED IN STORY 3]: the account detail returns exactly these fourteen keys', function (): void {
    $subject = gmUser();

    $response = $this->actingAs(gmAs('super_admin'))
        ->getJson("/api/v1/admin/users/{$subject->id}")
        ->assertOk();

    // BEFORE: twelve keys — AdminUserResource's eleven plus `permissions`.
    // AFTER:  fourteen. Story 3 adds `contestant` (ADR-016 D22) and
    //         `withheld` (D20's mechanism, applied in the other direction).
    //
    // Recorded as a closed set: the point of this file is that adding a
    // fifteenth is visible here rather than silent.
    expect(array_keys($response->json('data')))->toEqualCanonicalizing([
        'id',
        'name',
        'email',
        'type',
        'is_active',
        'is_deleted',
        'status',
        'preferred_locale',
        'mfa_enabled',
        'roles',
        'created_at',
        'permissions',
        'contestant',
        'withheld',
    ]);
});

test('GM: the detail carries email, unlike a contestant context', function (): void {
    $subject = gmUser(UserType::ADMIN, ['email' => 'recorded@quran.test']);

    $this->actingAs(gmAs('super_admin'))
        ->getJson("/api/v1/admin/users/{$subject->id}")
        ->assertOk()
        ->assertJsonPath('data.email', 'recorded@quran.test');

    // Recorded deliberately. ADR-016 D18 keeps `email` OUT of a contestant
    // context and says why — it answers no relationship question and is
    // reachable here, behind users.view. This assertion is the "here".
});

test('GM: the detail reports effective permissions, which the list does not', function (): void {
    $admin = gmAs('super_admin');

    $response = $this->actingAs($admin)
        ->getJson("/api/v1/admin/users/{$admin->id}")
        ->assertOk();

    expect($response->json('data.roles'))->toBe(['super_admin']);
    expect($response->json('data.permissions'))->toContain('users.view', 'contestants.view');
});

test('GM: the derived status is reported here too', function (array $columns, string $expected): void {
    $subject = gmUser(UserType::ADMIN, $columns);

    if ($expected === 'deleted') {
        UserModel::query()->whereKey($subject->id)->delete();
    }

    $this->actingAs(gmAs('super_admin'))
        ->getJson("/api/v1/admin/users/{$subject->id}")
        ->assertOk()
        ->assertJsonPath('data.status', $expected);
})->with([
    'active' => [['is_active' => true], 'active'],
    'deactivated' => [['is_active' => false], 'deactivated'],
    'pending' => [['password_hash' => null], 'pending_activation'],
    'deleted' => [['is_active' => true], 'deleted'],
]);

test('GM: a deleted account is still retrievable by id', function (): void {
    $subject = gmUser();
    UserModel::query()->whereKey($subject->id)->delete();

    // Deliberately unlike the contestant endpoints, which 404 on a
    // soft-deleted row. Recorded because it is a real inconsistency between
    // two admin surfaces, not because it is right — `withTrashed()` here is
    // what makes the restore flow work from a detail screen.
    $this->actingAs(gmAs('super_admin'))
        ->getJson("/api/v1/admin/users/{$subject->id}")
        ->assertOk()
        ->assertJsonPath('data.is_deleted', true);
});

test('GM: an unknown id returns 404', function (): void {
    $this->actingAs(gmAs('super_admin'))
        ->getJson('/api/v1/admin/users/'.Str::uuid())
        ->assertStatus(404);
});

test('GM: a malformed id returns 404, not 422', function (): void {
    // Recorded, not endorsed. The contestant endpoints answer 422 with
    // INVALID_CONTESTANT_ID for a malformed id because ContestantId
    // validates shape; this route hands the string straight to findOrFail.
    // Two admin surfaces, two answers to the same class of mistake.
    $this->actingAs(gmAs('super_admin'))
        ->getJson('/api/v1/admin/users/not-a-uuid')
        ->assertStatus(404);
});

test('GM [CHANGED IN STORY 3]: the contestant behind an account now crosses', function (): void {
    $account = gmUser(UserType::CONTESTANT);

    DB::table('countries')->insert([
        'id' => $countryId = (string) Str::uuid(),
        'iso_code' => 'JO', 'iso3_code' => 'JOR', 'phone_code' => '+962',
        'flag_url' => 'https://example.test/flag.svg', 'is_active' => true,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    DB::table('contestants')->insert([
        'id' => (string) Str::uuid(),
        'user_id' => (string) $account->id,
        'country_id' => $countryId,
        'full_name' => 'RECORDED CONTESTANT NAME',
        'date_of_birth' => '1999-01-01',
        'gender' => 'male',
        'national_id' => '9990000001',
        'phone_number' => '+962790000000',
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $response = $this->actingAs(gmAs('super_admin'))
        ->getJson("/api/v1/admin/users/{$account->id}")
        ->assertOk();

    // BEFORE: no `contestant` key at all. The account had one, and the
    //         endpoint said nothing about it — the link existed in the
    //         database and in one direction of the API only.
    // AFTER:  the three fields D22 admits, and no more.
    expect(array_keys($response->json('data.contestant')))
        ->toEqualCanonicalizing(['id', 'full_name', 'is_deleted']);

    expect($response->json('data.contestant.full_name'))->toBe('RECORDED CONTESTANT NAME');
    expect($response->json('data.contestant.is_deleted'))->toBeFalse();

    // The reader here holds contestants.view, so nothing is withheld.
    expect($response->json('data.withheld'))->toBe([]);

    // What D22 refused is still refused, and this is the assertion that
    // says so: the national ID is in the row the test inserted and nowhere
    // in the response.
    expect($response->getContent())->not->toContain('9990000001');
});
