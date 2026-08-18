<?php

// @stub-version 1.0.0
// @generated-by make:platform-module
// @adr ADR-011

declare(strict_types=1);

namespace Modules\Core\Providers;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Modules\Core\Domain\Repositories\RoleRepositoryContract;
use Modules\Core\Domain\Repositories\UserRepositoryContract;
use Modules\Core\Infrastructure\Database\Models\UserModel;
use Modules\Core\Infrastructure\Database\Repositories\RoleRepository;
use Modules\Core\Infrastructure\Database\Repositories\UserRepository;
use Modules\Core\Infrastructure\Permissions\AuthorizationService;
use Modules\Core\Infrastructure\Permissions\PermissionCatalog;

final class CoreServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(
            UserRepositoryContract::class,
            UserRepository::class
        );

        $this->app->singleton(
            RoleRepositoryContract::class,
            RoleRepository::class
        );
    }

    public function boot(): void
    {
        $this->registerMigrations();
        $this->registerRoutes();
        $this->registerTranslations();
        $this->registerGates();
    }

    /**
     * Every catalogue permission becomes a Gate ability of the same name,
     * so a caller writes `$this->authorize('seasons.create')` and the
     * string it passes is the same string the catalogue defines.
     *
     * Registering them from the catalogue rather than by hand is what
     * keeps the two from drifting: a permission cannot exist without its
     * ability, and an ability cannot exist without its permission.
     *
     * NOTHING CALLS THESE YET. Registration is inert until the activation
     * step wires the API to them — deliberately, so that switching
     * authorization on is a single reviewable change rather than
     * something that leaked in over several commits.
     *
     * No Gate::before is defined. A super-admin bypass would mean the
     * one account that matters most is never actually checked, and
     * super_admin already holds every permission by enumeration
     * (ADR-015 §5), so a bypass would buy nothing and hide any bug in
     * resolution for precisely the account where it matters.
     */
    private function registerGates(): void
    {
        $authorization = $this->app->make(AuthorizationService::class);

        foreach (PermissionCatalog::all() as $permission) {
            Gate::define(
                $permission,
                static fn (UserModel $user): bool => $authorization->allows($user, $permission)
            );
        }
    }

    private function registerMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');
    }

    private function registerRoutes(): void
    {
        if (file_exists(__DIR__.'/../Routes/api.php')) {
            Route::middleware(['api'])
                ->prefix('api/v1')
                ->group(__DIR__.'/../Routes/api.php');
        }

        if (file_exists(__DIR__.'/../Routes/admin.php')) {
            Route::middleware(['api', 'auth:sanctum', 'admin'])
                ->prefix('api/v1/admin')
                ->group(__DIR__.'/../Routes/admin.php');
        }
    }

    private function registerTranslations(): void
    {
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'core');
    }
}
