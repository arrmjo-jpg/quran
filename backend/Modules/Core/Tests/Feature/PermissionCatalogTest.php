<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Core\Infrastructure\Database\Seeders\PermissionsSeeder;
use Modules\Core\Infrastructure\Permissions\PermissionCatalog;

uses(RefreshDatabase::class)->group('core', 'feature', 'identity', 'permissions');

/*
|--------------------------------------------------------------------------
| Permission catalogue consistency — ADR-015 §4.3, §4.4
|--------------------------------------------------------------------------
| The catalogue is a hand-maintained file, so the invariants that keep it
| trustworthy have to be asserted rather than assumed. These tests are
| what can be enforced before authorization is wired; the catalogue ↔ Gate
| binding test arrives with the epic that switches enforcement on.
*/

test('every permission name is unique', function (): void {
    $all = PermissionCatalog::all();

    $duplicates = array_keys(array_filter(array_count_values($all), fn (int $n): bool => $n > 1));

    expect($duplicates)->toBeEmpty('Duplicate permission names: '.implode(', ', $duplicates));
    expect($all)->toHaveCount(count(array_unique($all)));
});

test('every permission name is resource.action in snake_case', function (): void {
    // One dot, both halves snake_case, no leading/trailing/double
    // underscores, no uppercase, no hyphens — the API surface uses
    // kebab-case in URLs (open-registration) and the catalogue must not
    // drift into copying that.
    $pattern = '/^[a-z][a-z0-9]*(_[a-z0-9]+)*\.[a-z][a-z0-9]*(_[a-z0-9]+)*$/';

    foreach (PermissionCatalog::all() as $name) {
        expect((bool) preg_match($pattern, $name))
            ->toBeTrue("Permission '{$name}' is not a snake_case resource.action name.");
    }
});

test('every action is drawn from the allowed verb set', function (): void {
    foreach (PermissionCatalog::all() as $name) {
        $action = explode('.', $name)[1];

        expect(in_array($action, PermissionCatalog::ALLOWED_VERBS, true))
            ->toBeTrue("Permission '{$name}' uses a verb outside ALLOWED_VERBS.");
    }
});

test('the coarse verbs the board rejected do not appear', function (): void {
    // 'manage' was removed outright rather than left "discouraged"
    // (ADR-015 §4.4). The others are the shapes it tends to come back as.
    $forbidden = ['manage', 'all', 'any', 'admin', 'full', 'access', 'crud'];

    foreach (PermissionCatalog::all() as $name) {
        $action = explode('.', $name)[1];

        expect(in_array($action, $forbidden, true))
            ->toBeFalse("Permission '{$name}' uses a forbidden coarse verb.");
    }

    foreach ($forbidden as $verb) {
        expect(in_array($verb, PermissionCatalog::ALLOWED_VERBS, true))
            ->toBeFalse("ALLOWED_VERBS must not contain the coarse verb '{$verb}'.");
    }
});

test('no permission is a wildcard', function (): void {
    foreach (PermissionCatalog::all() as $name) {
        expect($name)->not->toContain('*');
    }
});

test('every allowed verb is actually used', function (): void {
    // Keeps ALLOWED_VERBS from accumulating aspirational entries: a verb
    // nobody uses is one more thing a future permission can be named
    // after by accident.
    $used = array_unique(array_map(
        static fn (string $name): string => explode('.', $name)[1],
        PermissionCatalog::all()
    ));

    $unused = array_diff(PermissionCatalog::ALLOWED_VERBS, $used);

    expect($unused)->toBeEmpty('Verbs allowed but unused: '.implode(', ', $unused));
});

test('grouping is derived from the names, not stored', function (): void {
    foreach (PermissionCatalog::grouped() as $resource => $names) {
        foreach ($names as $name) {
            expect(explode('.', $name)[0])->toBe($resource);
        }
    }

    expect(array_keys(PermissionCatalog::grouped()))->toBe(PermissionCatalog::resources());
    expect(array_merge(...array_values(PermissionCatalog::grouped())))->toBe(PermissionCatalog::all());
});

test('the seeder writes the whole catalogue and is idempotent', function (): void {
    $seeder = new PermissionsSeeder;

    $seeder->run();
    $first = DB::table('permissions')->pluck('name')->sort()->values()->all();

    $expected = collect(PermissionCatalog::all())->sort()->values()->all();
    expect($first)->toBe($expected);

    $seeder->run();
    expect(DB::table('permissions')->count())->toBe(PermissionCatalog::count());
});

test('the seeder backfills a catalogue entry added later', function (): void {
    (new PermissionsSeeder)->run();

    DB::table('permissions')->where('name', 'seasons.archive')->delete();
    expect(DB::table('permissions')->count())->toBe(PermissionCatalog::count() - 1);

    (new PermissionsSeeder)->run();

    expect(DB::table('permissions')->where('name', 'seasons.archive')->exists())->toBeTrue();
    expect(DB::table('permissions')->count())->toBe(PermissionCatalog::count());
});

test('no persisted permission is missing from the catalogue', function (): void {
    // Guards the direction the seeder deliberately does not handle: a row
    // left behind after its catalogue entry was removed. Silently deleting
    // it during a seed could revoke capability from live roles, so it is
    // reported here instead.
    (new PermissionsSeeder)->run();

    $orphans = array_diff(
        DB::table('permissions')->pluck('name')->all(),
        PermissionCatalog::all()
    );

    expect($orphans)->toBeEmpty(
        'Permissions exist in the database but not in the catalogue: '.implode(', ', $orphans)
    );
});
