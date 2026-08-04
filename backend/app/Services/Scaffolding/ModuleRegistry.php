<?php

declare(strict_types=1);

namespace App\Services\Scaffolding;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Log;

/**
 * ModuleRegistry
 *
 * Single Responsibility: Receives a list of discovered module names and
 * boots their ServiceProviders into the Laravel application container.
 *
 * This class performs NO discovery. That is the sole responsibility
 * of ModuleDiscovery.
 *
 * @see ModuleDiscovery
 */
final class ModuleRegistry
{
    public function __construct(
        private readonly Application $app,
    ) {}

    /**
     * Register all discovered modules by booting their ServiceProviders.
     *
     * @param  array<int, string>  $moduleNames  List of module names from ModuleDiscovery
     */
    public function register(array $moduleNames): void
    {
        foreach ($moduleNames as $moduleName) {
            $this->registerModule($moduleName);
        }
    }

    /**
     * Register a single module's ServiceProvider into the application.
     */
    private function registerModule(string $moduleName): void
    {
        $providerClass = "Modules\\{$moduleName}\\Providers\\{$moduleName}ServiceProvider";

        if (! class_exists($providerClass)) {
            Log::warning('[ModuleRegistry] Provider class not found — skipping module.', [
                'module' => $moduleName,
                'provider' => $providerClass,
            ]);

            return;
        }

        $this->app->register($providerClass);
    }

    /**
     * Check if a specific module is registered in the application.
     */
    public function isRegistered(string $moduleName): bool
    {
        $providerClass = "Modules\\{$moduleName}\\Providers\\{$moduleName}ServiceProvider";

        return isset($this->app->getLoadedProviders()[$providerClass]);
    }

    /**
     * Return all currently registered module provider class names.
     *
     * @return array<int, string>
     */
    public function getRegisteredProviders(): array
    {
        $loaded = $this->app->getLoadedProviders();

        return array_values(
            array_filter(
                array_keys($loaded),
                fn (string $class): bool => str_starts_with($class, 'Modules\\')
            )
        );
    }
}
