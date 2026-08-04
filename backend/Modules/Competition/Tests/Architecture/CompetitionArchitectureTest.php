<?php

// @stub-version 1.0.0
// @generated-by make:platform-module
// @adr ADR-009, ADR-011

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

test('Competition domain layer has no framework dependencies', function (): void {
    $files = glob(base_path('Modules/Competition/Domain/**/*.php')) ?: [];

    foreach ($files as $file) {
        $content = file_get_contents($file);
        expect($content)
            ->not->toContain('use Illuminate\\', "Domain file {$file} must not import Laravel framework classes.")
            ->not->toContain('use Modules\\', "Domain file {$file} must not import other modules.");
    }
})->skip(fn () => ! is_dir(base_path('Modules/Competition/Domain')));

test('Competition does not import concrete classes from other modules', function (): void {
    $moduleFiles = glob(base_path('Modules/Competition/**/*.php'), (defined('GLOB_BRACE') ? GLOB_BRACE : 0)) ?: [];

    foreach ($moduleFiles as $file) {
        $content = file_get_contents($file);

        // Scan for cross-module imports that bypass Contracts/
        preg_match_all('/use Modules\\\\(\w+)\\\\(?!Contracts)/', $content, $matches);
        $violations = array_filter($matches[1], fn (string $m): bool => $m !== 'Competition');

        expect($violations)
            ->toBeEmpty("File {$file} imports concrete classes from: ".implode(', ', $violations));
    }
})->skip(fn () => empty(glob(base_path('Modules/Competition/**/*.php'), (defined('GLOB_BRACE') ? GLOB_BRACE : 0))));
