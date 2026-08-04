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
            Route::middleware(['api', 'auth:sanctum'])
                ->prefix('api/v1/admin')
                ->group(__DIR__.'/../Routes/admin.php');
        }

        if (file_exists(__DIR__.'/../Routes/judge.php')) {
            Route::middleware(['api'])
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
