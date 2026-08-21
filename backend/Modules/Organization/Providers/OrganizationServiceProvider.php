<?php

// @stub-version 1.0.0
// @generated-by make:platform-module
// @adr ADR-011

declare(strict_types=1);

namespace Modules\Organization\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Modules\Organization\Contracts\OrganizationServiceContract;
use Modules\Organization\Domain\Repositories\CenterRepositoryContract;
use Modules\Organization\Domain\Repositories\CircleRepositoryContract;
use Modules\Organization\Domain\Repositories\ContestantMembershipRepositoryContract;
use Modules\Organization\Infrastructure\Database\Repositories\CenterRepository;
use Modules\Organization\Infrastructure\Database\Repositories\CircleRepository;
use Modules\Organization\Infrastructure\Database\Repositories\ContestantMembershipRepository;
use Modules\Organization\Infrastructure\Services\OrganizationService;

final class OrganizationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(
            CenterRepositoryContract::class,
            CenterRepository::class
        );

        $this->app->singleton(
            CircleRepositoryContract::class,
            CircleRepository::class
        );

        $this->app->singleton(
            ContestantMembershipRepositoryContract::class,
            ContestantMembershipRepository::class
        );

        // The module's boundary, per ADR-002. Contestants reads a
        // contestant's circle history through this and never learns that
        // ContestantMembershipModel exists.
        $this->app->singleton(
            OrganizationServiceContract::class,
            OrganizationService::class
        );
    }

    public function boot(): void
    {
        $this->registerMigrations();
        $this->registerRoutes();
        $this->registerTranslations();
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
            Route::middleware(['api', 'auth:sanctum'])
                ->prefix('api/v1/admin')
                ->group(__DIR__.'/../Routes/admin.php');
        }
    }

    private function registerTranslations(): void
    {
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'organization');
    }
}
