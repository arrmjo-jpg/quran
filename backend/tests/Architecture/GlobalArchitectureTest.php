<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\Group;

uses()->group('architecture');

/*
|--------------------------------------------------------------------------
| Global Modular Monolith & Clean Architecture Guards (ADR-002 / ADR-009 / ADR-012)
|--------------------------------------------------------------------------
| These binding architectural tests strictly prevent layer contamination
| and enforce module boundary encapsulation across all 15 modules.
|
| Run via: php artisan test --group=architecture
*/

test('domain layer classes do not import framework or eloquent classes', function (): void {
    $domainFiles = glob(base_path('Modules/*/Domain/**/*.php'), (defined('GLOB_BRACE') ? GLOB_BRACE : 0)) ?: [];

    foreach ($domainFiles as $file) {
        $content = file_get_contents($file);

        expect($content)
            ->not->toContain('Illuminate\\Database\\Eloquent', "File {$file} must not depend on Eloquent.")
            ->not->toContain('Illuminate\\Http', "File {$file} must not depend on HTTP layer.")
            ->not->toContain('Illuminate\\Support\\Facades', "File {$file} must not depend on Laravel Facades.");
    }
});

test('no module imports concrete internal classes from another module outside Contracts', function (): void {
    $modules = ['Core', 'Countries', 'Contestants', 'Applications', 'Judges', 'Competition', 'Evaluations', 'Videos', 'Media', 'Streaming', 'Content', 'Sponsors', 'Notifications', 'Reports', 'Search'];

    foreach ($modules as $sourceModule) {
        $files = glob(base_path("Modules/{$sourceModule}/**/*.php"), (defined('GLOB_BRACE') ? GLOB_BRACE : 0)) ?: [];

        foreach ($files as $file) {
            // Skip contract interfaces themselves
            if (str_contains($file, '/Contracts/')) {
                continue;
            }

            $content = file_get_contents($file);

            foreach ($modules as $targetModule) {
                if ($sourceModule === $targetModule) {
                    continue;
                }

                // Pattern matching imports from targetModule not inside Contracts
                preg_match_all("/use Modules\\\\{$targetModule}\\\\(?!Contracts\\\\)/", $content, $matches);

                expect($matches[0])
                    ->toBeEmpty("File {$file} in module {$sourceModule} illegally imports concrete class from {$targetModule} outside Contracts.");
            }
        }
    }
});

test('controllers do not import eloquent models directly', function (): void {
    $controllerFiles = glob(base_path('Modules/*/Presentation/HTTP/Controllers/**/*.php'), (defined('GLOB_BRACE') ? GLOB_BRACE : 0)) ?: [];

    foreach ($controllerFiles as $file) {
        $content = file_get_contents($file);

        expect($content)
            ->not->toContain('\\Models\\', "Controller {$file} must not import Eloquent Models directly per ADR-009/ADR-012. Use JsonResource or DTO.");
    }
});
