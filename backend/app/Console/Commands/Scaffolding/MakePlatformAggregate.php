<?php

declare(strict_types=1);

namespace App\Console\Commands\Scaffolding;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * make:platform-aggregate
 *
 * Generates the full Aggregate Root vertical slice for a module entity:
 *   1. Domain/Entities/{EntityName}.php                           (Aggregate Root)
 *   2. Domain/Repositories/{EntityName}RepositoryContract.php    (Interface)
 *   3. Infrastructure/Database/Models/{EntityName}Model.php      (Eloquent Model)
 *   4. Infrastructure/Database/Repositories/{EntityName}Repository.php (Implementation)
 *   5. Infrastructure/Database/Factories/{EntityName}Factory.php (Model Factory)
 *   6. Tests/Unit/{EntityName}Test.php                           (Pest Unit Test)
 *
 * Per ADR-011: All stubs are versioned under stubs/platform/v1/
 *
 * Usage:
 *   php artisan make:platform-aggregate {ModuleName} {EntityName} {--table=}
 */
final class MakePlatformAggregate extends Command
{
    protected $signature = 'make:platform-aggregate
        {module  : The PascalCase module name (e.g. Contestants)}
        {entity  : The PascalCase entity name (e.g. Contestant)}
        {--table= : Override the DB table name (default: snake_plural of entity)}';

    protected $description = 'Generate a complete Aggregate Root vertical slice for a module entity (ADR-011)';

    public function handle(): int
    {
        $module = $this->argument('module');
        $entity = $this->argument('entity');
        $table = $this->option('table') ?? Str::snake(Str::plural($entity));

        $modulePath = base_path("Modules/{$module}");

        if (! is_dir($modulePath)) {
            $this->error("Module '{$module}' does not exist. Run: php artisan make:platform-module {$module}");

            return self::FAILURE;
        }

        $this->info("🧱  Generating Aggregate: <comment>{$entity}</comment> in module <comment>{$module}</comment>");
        $this->newLine();

        $tokens = $this->buildTokens($module, $entity, $table);

        $this->createDomainEntity($modulePath, $entity, $tokens);
        $this->createRepositoryContract($modulePath, $entity, $tokens);
        $this->createEloquentModel($modulePath, $entity, $tokens);
        $this->createRepository($modulePath, $entity, $tokens);
        $this->createFactory($modulePath, $entity, $tokens);
        $this->createUnitTest($modulePath, $entity, $tokens);

        $this->newLine();
        $this->info("✅  Aggregate <comment>{$entity}</comment> scaffolded in <comment>{$module}</comment>. Table: <comment>{$table}</comment>");

        return self::SUCCESS;
    }

    private function createDomainEntity(string $modulePath, string $entity, array $tokens): void
    {
        $content = $this->render('aggregate-entity', $tokens);
        file_put_contents("{$modulePath}/Domain/Entities/{$entity}.php", $content);
        $this->line("  <info>✓</info> Domain Entity: Domain/Entities/{$entity}.php");
    }

    private function createRepositoryContract(string $modulePath, string $entity, array $tokens): void
    {
        $content = $this->render('aggregate-repository-contract', $tokens);
        file_put_contents("{$modulePath}/Domain/Repositories/{$entity}RepositoryContract.php", $content);
        $this->line("  <info>✓</info> Repository Contract: Domain/Repositories/{$entity}RepositoryContract.php");
    }

    private function createEloquentModel(string $modulePath, string $entity, array $tokens): void
    {
        $content = $this->render('aggregate-model', $tokens);
        file_put_contents("{$modulePath}/Infrastructure/Database/Models/{$entity}Model.php", $content);
        $this->line("  <info>✓</info> Eloquent Model: Infrastructure/Database/Models/{$entity}Model.php");
    }

    private function createRepository(string $modulePath, string $entity, array $tokens): void
    {
        $content = $this->render('aggregate-repository', $tokens);
        file_put_contents("{$modulePath}/Infrastructure/Database/Repositories/{$entity}Repository.php", $content);
        $this->line("  <info>✓</info> Repository Impl: Infrastructure/Database/Repositories/{$entity}Repository.php");
    }

    private function createFactory(string $modulePath, string $entity, array $tokens): void
    {
        $content = $this->render('aggregate-factory', $tokens);
        file_put_contents("{$modulePath}/Infrastructure/Database/Factories/{$entity}Factory.php", $content);
        $this->line("  <info>✓</info> Model Factory: Infrastructure/Database/Factories/{$entity}Factory.php");
    }

    private function createUnitTest(string $modulePath, string $entity, array $tokens): void
    {
        $content = $this->render('aggregate-unit-test', $tokens);
        file_put_contents("{$modulePath}/Tests/Unit/{$entity}Test.php", $content);
        $this->line("  <info>✓</info> Unit Test: Tests/Unit/{$entity}Test.php");
    }

    private function render(string $stubName, array $tokens): string
    {
        $stubPath = base_path("stubs/platform/v1/{$stubName}.stub");

        if (! file_exists($stubPath)) {
            throw new \RuntimeException("Stub not found: {$stubPath}");
        }

        $stub = file_get_contents($stubPath);

        return str_replace(array_keys($tokens), array_values($tokens), $stub);
    }

    /** @return array<string, string> */
    private function buildTokens(string $module, string $entity, string $table): array
    {
        return [
            '{{ModuleName}}' => $module,
            '{{moduleLower}}' => Str::lower($module),
            '{{moduleSnake}}' => Str::snake($module),
            '{{EntityName}}' => $entity,
            '{{entityLower}}' => Str::lower($entity),
            '{{entitySnake}}' => Str::snake($entity),
            '{{entityPlural}}' => Str::plural(Str::snake($entity)),
            '{{tableName}}' => $table,
        ];
    }
}
