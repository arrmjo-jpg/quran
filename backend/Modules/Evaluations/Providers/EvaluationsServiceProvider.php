<?php

// @stub-version 1.0.0
// @generated-by make:platform-module
// @adr ADR-011

declare(strict_types=1);

namespace Modules\Evaluations\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Modules\Evaluations\Application\UseCases\SubmitEvaluationUseCase;
use Modules\Evaluations\Domain\Repositories\EvaluationRepositoryContract;
use Modules\Evaluations\Domain\Services\EvaluationStateMachine;
use Modules\Evaluations\Domain\Services\RankingService;
use Modules\Evaluations\Infrastructure\Database\Repositories\EvaluationRepository;

final class EvaluationsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(
            EvaluationRepositoryContract::class,
            EvaluationRepository::class
        );
        $this->app->singleton(SubmitEvaluationUseCase::class);
        $this->app->singleton(EvaluationStateMachine::class);
        $this->app->singleton(RankingService::class);
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

        // The judge surface carries the 'admin' middleware because judges
        // authenticate on the admin surface (ADR-015 §1: users.type is
        // the door, roles decide what happens inside). It was previously
        // registered with 'api' alone, and its own route file adds only
        // auth:sanctum — so any authenticated account, contestants
        // included, could reach the scoring endpoints. Nothing about
        // being a judge was ever checked.
        if (file_exists(__DIR__.'/../Routes/judge.php')) {
            Route::middleware(['api', 'auth:sanctum', 'admin'])
                ->prefix('api/v1')
                ->group(__DIR__.'/../Routes/judge.php');
        }

        if (file_exists(__DIR__.'/../Routes/contestant.php')) {
            Route::middleware(['api'])
                ->prefix('api/v1')
                ->group(__DIR__.'/../Routes/contestant.php');
        }
    }

    private function registerTranslations(): void
    {
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'evaluations');
    }
}
