<?php

// @stub-version 1.0.0
// @generated-by make:platform-module
// @adr ADR-011

declare(strict_types=1);

namespace Modules\Competition\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Modules\Competition\Contracts\CompetitionServiceContract;
use Modules\Competition\Contracts\RuleEngineContract;
use Modules\Competition\Domain\Repositories\SeasonRepositoryContract;
use Modules\Competition\Domain\Services\CompetitionRuleEngine;
use Modules\Competition\Infrastructure\Database\Repositories\SeasonRepository;
use Modules\Competition\Infrastructure\Services\CompetitionService;

final class CompetitionServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(
            SeasonRepositoryContract::class,
            SeasonRepository::class
        );

        $this->app->singleton(
            RuleEngineContract::class,
            CompetitionRuleEngine::class
        );

        $this->app->singleton(
            CompetitionServiceContract::class,
            CompetitionService::class
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
            Route::middleware(['api', 'auth:sanctum', 'admin'])
                ->prefix('api/v1/admin')
                ->group(__DIR__.'/../Routes/admin.php');
        }
    }

    private function registerTranslations(): void
    {
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'competition');
    }
}
