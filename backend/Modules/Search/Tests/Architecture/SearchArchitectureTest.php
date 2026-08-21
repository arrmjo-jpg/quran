<?php

// @stub-version 1.0.0
// @generated-by make:platform-module
// @adr ADR-009, ADR-011

declare(strict_types=1);

use Tests\Support\ModuleBoundary;

uses()->group('search', 'architecture');

/*
|--------------------------------------------------------------------------
| Search Module — Architecture Tests
|--------------------------------------------------------------------------
| ADR-002 module boundaries and ADR-009 layer isolation, for this module.
|
| BOTH CHECKS USED TO BE INCAPABLE OF FAILING, for two independent reasons,
| and both were green the whole time:
|
|   * they globbed `**`, which PHP does not treat as recursive — the pattern
|     degrades to a single `*` — so `Modules/Search/**` reached exactly one
|     directory level and missed the Domain, Application, Infrastructure and
|     Presentation code the rules exist to police;
|
|   * they passed a "failure message" to toContain, which Pest treats as an
|     additional needle. `->not->toContain($needle, $message)` therefore
|     asserts "contains neither" — and since the message text is never in the
|     file, the negation was satisfied no matter what the file imported.
|
| The scanning now lives in Tests\Support\ModuleBoundary, so there is one
| implementation shared by every module and by the global guards.
|
| Run with: php artisan test --filter SearchArchitecture
*/

test('Search domain layer has no framework dependencies', function (): void {
    // Collected and asserted once, rather than asserted inside the loop:
    // a per-file assertion stops at the first offender and says nothing
    // about how far the problem spreads.
    $offenders = [];

    foreach (ModuleBoundary::phpFilesUnder(base_path('Modules/Search/Domain')) as $file) {
        if (str_contains((string) file_get_contents($file), 'use Illuminate')) {
            $offenders[] = ModuleBoundary::relativePath($file);
        }
    }

    sort($offenders);

    expect($offenders)->toBeEmpty(
        'Domain classes must not import the framework (ADR-009/ADR-012). '
        ."Offenders:\n  ".implode("\n  ", $offenders)
    );
})->skip(
    fn (): bool => ModuleBoundary::phpFilesUnder(base_path('Modules/Search/Domain')) === [],
    'Search has no Domain layer yet. Skipped out loud rather than passing over an empty scan.'
);

test('Search does not import concrete classes from other modules', function (): void {
    // Cross-module imports that bypass Contracts/, minus the platform
    // exemptions and minus the recorded baseline. Anything left is new.
    $offenders = ModuleBoundary::unbaselinedViolationsIn('Search');

    sort($offenders);

    expect($offenders)->toBeEmpty(
        'Cross-module imports that bypass Contracts/ (ADR-002). Add a Contracts '
        .'method and a DTO, or record the exact file and class in '
        ."ModuleBoundary::BASELINE with a reason.\n\n  "
        .implode("\n  ", $offenders)
    );
});
