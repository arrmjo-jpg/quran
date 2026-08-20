<?php

// @stub-version 1.0.0
// @generated-by make:platform-module
// @adr ADR-009, ADR-011

declare(strict_types=1);

uses()->group('organization', 'architecture');

/*
|--------------------------------------------------------------------------
| Organization Module — Architecture Tests
|--------------------------------------------------------------------------
| These tests enforce ADR-002 module boundary and ADR-009 Clean Architecture
| layer isolation rules at the test suite level.
|
| Run with: php artisan test --filter OrganizationArchitecture
*/

test('Organization domain layer has no framework dependencies', function (): void {
    $files = glob(base_path('Modules/Organization/Domain/**/*.php')) ?: [];

    foreach ($files as $file) {
        $content = file_get_contents($file);
        expect($content)
            ->not->toContain('use Illuminate\\', "Domain file {$file} must not import Laravel framework classes.")
            ->not->toContain('use Modules\\', "Domain file {$file} must not import other modules.");
    }
})->skip(fn () => ! is_dir(base_path('Modules/Organization/Domain')));

/**
 * Every PHP file of PRODUCTION code in the module.
 *
 * glob() cannot express this. Its `**` is not a recursive wildcard — PHP
 * treats it as a single `*`, so `Modules/Organization/** /*.php` enumerated
 * exactly one directory level: four files, none of them the Domain,
 * Application, Infrastructure or Presentation code the boundary rule exists
 * to police. The recursive iterators below are the same enumeration
 * HardcodedUuidTest uses for the same reason.
 *
 * TESTS ARE EXCLUDED — ADR-002 §11, "Scope of the Rule: Production Code
 * Only". A feature test must create a user, grant it a role and seed the
 * permission catalogue before it can assert anything, and all three are owned
 * by Core with no contract exposing them. Holding Tests/ to the rule would
 * make it a requirement no test could satisfy. The exemption stops here: it
 * does not make production logic acceptable by moving it into a helper under
 * this directory.
 *
 * @return array<int, string>
 */
function organizationModuleFiles(): array
{
    $root = base_path('Modules/Organization');

    if (! is_dir($root)) {
        return [];
    }

    $testsDir = $root.DIRECTORY_SEPARATOR.'Tests'.DIRECTORY_SEPARATOR;

    $files = [];

    /** @var SplFileInfo $file */
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root)) as $file) {
        if (! $file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }

        // Matched on the path prefix rather than on the string "Tests"
        // appearing anywhere: a production class legitimately named
        // TestimonialController would otherwise exempt itself.
        if (str_starts_with($file->getPathname(), $testsDir)) {
            continue;
        }

        $files[] = $file->getPathname();
    }

    return $files;
}

test('Organization does not import concrete classes from other modules', function (): void {
    // Collected rather than asserted per file: asserting inside the loop stops
    // at the first offender, which tells you nothing about how far the problem
    // spreads. One assertion at the end names every file at once.
    $offenders = [];

    foreach (organizationModuleFiles() as $file) {
        $content = (string) file_get_contents($file);

        // Scan for cross-module imports that bypass Contracts/
        preg_match_all('/use Modules\\\\(\w+)\\\\(?!Contracts)/', $content, $matches);
        $violations = array_unique(array_filter($matches[1], fn (string $m): bool => $m !== 'Organization'));

        if ($violations === []) {
            continue;
        }

        $relative = str_replace(base_path().DIRECTORY_SEPARATOR, '', $file);
        $offenders[] = str_replace('\\', '/', $relative).' imports concrete classes from: '.implode(', ', $violations);
    }

    expect($offenders)->toBeEmpty(
        "Cross-module imports that bypass Contracts/:\n  ".implode("\n  ", $offenders)
    );
})->skip(fn () => organizationModuleFiles() === []);
