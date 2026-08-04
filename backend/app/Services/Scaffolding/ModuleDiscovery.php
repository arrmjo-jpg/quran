<?php

declare(strict_types=1);

namespace App\Services\Scaffolding;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * ModuleDiscovery
 *
 * Single Responsibility: Scans the Modules/ directory and returns
 * a list of valid, discoverable module names.
 *
 * This class performs NO registration. That is the sole responsibility
 * of ModuleRegistry.
 *
 * @see ModuleRegistry
 */
final class ModuleDiscovery
{
    /**
     * The absolute path to the Modules root directory.
     */
    private string $modulesPath;

    /**
     * Cache TTL in seconds (5 minutes in production, 0 in local/test).
     */
    private const CACHE_KEY = 'platform.discovered_modules';

    private const CACHE_TTL = 300;

    public function __construct()
    {
        $this->modulesPath = base_path('Modules');
    }

    /**
     * Discover all valid modules in the Modules/ directory.
     *
     * A valid module MUST have:
     *   - A directory under Modules/{ModuleName}/
     *   - A Providers/{ModuleName}ServiceProvider.php file
     *   - A module.json manifest file
     *
     * @return array<int, string> List of valid module names (e.g. ['Core', 'Countries', ...])
     */
    public function discover(): array
    {
        // Skip cache in local/testing environments for hot-reload
        if (app()->isLocal() || app()->runningUnitTests()) {
            return $this->scan();
        }

        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, fn (): array => $this->scan());
    }

    /**
     * Perform the actual filesystem scan.
     *
     * @return array<int, string>
     */
    private function scan(): array
    {
        if (! is_dir($this->modulesPath)) {
            return [];
        }

        $directories = array_filter(
            scandir($this->modulesPath) ?: [],
            fn (string $entry): bool => $entry !== '.' && $entry !== '..' && is_dir("{$this->modulesPath}/{$entry}")
        );

        $validModules = [];

        foreach ($directories as $moduleName) {
            if ($this->isValidModule($moduleName)) {
                $validModules[] = $moduleName;
            }
        }

        sort($validModules);

        return $validModules;
    }

    /**
     * Validate that a directory is a proper platform module.
     */
    private function isValidModule(string $moduleName): bool
    {
        // Must be PascalCase
        if ($moduleName !== Str::studly($moduleName)) {
            return false;
        }

        $moduleRoot = "{$this->modulesPath}/{$moduleName}";

        // Must have a ServiceProvider
        $providerFile = "{$moduleRoot}/Providers/{$moduleName}ServiceProvider.php";

        if (! file_exists($providerFile)) {
            return false;
        }

        // Must have a module manifest
        $manifestFile = "{$moduleRoot}/module.json";

        if (! file_exists($manifestFile)) {
            return false;
        }

        return true;
    }

    /**
     * Flush the discovery cache. Call this after running make:platform-module.
     */
    public function flushCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * Return the absolute path to the Modules directory.
     */
    public function getModulesPath(): string
    {
        return $this->modulesPath;
    }
}
