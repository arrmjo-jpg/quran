<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\Scaffolding\ModuleDiscovery;
use App\Services\Scaffolding\ModuleRegistry;
use Illuminate\Support\ServiceProvider;

/**
 * ModuleServiceProvider
 *
 * Root bootstrapper for the platform's Modular Monolith architecture.
 * Registers and boots all discovered module ServiceProviders.
 */
final class ModuleServiceProvider extends ServiceProvider
{
    private ?array $discoveredModules = null;

    public function register(): void
    {
        $discovery = new ModuleDiscovery;
        $registry = new ModuleRegistry($this->app);

        $this->app->singleton(ModuleDiscovery::class, fn () => $discovery);
        $this->app->singleton(ModuleRegistry::class, fn () => $registry);

        $this->discoveredModules = $discovery->discover();

        $registry->register($this->discoveredModules);
    }

    public function boot(): void
    {
        // Handled via individual registered Module ServiceProviders
    }
}
