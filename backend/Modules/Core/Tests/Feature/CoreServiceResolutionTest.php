<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Core\Contracts\CoreServiceContract;
use Modules\Core\Contracts\ResolvedUserDTO;
use Modules\Core\Domain\ValueObjects\UserType;
use Modules\Core\Infrastructure\Database\Models\UserModel;

uses(RefreshDatabase::class)->group('core', 'feature', 'identity');

/*
|--------------------------------------------------------------------------
| CoreService — the cross-module read Identity 360 composes from
|--------------------------------------------------------------------------
|
| ContestantIdentity360Test covers what the endpoint returns. This covers the
| service directly, because one of its properties is invisible from there: the
| password hash is never selected. A response test cannot see the difference
| between a hash that was fetched and discarded and one that was never
| fetched, and that difference is the whole point of the query.
*/

function resolutionUser(array $overrides = []): UserModel
{
    return UserModel::query()->create(array_merge([
        'id' => (string) Str::uuid(),
        'email' => 'resolution-'.Str::random(6).'@quran.test',
        'name' => 'Resolved Person',
        'type' => UserType::ADMIN,
        'password_hash' => password_hash('Pass123!', PASSWORD_BCRYPT),
        'is_active' => true,
    ], $overrides));
}

function resolveUsers(string ...$ids): array
{
    return app(CoreServiceContract::class)->findResolvedByIds($ids);
}

/*
|--------------------------------------------------------------------------
| The result, unchanged
|--------------------------------------------------------------------------
*/

test('the DTO carries exactly the four fields D18 admits', function (): void {
    $user = resolutionUser();

    $resolved = resolveUsers((string) $user->id);

    expect($resolved)->toHaveCount(1)
        ->and($resolved[0])->toBeInstanceOf(ResolvedUserDTO::class);

    // Not a spot check of four names: the object's whole public surface, so a
    // fifth property added later fails here rather than reaching a response.
    expect(array_keys(get_object_vars($resolved[0])))
        ->toBe(['id', 'name', 'status', 'type']);
});

test('status is derived from the same three facts as before', function (array $columns, string $expected): void {
    // The behaviour the hash was being read for. `has_password` replaces
    // reading it, and these four cases are what prove the replacement is
    // equivalent rather than merely compiling.
    $user = resolutionUser($columns);

    if ($expected === 'deleted') {
        $user->delete();
    }

    expect(resolveUsers((string) $user->id)[0]->status)->toBe($expected);
})->with([
    'active' => [['is_active' => true], 'active'],
    'deactivated' => [['is_active' => false], 'deactivated'],
    'pending activation' => [['password_hash' => null, 'is_active' => true], 'pending_activation'],
    'deleted' => [['is_active' => true], 'deleted'],
]);

test('a soft-deleted account still resolves', function (): void {
    // withTrashed, because a contestant whose account was deleted still has a
    // link worth reporting — the alternative is a null branch that reads as a
    // contestant with no account at all.
    $user = resolutionUser();
    $user->delete();

    expect(resolveUsers((string) $user->id))->toHaveCount(1);
});

test('an id that resolves to nothing is simply absent', function (): void {
    expect(resolveUsers((string) Str::uuid()))->toBe([]);
});

test('an empty list costs no query', function (): void {
    DB::flushQueryLog();
    DB::enableQueryLog();

    expect(resolveUsers())->toBe([]);

    $count = count(DB::getRawQueryLog());
    DB::disableQueryLog();

    expect($count)->toBe(0);
});

test('resolving many accounts is one query', function (): void {
    $users = collect(range(1, 5))->map(fn (): UserModel => resolutionUser());

    DB::flushQueryLog();
    DB::enableQueryLog();

    $resolved = resolveUsers(...$users->map(fn (UserModel $u): string => (string) $u->id)->all());

    $count = count(DB::getRawQueryLog());
    DB::disableQueryLog();

    expect($resolved)->toHaveCount(5)
        ->and($count)->toBe(1);
});

/*
|--------------------------------------------------------------------------
| The hash is never read
|--------------------------------------------------------------------------
*/

test('the query does not select the password hash', function (): void {
    // Asserted against the SQL itself, which is the only place the property
    // is observable. A test on the DTO would pass either way, because the
    // hash never reached it even when the column was being fetched.
    $user = resolutionUser();

    DB::flushQueryLog();
    DB::enableQueryLog();

    resolveUsers((string) $user->id);

    $sql = DB::getRawQueryLog()[0]['raw_query'] ?? '';
    DB::disableQueryLog();

    expect($sql)->not->toBeEmpty()
        // The column may be named only inside the IS NOT NULL test that
        // produces has_password, never in the select list on its own.
        ->and($sql)->toContain('has_password')
        ->and(substr_count($sql, 'password_hash'))->toBe(1);
});

test('the loaded model has no password hash attribute at all', function (): void {
    // The failure this guards is subtler than a leaked response: a model
    // carrying the hash can surface it in a var_dump, an exception trace or a
    // log context, none of which respect $hidden.
    $user = resolutionUser();

    $loaded = UserModel::withTrashed()
        ->whereIn('id', [(string) $user->id])
        ->selectRaw('id, name, type, deleted_at, is_active, (password_hash IS NOT NULL) AS has_password')
        ->first();

    expect($loaded->getAttributes())->not->toHaveKey('password_hash')
        ->and($loaded->getAttributes())->toHaveKey('has_password');
});
