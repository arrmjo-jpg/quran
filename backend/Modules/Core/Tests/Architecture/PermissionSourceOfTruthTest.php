<?php

declare(strict_types=1);

use Modules\Core\Infrastructure\Database\Seeders\RolesSeeder;
use Modules\Core\Infrastructure\Permissions\PermissionCatalog;

uses()->group('core', 'architecture', 'identity', 'permissions');

/*
|--------------------------------------------------------------------------
| PermissionCatalog is the only source of truth — ADR-015 §4.3
|--------------------------------------------------------------------------
| The point is not typo-prevention. It is that renaming one permission
| must be a single-file change that the test suite catches everywhere it
| was referenced — which only holds if no permission name is written as a
| loose string somewhere else.
|
| Scope note: this scans application code (Modules/ and app/). Tests are
| excluded on purpose — a test naming a permission it is exercising is
| legitimate and readable, and forcing indirection there would make the
| assertions harder to follow, not safer.
*/

/**
 * A file's PHP with every comment removed.
 *
 * The scans below are about what the CODE does. A docblock explaining a
 * rule — or showing `$this->authorize('seasons.create')` as an example —
 * is documentation, not a hardcoded reference, and matching it would
 * push authors toward writing vaguer comments to satisfy a test.
 */
function permissionSourceCode(string $path): string
{
    $code = '';

    foreach (token_get_all((string) file_get_contents($path)) as $token) {
        if (is_array($token) && in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
            continue;
        }

        $code .= is_array($token) ? $token[1] : $token;
    }

    return $code;
}

/**
 * @return array<int, string>
 */
function permissionSourceFiles(): array
{
    $roots = [base_path('Modules'), base_path('app')];
    $files = [];

    foreach ($roots as $root) {
        /** @var iterable<SplFileInfo> $iterator */
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));

        foreach ($iterator as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $path = str_replace('\\', '/', $file->getPathname());

            // Tests are out of scope (see the note above); the catalogue
            // itself necessarily contains every name.
            if (str_contains($path, '/Tests/') || str_contains($path, 'PermissionCatalog.php')) {
                continue;
            }

            $files[] = $file->getPathname();
        }
    }

    return $files;
}

test('no application file hardcodes a permission name', function (): void {
    $known = PermissionCatalog::all();
    $violations = [];

    foreach (permissionSourceFiles() as $path) {
        // RolesSeeder is the one place a role's grants are DEFINED, the
        // same way PermissionCatalog is the one place permissions are.
        // Excluding it is not a loophole: a name that goes stale there is
        // caught by 'every permission a seeded role grants exists in the
        // catalogue' below, and by the repository refusing to persist a
        // grant with no row.
        if (str_contains(str_replace('\\', '/', $path), 'Seeders/RolesSeeder.php')) {
            continue;
        }

        $content = permissionSourceCode($path);

        foreach ($known as $name) {
            if (preg_match("/['\"]".preg_quote($name, '/')."['\"]/", $content) === 1) {
                $violations[] = basename($path).' → '.$name;
            }
        }
    }

    expect($violations)->toBeEmpty(
        'Permission names must come from PermissionCatalog, not string literals: '
        .implode('; ', $violations)
    );
});

test('no application file hardcodes a role name', function (): void {
    // Same reasoning, and the failure it prevents is the one this whole
    // design was written against: a hardcoded role list meant a role
    // created from the panel could not actually be used. Roles are data.
    $roleNames = ['super_admin', 'competition_manager', 'evaluator', 'data_entry', 'moderator'];
    $violations = [];

    foreach (permissionSourceFiles() as $path) {
        // The seeder's whole job is to name the roles it creates.
        if (str_contains(str_replace('\\', '/', $path), 'Seeders/RolesSeeder.php')) {
            continue;
        }

        $content = permissionSourceCode($path);

        foreach ($roleNames as $role) {
            if (preg_match("/['\"]".preg_quote($role, '/')."['\"]/", $content) === 1) {
                $violations[] = basename($path).' → '.$role;
            }
        }
    }

    expect($violations)->toBeEmpty(
        'Role names must never be hardcoded (ADR-015 §1): '.implode('; ', $violations)
    );
});

test('every permission a seeded role grants exists in the catalogue', function (): void {
    // Closes the loop left open by excluding RolesSeeder above. Reads the
    // definitions through reflection rather than running the seeder, so
    // this stays a pure architecture check with no database.
    $seeder = new ReflectionClass(RolesSeeder::class);
    $instance = $seeder->newInstanceWithoutConstructor();

    $definitions = [];

    foreach (['systemRoleDefinitions', 'seededRoleDefinitions'] as $methodName) {
        $method = $seeder->getMethod($methodName);
        $method->setAccessible(true);
        $definitions[] = $method->invoke($instance);
    }

    $unknown = [];

    foreach (array_merge(...$definitions) as $roleName => $permissions) {
        foreach ($permissions as $permission) {
            if (! PermissionCatalog::has($permission)) {
                $unknown[] = "{$roleName} → {$permission}";
            }
        }
    }

    expect($unknown)->toBeEmpty(
        'Seeded roles grant permissions the catalogue does not define: '.implode('; ', $unknown)
    );
});

test('the catalogue is reachable only from Infrastructure and Application', function (): void {
    // The domain may not import Infrastructure (ADR-002/ADR-012), which
    // is why PermissionName validates shape rather than membership. If a
    // domain file ever imports the catalogue, that reasoning has been
    // quietly reversed.
    $violations = [];

    foreach (permissionSourceFiles() as $path) {
        $normalised = str_replace('\\', '/', $path);

        if (! str_contains($normalised, '/Domain/')) {
            continue;
        }

        $content = (string) file_get_contents($path);

        // A code reference — an import or a static call — is the
        // violation. A docblock explaining why the domain deliberately
        // does NOT reach for the catalogue is the opposite of one, and
        // both PermissionName and UnknownPermissionException carry that
        // explanation.
        $importsIt = preg_match('/^use .*PermissionCatalog;/m', $content) === 1;
        $callsIt = preg_match('/\bPermissionCatalog::/', $content) === 1;

        if ($importsIt || $callsIt) {
            $violations[] = $path;
        }
    }

    expect($violations)->toBeEmpty(
        'Domain files must not reference PermissionCatalog (it lives in Infrastructure): '
        .implode(', ', $violations)
    );
});
