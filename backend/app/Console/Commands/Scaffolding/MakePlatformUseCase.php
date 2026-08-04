<?php

declare(strict_types=1);

namespace App\Console\Commands\Scaffolding;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * make:platform-usecase
 *
 * Generates the complete Use Case vertical slice for a module:
 *   1. Application/Commands/{UseCaseName}Command.php  (Readonly DTO)
 *   2. Application/UseCases/{UseCaseName}UseCase.php  (Execution Logic)
 *   3. Presentation/HTTP/Controllers/{UseCaseName}Controller.php (Thin Controller)
 *   4. Presentation/HTTP/Requests/{UseCaseName}Request.php (Form Request)
 *   5. Tests/Feature/{UseCaseName}Test.php (Pest Feature Test stub)
 *
 * Per ADR-011: All stubs are versioned under stubs/platform/v1/
 *
 * Usage:
 *   php artisan make:platform-usecase {ModuleName} {UseCaseName} {EntityName}
 */
final class MakePlatformUseCase extends Command
{
    protected $signature = 'make:platform-usecase
        {module : The PascalCase module name (e.g. Contestants)}
        {usecase : The PascalCase use case name (e.g. RegisterContestant)}
        {entity : The PascalCase entity/aggregate name (e.g. Contestant)}';

    protected $description = 'Generate a complete Use Case vertical slice for a module (ADR-011)';

    public function handle(): int
    {
        $module = $this->argument('module');
        $usecase = $this->argument('usecase');
        $entity = $this->argument('entity');

        $modulePath = base_path("Modules/{$module}");

        if (! is_dir($modulePath)) {
            $this->error("Module '{$module}' does not exist. Run: php artisan make:platform-module {$module}");

            return self::FAILURE;
        }

        $this->info("⚡  Generating Use Case: <comment>{$usecase}</comment> in module <comment>{$module}</comment>");
        $this->newLine();

        $this->createCommand($modulePath, $module, $usecase, $entity);
        $this->createUseCase($modulePath, $module, $usecase, $entity);
        $this->createController($modulePath, $module, $usecase, $entity);
        $this->createRequest($modulePath, $module, $usecase);
        $this->createFeatureTest($modulePath, $module, $usecase, $entity);

        $this->newLine();
        $this->info("✅  UseCase <comment>{$usecase}</comment> scaffolded successfully in <comment>{$module}</comment>.");

        return self::SUCCESS;
    }

    private function createCommand(string $modulePath, string $module, string $usecase, string $entity): void
    {
        $content = $this->render('usecase-command', $module, $usecase, $entity);
        $file = "{$modulePath}/Application/Commands/{$usecase}Command.php";
        file_put_contents($file, $content);
        $this->line("  <info>✓</info> Command DTO: Application/Commands/{$usecase}Command.php");
    }

    private function createUseCase(string $modulePath, string $module, string $usecase, string $entity): void
    {
        $content = $this->render('usecase', $module, $usecase, $entity);
        $file = "{$modulePath}/Application/UseCases/{$usecase}UseCase.php";
        file_put_contents($file, $content);
        $this->line("  <info>✓</info> UseCase: Application/UseCases/{$usecase}UseCase.php");
    }

    private function createController(string $modulePath, string $module, string $usecase, string $entity): void
    {
        $content = $this->render('usecase-controller', $module, $usecase, $entity);
        $file = "{$modulePath}/Presentation/HTTP/Controllers/{$usecase}Controller.php";
        file_put_contents($file, $content);
        $this->line("  <info>✓</info> Controller: Presentation/HTTP/Controllers/{$usecase}Controller.php");
    }

    private function createRequest(string $modulePath, string $module, string $usecase): void
    {
        $content = $this->render('request', $module, $usecase, '');
        $file = "{$modulePath}/Presentation/HTTP/Requests/{$usecase}Request.php";
        file_put_contents($file, $content);
        $this->line("  <info>✓</info> Form Request: Presentation/HTTP/Requests/{$usecase}Request.php");
    }

    private function createFeatureTest(string $modulePath, string $module, string $usecase, string $entity): void
    {
        $content = $this->render('test', $module, $usecase, $entity);
        $file = "{$modulePath}/Tests/Feature/{$usecase}Test.php";
        file_put_contents($file, $content);
        $this->line("  <info>✓</info> Feature Test: Tests/Feature/{$usecase}Test.php");
    }

    private function render(string $stubName, string $module, string $usecase, string $entity): string
    {
        $stubPath = base_path("stubs/platform/v1/{$stubName}.stub");

        if (! file_exists($stubPath)) {
            throw new \RuntimeException("Stub not found: {$stubPath}");
        }

        $stub = file_get_contents($stubPath);

        return str_replace(
            [
                '{{ModuleName}}', '{{moduleLower}}', '{{moduleSnake}}',
                '{{UseCaseName}}', '{{useCaseLower}}', '{{useCaseSnake}}',
                '{{EntityName}}', '{{entityLower}}', '{{entitySnake}}',
                '{{endpoint}}',
            ],
            [
                $module, Str::lower($module), Str::snake($module),
                $usecase, Str::camel($usecase), Str::snake($usecase),
                $entity, Str::lower($entity), Str::snake($entity),
                Str::plural(Str::slug(Str::snake($usecase))),
            ],
            $stub
        );
    }
}
