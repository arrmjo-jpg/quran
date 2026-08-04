<?php

declare(strict_types=1);

namespace App\Console\Commands\Scaffolding;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * make:platform-contract
 *
 * Generates a public Contract interface in a module's Contracts/ directory
 * and automatically adds the binding stub to the module's ServiceProvider.
 *
 * Per ADR-002: Modules ONLY export interfaces via their Contracts/ directory.
 * Per ADR-011: All stubs are versioned under stubs/platform/v1/
 *
 * Usage:
 *   php artisan make:platform-contract {ModuleName} {ContractName}
 *
 * Example:
 *   php artisan make:platform-contract Contestants ContestantQueryService
 */
final class MakePlatformContract extends Command
{
    protected $signature = 'make:platform-contract
        {module   : The PascalCase module name (e.g. Contestants)}
        {contract : The PascalCase contract name (e.g. ContestantQueryService)}';

    protected $description = 'Generate a public Contract interface in Contracts/ and wire it to the ServiceProvider (ADR-002 / ADR-011)';

    public function handle(): int
    {
        $module = $this->argument('module');
        $contract = $this->argument('contract');

        $modulePath = base_path("Modules/{$module}");

        if (! is_dir($modulePath)) {
            $this->error("Module '{$module}' does not exist. Run: php artisan make:platform-module {$module}");

            return self::FAILURE;
        }

        $contractFile = "{$modulePath}/Contracts/{$contract}.php";

        if (file_exists($contractFile)) {
            $this->error("Contract '{$contract}' already exists in Modules/{$module}/Contracts/");

            return self::FAILURE;
        }

        $this->info("📜  Generating Contract: <comment>{$contract}</comment> in module <comment>{$module}</comment>");
        $this->newLine();

        $tokens = $this->buildTokens($module, $contract);

        $this->createContractInterface($modulePath, $contract, $contractFile, $tokens);
        $this->appendBindingToProvider($modulePath, $module, $contract, $tokens);

        $this->newLine();
        $this->info("✅  Contract <comment>{$contract}</comment> created in <comment>Modules/{$module}/Contracts/</comment>.");
        $this->line("   Binding stub added to <comment>Providers/{$module}ServiceProvider.php</comment> — implement the concrete class and update the binding.");

        return self::SUCCESS;
    }

    private function createContractInterface(string $modulePath, string $contract, string $file, array $tokens): void
    {
        $content = $this->render('contract', $tokens);
        file_put_contents($file, $content);
        $this->line("  <info>✓</info> Contract Interface: Contracts/{$contract}.php");
    }

    private function appendBindingToProvider(string $modulePath, string $module, string $contract, array $tokens): void
    {
        $providerFile = "{$modulePath}/Providers/{$module}ServiceProvider.php";

        if (! file_exists($providerFile)) {
            $this->warn('  ⚠️  ServiceProvider not found — skipping auto-binding.');

            return;
        }

        // Inject the binding comment into the register() method
        $providerContent = file_get_contents($providerFile);
        $bindingLine = "\n        // TODO: Bind {$contract}\n        // \$this->app->singleton({$contract}::class, Concrete{$contract}::class);";

        $providerContent = str_replace(
            'public function register(): void
    {',
            "public function register(): void\n    {{$bindingLine}",
            $providerContent
        );

        file_put_contents($providerFile, $providerContent);
        $this->line("  <info>✓</info> Binding stub injected into Providers/{$module}ServiceProvider.php");
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
    private function buildTokens(string $module, string $contract): array
    {
        return [
            '{{ModuleName}}' => $module,
            '{{moduleLower}}' => Str::lower($module),
            '{{moduleSnake}}' => Str::snake($module),
            '{{ContractName}}' => $contract,
            '{{contractLower}}' => Str::lower($contract),
            '{{contractSnake}}' => Str::snake($contract),
        ];
    }
}
