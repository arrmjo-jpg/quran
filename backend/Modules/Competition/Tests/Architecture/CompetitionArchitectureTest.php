<?php

// @stub-version 1.1.0
// @generated-by make:platform-module
// @adr ADR-002, ADR-009, ADR-011

declare(strict_types=1);

uses()->group('competition', 'architecture');

/*
|--------------------------------------------------------------------------
| Competition Module — Architecture Tests
|--------------------------------------------------------------------------
| These tests enforce ADR-002 module boundary and ADR-009 Clean Architecture
| layer isolation rules at the test suite level.
|
| Run with: php artisan test --filter CompetitionArchitecture
*/

/**
 * Every .php file under $dir, recursively.
 *
 * glob() has no recursive `**` — it silently degrades to a single `*` and
 * matches one directory level only, so the previous implementation scanned
 * 5 of the module's 117 files and passed regardless of what the rest did.
 *
 * @return array<int, string>
 */
function competitionPhpFiles(string $dir): array
{
    if (! is_dir($dir)) {
        return [];
    }

    $files = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
            $files[] = str_replace('\\', '/', $file->getPathname());
        }
    }

    sort($files);

    return $files;
}

/**
 * Fully-qualified names imported by a file: `use` statements plus in-class
 * trait usage, with group syntax expanded and `as` aliases stripped.
 *
 * @return array<int, string>
 */
function competitionImportsIn(string $file): array
{
    $content = file_get_contents($file) ?: '';

    preg_match_all('/^[ \t]*use[ \t]+(?:function[ \t]+|const[ \t]+)?([^;]+);/m', $content, $matches);

    $imports = [];

    foreach ($matches[1] as $statement) {
        $statement = trim(preg_replace('/\s+/', ' ', $statement) ?? '');

        // Group syntax: `use Modules\Foo\{Bar, Baz as Qux};`
        if (preg_match('/^(.*\\\\)\{(.+)\}$/', $statement, $group) === 1) {
            foreach (explode(',', $group[2]) as $member) {
                $imports[] = $group[1].trim($member);
            }

            continue;
        }

        // Multiple trait usage: `use Bar, Baz;`
        foreach (explode(',', $statement) as $member) {
            $imports[] = trim($member);
        }
    }

    return array_values(array_filter(array_map(
        static fn (string $import): string => trim(preg_split('/ as /i', $import)[0] ?? ''),
        $imports
    )));
}

/**
 * Cross-module imports that are sanctioned despite crossing a boundary.
 *
 * Modules\Core\Domain\* holds shared, pure-PHP domain primitives with zero
 * framework or infrastructure dependency — notably the HasDomainEvents trait,
 * whose own docblock states every aggregate should use it rather than
 * duplicating the same three members. Importing it is the intended design,
 * not a boundary violation.
 *
 * @return array<int, string>
 */
function competitionSanctionedImportPrefixes(): array
{
    return [
        'Modules\\Core\\Domain\\',
    ];
}

/**
 * True when $import reaches into another module in a way ADR-002 forbids:
 * anything outside Modules\Competition that is neither another module's
 * Contracts\ namespace nor an explicitly sanctioned prefix.
 */
function competitionIsBoundaryViolation(string $import): bool
{
    if (preg_match('/^Modules\\\\(\w+)\\\\/', $import, $match) !== 1) {
        return false;
    }

    if ($match[1] === 'Competition') {
        return false;
    }

    if (preg_match('/^Modules\\\\\w+\\\\Contracts\\\\/', $import) === 1) {
        return false;
    }

    foreach (competitionSanctionedImportPrefixes() as $prefix) {
        if (str_starts_with($import, $prefix)) {
            return false;
        }
    }

    return true;
}

/**
 * Path relative to Modules/, for readable failure messages.
 */
function competitionRelativePath(string $file): string
{
    return str_replace(str_replace('\\', '/', base_path('Modules')).'/', '', $file);
}

test('Competition domain layer has no framework dependencies', function (): void {
    $files = competitionPhpFiles(base_path('Modules/Competition/Domain'));

    expect($files)->not->toBeEmpty('Expected to scan Domain files, found none.');

    foreach ($files as $file) {
        $imports = competitionImportsIn($file);
        $relative = competitionRelativePath($file);

        $frameworkImports = array_values(array_filter(
            $imports,
            static fn (string $import): bool => str_starts_with($import, 'Illuminate\\')
        ));

        expect($frameworkImports)->toBeEmpty(sprintf(
            'Domain file %s must not import Laravel framework classes, found: %s',
            $relative,
            implode(', ', $frameworkImports)
        ));

        // Note: same-module imports are legitimate — a Domain entity referencing
        // its own module's events is exactly what the layer is for. Only imports
        // that cross the module boundary are checked here.
        $crossModuleImports = array_values(array_filter(
            $imports,
            static fn (string $import): bool => competitionIsBoundaryViolation($import)
        ));

        expect($crossModuleImports)->toBeEmpty(sprintf(
            'Domain file %s must not import other modules, found: %s',
            $relative,
            implode(', ', $crossModuleImports)
        ));
    }
})->skip(fn (): bool => ! is_dir(base_path('Modules/Competition/Domain')));

test('Competition does not import concrete classes from other modules', function (): void {
    // Database/ and Tests/ are excluded: migrations legitimately extend the
    // shared PlatformBlueprint schema helper, and tests legitimately arrange
    // fixtures with other modules' Eloquent models. Neither is runtime module
    // coupling, so ADR-002 boundaries do not apply to them.
    $excludedDirectories = ['Database', 'Tests'];

    $moduleRoot = str_replace('\\', '/', base_path('Modules/Competition'));
    $files = array_values(array_filter(
        competitionPhpFiles($moduleRoot),
        static function (string $file) use ($moduleRoot, $excludedDirectories): bool {
            foreach ($excludedDirectories as $directory) {
                if (str_starts_with($file, "{$moduleRoot}/{$directory}/")) {
                    return false;
                }
            }

            return true;
        }
    ));

    expect($files)->not->toBeEmpty('Expected to scan module source files, found none.');

    foreach ($files as $file) {
        // Cross-module imports that bypass Contracts/
        $violations = array_values(array_filter(
            competitionImportsIn($file),
            static fn (string $import): bool => competitionIsBoundaryViolation($import)
        ));

        expect($violations)->toBeEmpty(sprintf(
            'File %s imports concrete classes from other modules: %s',
            competitionRelativePath($file),
            implode(', ', $violations)
        ));
    }
})->skip(fn (): bool => ! is_dir(base_path('Modules/Competition')));
