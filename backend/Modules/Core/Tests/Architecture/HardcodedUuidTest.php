<?php

declare(strict_types=1);

use Modules\Core\Domain\ValueObjects\UserId;

uses()->group('core', 'architecture', 'identity');

/*
|--------------------------------------------------------------------------
| Hand-written UUIDs must be ones the domain accepts
|--------------------------------------------------------------------------
|
| `00000000-0000-0000-0000-000000000001` was seeded as the platform admin's
| id for months. It looks like a UUID, Ramsey's validator accepts it, and MySQL
| stores it happily — but its version nibble is zero, and
| Symfony\Component\Uid\Uuid::isValid(), which UserId uses, rejects it.
|
| Nothing noticed until authorization went live, because nothing had built a
| UserId from that row and every test builds its users with Str::uuid(). Then
| every admin request from the seeded account threw on the Gate check: a 500,
| not a 403, on a fresh install of the platform.
|
| So the rule is not "use UUIDs" — that was already true — but "a UUID written
| by hand must be one the value objects will accept". A literal that only
| looks right is worse than an obviously wrong one, because it survives review.
*/

/**
 * Files that legitimately contain UUID literals: fixtures, seeders and the
 * scripts that bootstrap an environment. Test files are included on purpose —
 * a test fixture with an unacceptable id fails in a way that looks like a bug
 * in the code under test.
 *
 * @return array<int, string>
 */
function uuidLiteralSources(): array
{
    $roots = [
        base_path('Modules'),
        base_path('database'),
        base_path('tests'),
        base_path('app'),
    ];

    $files = [];

    foreach ($roots as $root) {
        if (! is_dir($root)) {
            continue;
        }

        /** @var SplFileInfo $file */
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root)) as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }
    }

    foreach (['reset-admin.php', 'seed-qa-accounts.php'] as $script) {
        if (file_exists(base_path($script))) {
            $files[] = base_path($script);
        }
    }

    return $files;
}

test('every hand-written UUID is one the domain will accept', function (): void {
    $pattern = '/[\'"]([0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12})[\'"]/';

    $offenders = [];

    foreach (uuidLiteralSources() as $path) {
        // Two files name the bad id because naming it is their job: this one
        // explains it, and the migration exists to remove it. Exempting the
        // migration is checked for staleness below, so it cannot quietly
        // become cover for a new offender.
        if (str_ends_with($path, 'HardcodedUuidTest.php')) {
            continue;
        }

        if (str_ends_with($path, '_fix_bootstrap_admin_uuid.php')) {
            continue;
        }

        $contents = (string) file_get_contents($path);

        if (! preg_match_all($pattern, $contents, $matches)) {
            continue;
        }

        foreach (array_unique($matches[1]) as $uuid) {
            try {
                new UserId($uuid);
            } catch (InvalidArgumentException) {
                $offenders[] = str_replace(base_path().DIRECTORY_SEPARATOR, '', $path).': '.$uuid;
            }
        }
    }

    expect($offenders)->toBeEmpty(
        "UUID literals the domain would refuse:\n  ".implode("\n  ", $offenders)
    );
});

test('the specific shape that caused this is refused', function (): void {
    // Guards the guard: if UserId's validator were ever loosened to something
    // as permissive as Ramsey's, the scan above would pass while the original
    // fault came straight back.
    expect(fn () => new UserId('00000000-0000-0000-0000-000000000001'))
        ->toThrow(InvalidArgumentException::class);

    expect(fn () => new UserId('01920000-0000-7000-8000-000000000001'))
        ->not->toThrow(InvalidArgumentException::class);
});

test('the migration exemption still refers to the migration that earns it', function (): void {
    // An exemption is a liability the moment it stops being needed. If the
    // migration is ever deleted or renamed, this fails rather than leaving a
    // silent hole in the scan above.
    $migration = glob(base_path('Modules/Core/Database/Migrations/*_fix_bootstrap_admin_uuid.php'));

    expect($migration)->toHaveCount(1);
    expect((string) file_get_contents($migration[0]))
        ->toContain('00000000-0000-0000-0000-000000000001');
});
