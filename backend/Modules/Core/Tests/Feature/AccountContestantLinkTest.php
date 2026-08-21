<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Contestants\Contracts\ContestantsServiceContract;
use Modules\Core\Domain\Entities\Role;
use Modules\Core\Domain\Repositories\RoleRepositoryContract;
use Modules\Core\Domain\ValueObjects\PermissionName;
use Modules\Core\Domain\ValueObjects\RoleId;
use Modules\Core\Domain\ValueObjects\UserId;
use Modules\Core\Domain\ValueObjects\UserType;
use Modules\Core\Infrastructure\Database\Models\UserModel;
use Modules\Core\Infrastructure\Database\Seeders\PermissionsSeeder;
use Modules\Core\Infrastructure\Database\Seeders\RolesSeeder;
use Modules\Core\Infrastructure\Permissions\EffectivePermissionResolver;

uses(RefreshDatabase::class)->group('core', 'feature', 'identity');

/*
|--------------------------------------------------------------------------
| Account → Contestant — Epic 4 Story 3 (ADR-016 D22, D23)
|--------------------------------------------------------------------------
|
| The reverse of Identity 360. D16 drew the tree from a User downward and
| until now the API only ran the other way.
|
| The Golden Master alongside this file records the shape. What is tested
| here is the pair D20 exists for — a branch that is EMPTY versus a branch
| that is WITHHELD — and the boundary D22 draws, which is the reason
| ResolvedContestantDTO has three properties and not nine.
*/

beforeEach(function (): void {
    (new PermissionsSeeder)->run();
    app(RolesSeeder::class)->run();
});

function linkUser(string $type = UserType::ADMIN, array $overrides = []): UserModel
{
    return UserModel::query()->create(array_merge([
        'id' => (string) Str::uuid(),
        'email' => 'link-'.Str::random(10).'@quran.test',
        'name' => 'Account Holder',
        'type' => $type,
        'password_hash' => password_hash('Pass123!', PASSWORD_BCRYPT),
        'is_active' => true,
    ], $overrides));
}

/** An admin holding exactly the permissions named — no seeded role involved. */
function linkAsHolderOf(array $permissions): UserModel
{
    $user = linkUser();

    $role = new Role(
        id: RoleId::generate(),
        // Lowered: Role refuses anything that is not snake_case, and
        // Str::random returns mixed case.
        name: 'test_role_'.Str::lower(Str::random(8)),
        permissions: PermissionName::fromMany($permissions),
    );

    app(RoleRepositoryContract::class)->save($role);

    DB::table('role_user')->insert([
        'role_id' => $role->id->value,
        'user_id' => (string) $user->id,
    ]);

    app(EffectivePermissionResolver::class)->forget(new UserId((string) $user->id));

    return $user->fresh();
}

function linkContestant(UserModel $account, array $overrides = []): string
{
    // Reused rather than made fresh each time: `countries.iso_code` and
    // `iso3_code` are both UNIQUE, and none of these tests cares which
    // country the contestant is in.
    $countryId = DB::table('countries')->value('id');

    if ($countryId === null) {
        DB::table('countries')->insert([
            'id' => $countryId = (string) Str::uuid(),
            'iso_code' => 'JO', 'iso3_code' => 'JOR', 'phone_code' => '+962',
            'flag_url' => 'https://example.test/flag.svg', 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    $id = (string) Str::uuid();

    DB::table('contestants')->insert(array_merge([
        'id' => $id,
        'user_id' => (string) $account->id,
        'country_id' => (string) $countryId,
        'full_name' => 'Competitor Name',
        'date_of_birth' => '1999-01-01',
        'gender' => 'male',
        'national_id' => '9990000001',
        'phone_number' => '+962790000000',
        'created_at' => now(), 'updated_at' => now(),
    ], $overrides));

    return $id;
}

/*
|--------------------------------------------------------------------------
| D20's distinction, applied in the other direction
|--------------------------------------------------------------------------
*/

test('an account with no contestant reports null and withholds nothing', function (): void {
    $account = linkUser();

    $response = $this->actingAs(linkAsHolderOf(['users.view', 'contestants.view']))
        ->getJson("/api/v1/admin/users/{$account->id}")
        ->assertOk();

    expect($response->json('data.contestant'))->toBeNull();
    expect($response->json('data.withheld'))->toBe([]);
});

test('a reader without contestants.view is told the branch was withheld', function (): void {
    $account = linkUser(UserType::CONTESTANT);
    linkContestant($account);

    $response = $this->actingAs(linkAsHolderOf(['users.view']))
        ->getJson("/api/v1/admin/users/{$account->id}")
        ->assertOk();

    // Null AND named. Null alone is what the previous test returns for an
    // account that genuinely has no contestant — two different facts that
    // would otherwise be one byte-identical response, and the screen would
    // report "not a contestant" about somebody who is one.
    expect($response->json('data.contestant'))->toBeNull();
    expect($response->json('data.withheld'))->toBe(['contestant']);
});

test('the withheld branch leaks nothing, not even the fact of a name', function (): void {
    $account = linkUser(UserType::CONTESTANT);
    linkContestant($account, ['full_name' => 'WITHHELD PERSON NAME']);

    $response = $this->actingAs(linkAsHolderOf(['users.view']))
        ->getJson("/api/v1/admin/users/{$account->id}")
        ->assertOk();

    expect($response->getContent())->not->toContain('WITHHELD PERSON NAME');
});

/*
|--------------------------------------------------------------------------
| D22's boundary
|--------------------------------------------------------------------------
*/

test('the branch carries exactly the three fields D22 admits', function (): void {
    $account = linkUser(UserType::CONTESTANT);
    $contestantId = linkContestant($account);

    $response = $this->actingAs(linkAsHolderOf(['users.view', 'contestants.view']))
        ->getJson("/api/v1/admin/users/{$account->id}")
        ->assertOk();

    expect(array_keys($response->json('data.contestant')))
        ->toEqualCanonicalizing(['id', 'full_name', 'is_deleted']);

    // The id is the navigation target, which is the whole reason D22 admits
    // it — a relationship displayed but not followable is a diagram.
    expect($response->json('data.contestant.id'))->toBe($contestantId);
});

test('what describes the person never crosses — D22', function (): void {
    $account = linkUser(UserType::CONTESTANT);
    linkContestant($account, [
        'national_id' => '1234509876',
        'phone_number' => '+962799999999',
        'date_of_birth' => '2001-07-04',
    ]);

    $response = $this->actingAs(linkAsHolderOf(['users.view', 'contestants.view']))
        ->getJson("/api/v1/admin/users/{$account->id}")
        ->assertOk();

    // Asserted against the whole body rather than the branch: the point is
    // that none of it is anywhere in the response, however it might arrive.
    expect($response->getContent())->not->toContain('1234509876');
    expect($response->getContent())->not->toContain('+962799999999');
    expect($response->getContent())->not->toContain('2001-07-04');
});

test('a divergent account name and contestant name are both reported', function (): void {
    $account = linkUser(UserType::CONTESTANT, ['name' => 'Account Spelling']);
    linkContestant($account, ['full_name' => 'Competition Spelling']);

    $response = $this->actingAs(linkAsHolderOf(['users.view', 'contestants.view']))
        ->getJson("/api/v1/admin/users/{$account->id}")
        ->assertOk();

    // Two columns nothing keeps in step, and their divergence is the reason
    // D22 admits full_name at all rather than treating it as duplication.
    expect($response->json('data.name'))->toBe('Account Spelling');
    expect($response->json('data.contestant.full_name'))->toBe('Competition Spelling');
});

test('a soft-deleted contestant is reported, flagged, not hidden', function (): void {
    $account = linkUser(UserType::CONTESTANT);
    $contestantId = linkContestant($account);

    DB::table('contestants')->where('id', $contestantId)->update(['deleted_at' => now()]);

    $response = $this->actingAs(linkAsHolderOf(['users.view', 'contestants.view']))
        ->getJson("/api/v1/admin/users/{$account->id}")
        ->assertOk();

    // "No longer competing" is a different fact from "never competed", and
    // reporting null here would say the second. That distinction is the
    // whole reason D22 admits is_deleted.
    expect($response->json('data.contestant.id'))->toBe($contestantId);
    expect($response->json('data.contestant.is_deleted'))->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| Scope — the branch is on the detail only
|--------------------------------------------------------------------------
*/

test('the account LIST gains no contestant branch', function (): void {
    $account = linkUser(UserType::CONTESTANT);
    linkContestant($account, ['full_name' => 'LIST SHOULD NOT SHOW THIS']);

    $response = $this->actingAs(linkAsHolderOf(['users.view', 'contestants.view']))
        ->getJson('/api/v1/admin/users')
        ->assertOk();

    // Deliberate scope, not an oversight. A per-row branch needs a batched
    // lookup and a second withheld surface, for a fact nobody reads at a
    // glance — so the list stays as AdminUserResource defines it.
    expect($response->json('data.0'))->not->toHaveKey('contestant');
    expect($response->getContent())->not->toContain('LIST SHOULD NOT SHOW THIS');
});

/*
|--------------------------------------------------------------------------
| The boundary itself
|--------------------------------------------------------------------------
*/

test('the contract keys its result by account, not by contestant', function (): void {
    $first = linkUser(UserType::CONTESTANT);
    $second = linkUser(UserType::CONTESTANT);
    $firstContestant = linkContestant($first, ['full_name' => 'First Person']);
    linkContestant($second, ['full_name' => 'Second Person']);

    $resolved = app(ContestantsServiceContract::class)
        ->findResolvedByUserIds([(string) $first->id, (string) $second->id]);

    // Keyed by account id so the caller — which started from accounts — can
    // match rows without the DTO carrying a fourth field and blurring D22.
    expect(array_keys($resolved))->toEqualCanonicalizing([
        (string) $first->id,
        (string) $second->id,
    ]);

    expect($resolved[(string) $first->id]->id)->toBe($firstContestant);
    expect($resolved[(string) $first->id]->fullName)->toBe('First Person');
});

test('the contract omits accounts that have no contestant, and takes an empty batch', function (): void {
    $without = linkUser();

    expect(app(ContestantsServiceContract::class)->findResolvedByUserIds([(string) $without->id]))
        ->toBe([]);

    // The empty case short-circuits rather than issuing `where user_id in ()`.
    expect(app(ContestantsServiceContract::class)->findResolvedByUserIds([]))->toBe([]);
});

test('resolving a batch of accounts costs one query', function (): void {
    $accounts = [];

    foreach (range(1, 5) as $n) {
        $account = linkUser(UserType::CONTESTANT);
        linkContestant($account, ['full_name' => "Person {$n}"]);
        $accounts[] = (string) $account->id;
    }

    DB::flushQueryLog();
    DB::enableQueryLog();

    $resolved = app(ContestantsServiceContract::class)->findResolvedByUserIds($accounts);

    $queries = count(DB::getRawQueryLog());
    DB::disableQueryLog();

    expect($resolved)->toHaveCount(5);

    // The reason the method takes an array at all. A per-account version
    // would be an N+1 the first time anything renders a list of accounts.
    expect($queries)->toBe(1);
});
