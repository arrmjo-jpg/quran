<?php

declare(strict_types=1);

use Tests\Support\ModuleBoundary;

uses()->group('architecture');

/*
|--------------------------------------------------------------------------
| Global Modular Monolith & Clean Architecture Guards (ADR-002 / ADR-009 / ADR-012)
|--------------------------------------------------------------------------
|
| THESE GUARDS USED TO INSPECT ALMOST NOTHING, and they were green while doing
| it. All three globbed `**`, which PHP does not treat as recursive — it
| degrades to a single `*`. Measured before this rewrite:
|
|   'controllers do not import eloquent models directly'
|       Controllers/**\/*.php matched 0 of 37 controllers, because
|       controllers sit directly in Controllers/. It reported no assertions
|       and was marked risky, for its whole life.
|
|   'no module imports concrete internal classes ... outside Contracts'
|       Modules/X/**\/*.php matched 5 of ~40 files in Contestants. It passed,
|       which is worse: nobody re-asks a question that has been answered
|       green. It also carried a hardcoded list of fifteen module names, and
|       Organization — added in Epic 2 — was never added to it.
|
|   'domain layer classes do not import framework or eloquent classes'
|       Domain/**\/*.php happened to work, because domain files sit exactly
|       one level down. Correct by accident, and only for that layout.
|
| The scanning now lives in Tests\Support\ModuleBoundary so there is one
| implementation, and the module list is read from disk so a new module cannot
| be omitted from it.
|
| Run via: php artisan test --group=architecture
*/

test('the scan reaches the files it claims to check', function (): void {
    // The guard on the guards. Every assertion below is worthless if the
    // scan comes back empty, and that is exactly the failure this file was
    // shipped with — so it is now asserted rather than assumed.
    $modules = ModuleBoundary::modules();

    expect($modules)->toContain('Core', 'Contestants', 'Organization');
    expect(count($modules))->toBeGreaterThanOrEqual(16);

    $contestantFiles = ModuleBoundary::sourceFilesOf('Contestants');

    // The old glob found 5 here. Anything in that region means `**` is being
    // treated as non-recursive again.
    expect(count($contestantFiles))->toBeGreaterThan(25);

    $controllers = [];

    foreach ($modules as $module) {
        $controllers = array_merge(
            $controllers,
            ModuleBoundary::phpFilesUnder(base_path("Modules/$module/Presentation/HTTP/Controllers"))
        );
    }

    // The old glob found 0.
    expect(count($controllers))->toBeGreaterThan(30);
});

test('domain layer classes do not import framework or eloquent classes', function (): void {
    // NO MESSAGE ARGUMENT ON toContain, AND THAT IS NOT A STYLE CHOICE.
    // Pest's toContain is variadic: every extra argument is another needle,
    // not a failure message. `->not->toContain($needle, $message)` therefore
    // asserts "contains neither the needle NOR the message text" — and since
    // the message is never in the subject, the negation is satisfied and the
    // assertion PASSES WHATEVER THE FILE CONTAINS. Proven with a throwaway
    // test before this rewrite; it is why this guard reported 30 assertions
    // for Contestants while three of those files imported another module.
    //
    // Offenders are collected and named in one assertion at the end instead,
    // which also reports every file rather than stopping at the first.
    $forbidden = [
        'Illuminate\\Database\\Eloquent' => 'Eloquent',
        'Illuminate\\Http' => 'the HTTP layer',
        'Illuminate\\Support\\Facades' => 'Laravel facades',
    ];

    $offenders = [];
    $checked = 0;

    foreach (ModuleBoundary::modules() as $module) {
        foreach (ModuleBoundary::phpFilesUnder(base_path("Modules/$module/Domain")) as $file) {
            $checked++;
            $content = (string) file_get_contents($file);

            foreach ($forbidden as $needle => $label) {
                if (str_contains($content, $needle)) {
                    $offenders[] = ModuleBoundary::relativePath($file).' depends on '.$label;
                }
            }
        }
    }

    sort($offenders);

    expect($offenders)->toBeEmpty(
        'Domain classes must not depend on the framework (ADR-009/ADR-012). '
        ."Offenders:\n  ".implode("\n  ", $offenders)
    );

    // The scan has to have reached something, or the emptiness above is
    // meaningless — the exact failure mode this file shipped with.
    expect($checked)->toBeGreaterThan(0);
});

test('no module imports concrete internal classes from another module outside Contracts', function (): void {
    $unbaselined = [];

    foreach (ModuleBoundary::modules() as $module) {
        $unbaselined = array_merge($unbaselined, ModuleBoundary::unbaselinedViolationsIn($module));
    }

    sort($unbaselined);

    expect($unbaselined)->toBeEmpty(
        "New cross-module imports outside Contracts/ (ADR-002).\n"
        ."Add a Contracts method and a DTO, or — if the coupling is deliberate and\n"
        ."temporary — add the exact file and class to ModuleBoundary::BASELINE with\n"
        ."a reason. Wildcards are not permitted there.\n\n  "
        .implode("\n  ", $unbaselined)
    );
});

test('the baseline still describes violations that exist', function (): void {
    // A baseline nobody prunes stops meaning "what we still owe". If work
    // removes one of these imports, this fails until the entry goes with it,
    // so the list can only shrink.
    $stale = ModuleBoundary::staleBaselineEntries();

    expect($stale)->toBeEmpty(
        "ModuleBoundary::BASELINE lists imports that are no longer there.\n"
        ."The debt was paid — delete these entries.\n\n  "
        .implode("\n  ", $stale)
    );
});

test('controllers do not import eloquent models directly', function (): void {
    // Runs against every controller for the first time — the glob it used to
    // rely on matched none of the 37. Against the ADR-009/ADR-012 baseline,
    // which is separate from the ADR-002 one: a controller reaching into its
    // OWN module's tables breaks layering, not module boundaries.
    $offenders = ModuleBoundary::unbaselinedControllerModelImports();

    sort($offenders);

    expect($offenders)->toBeEmpty(
        'Controllers must not import Eloquent models (ADR-009/ADR-012) — use a '
        .'repository, a use case or a resource. If the import is deliberate and '
        .'temporary, add the exact file and class to '
        ."ModuleBoundary::CONTROLLER_MODEL_BASELINE with a reason.\n\n  "
        .implode("\n  ", $offenders)
    );
});

test('the controller baseline still describes imports that exist', function (): void {
    // The same anti-rot rule as the boundary baseline: paying the debt has to
    // shrink the list, so a removed import fails until its entry goes too.
    $stale = ModuleBoundary::staleControllerBaselineEntries();

    expect($stale)->toBeEmpty(
        'ModuleBoundary::CONTROLLER_MODEL_BASELINE lists imports that are no '
        ."longer there. The debt was paid — delete these entries.\n\n  "
        .implode("\n  ", $stale)
    );
});
