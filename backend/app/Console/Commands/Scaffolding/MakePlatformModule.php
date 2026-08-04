<?php

declare(strict_types=1);

namespace App\Console\Commands\Scaffolding;

use App\Services\Scaffolding\ModuleDiscovery;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * make:platform-module
 *
 * Generates the complete 26-directory structure for a new platform module,
 * creates its ServiceProvider, default route files, module manifest,
 * and flushes the ModuleDiscovery cache.
 *
 * Per ADR-011: All stubs are versioned under stubs/platform/v1/
 * and carry @stub-version 1.0.0 headers.
 *
 * Usage:
 *   php artisan make:platform-module {ModuleName}
 */
final class MakePlatformModule extends Command
{
    protected $signature = 'make:platform-module {name : The PascalCase module name (e.g. Contestants)}';

    protected $description = 'Generate the complete 26-directory structure for a new platform module (ADR-011)';

    /**
     * The 26 canonical directories per ADR-011.
     *
     * @var array<int, string>
     */
    private const DIRECTORIES = [
        'Domain/Entities',
        'Domain/Events',
        'Domain/Exceptions',
        'Domain/Repositories',
        'Domain/Rules',
        'Domain/Services',
        'Application/Commands',
        'Application/Queries',
        'Application/UseCases',
        'Application/DTOs',
        'Application/Listeners',
        'Infrastructure/Database/Models',
        'Infrastructure/Database/Repositories',
        'Infrastructure/Database/Factories',
        'Infrastructure/Database/Seeders',
        'Infrastructure/Services',
        'Presentation/HTTP/Controllers',
        'Presentation/HTTP/Requests',
        'Presentation/HTTP/Resources',
        'Presentation/HTTP/Middleware',
        'Presentation/CLI/Commands',
        'Contracts',
        'Database/Migrations',
        'Providers',
        'Routes',
        'Tests/Unit',
        'Tests/Feature',
        'Tests/Architecture',
        'lang/en',
        'lang/ar',
    ];

    public function handle(ModuleDiscovery $discovery): int
    {
        $name = $this->argument('name');

        if (! $this->isValidModuleName($name)) {
            $this->error("Module name must be PascalCase (e.g. 'Contestants'). Got: '{$name}'");

            return self::FAILURE;
        }

        $modulePath = base_path("Modules/{$name}");

        if (is_dir($modulePath)) {
            $this->error("Module '{$name}' already exists at: {$modulePath}");

            return self::FAILURE;
        }

        $this->info("🏗️  Scaffolding module: <comment>{$name}</comment>");
        $this->newLine();

        // 1. Create all 30 directories (26 canonical + lang dirs)
        $this->createDirectories($modulePath);

        // 2. Create ServiceProvider
        $this->createServiceProvider($modulePath, $name);

        // 3. Create Service Contract
        $this->createServiceContract($modulePath, $name);

        // 4. Create Route files
        $this->createRouteFiles($modulePath, $name);

        // 5. Create module manifest
        $this->createManifest($modulePath, $name);

        // 6. Create Architecture test
        $this->createArchitectureTest($modulePath, $name);

        // 7. Flush discovery cache
        $discovery->flushCache();

        $this->newLine();
        $this->info("✅  Module <comment>{$name}</comment> scaffolded successfully.");
        $this->line("   Path: <comment>{$modulePath}</comment>");
        $this->line('   Run <comment>composer dump-autoload</comment> to enable autoloading.');

        return self::SUCCESS;
    }

    private function createDirectories(string $modulePath): void
    {
        $this->line('  Creating directory structure...');

        foreach (self::DIRECTORIES as $dir) {
            $fullPath = "{$modulePath}/{$dir}";
            mkdir($fullPath, 0755, true);
            // Create .gitkeep so git tracks empty directories
            file_put_contents("{$fullPath}/.gitkeep", '');
        }

        $this->line('  <info>✓</info> 30 directories created');
    }

    private function createServiceProvider(string $modulePath, string $name): void
    {
        $stub = $this->loadStub('module-provider');
        $content = $this->replaceTokens($stub, $name);
        $file = "{$modulePath}/Providers/{$name}ServiceProvider.php";
        file_put_contents($file, $content);
        $this->line('  <info>✓</info> ServiceProvider created');
    }

    private function createServiceContract(string $modulePath, string $name): void
    {
        $stub = $this->loadStub('module-contract');
        $content = $this->replaceTokens($stub, $name);
        $file = "{$modulePath}/Contracts/{$name}ServiceContract.php";
        file_put_contents($file, $content);
        $this->line('  <info>✓</info> Service Contract interface created');
    }

    private function createRouteFiles(string $modulePath, string $name): void
    {
        $stub = $this->loadStub('module-routes');
        $content = $this->replaceTokens($stub, $name);
        file_put_contents("{$modulePath}/Routes/api.php", $content);
        file_put_contents("{$modulePath}/Routes/admin.php", $content);
        $this->line('  <info>✓</info> Route files created (api.php, admin.php)');
    }

    private function createManifest(string $modulePath, string $name): void
    {
        $manifest = json_encode([
            'name' => $name,
            'stub_version' => '1.0.0',
            'created_at' => now()->toIso8601String(),
            'description' => "{$name} module for the Quran Competition Platform.",
            'status' => 'active',
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        file_put_contents("{$modulePath}/module.json", $manifest."\n");
        $this->line('  <info>✓</info> module.json manifest created');
    }

    private function createArchitectureTest(string $modulePath, string $name): void
    {
        $stub = $this->loadStub('architecture-test');
        $content = $this->replaceTokens($stub, $name);
        $file = "{$modulePath}/Tests/Architecture/{$name}ArchitectureTest.php";
        file_put_contents($file, $content);
        // Remove the .gitkeep since we now have a real file
        @unlink("{$modulePath}/Tests/Architecture/.gitkeep");
        $this->line('  <info>✓</info> Architecture test created');
    }

    private function loadStub(string $stubName): string
    {
        $stubPath = base_path("stubs/platform/v1/{$stubName}.stub");

        if (! file_exists($stubPath)) {
            throw new \RuntimeException("Stub not found: {$stubPath}");
        }

        return file_get_contents($stubPath);
    }

    private function replaceTokens(string $stub, string $name): string
    {
        return str_replace(
            ['{{ModuleName}}', '{{moduleLower}}', '{{moduleSnake}}', '{{moduleSlug}}'],
            [$name, Str::lower($name), Str::snake($name), Str::slug($name)],
            $stub
        );
    }

    private function isValidModuleName(string $name): bool
    {
        return (bool) preg_match('/^[A-Z][a-zA-Z]+$/', $name);
    }
}
