<?php

// @stub-version 1.0.0
// @generated-by make:platform-module
// @adr ADR-011

declare(strict_types=1);

namespace Modules\Core\Providers;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Modules\Core\Contracts\CoreServiceContract;
use Modules\Core\Domain\Repositories\ActivityLogRepositoryContract;
use Modules\Core\Domain\Repositories\LoginHistoryRepositoryContract;
use Modules\Core\Domain\Repositories\InvitationRepositoryContract;
use Modules\Core\Domain\Repositories\RoleRepositoryContract;
use Modules\Core\Domain\Repositories\UserProfileRepositoryContract;
use Modules\Core\Domain\Repositories\UserRepositoryContract;
use Modules\Core\Infrastructure\ActivityLog\RecordActivity;
use Modules\Core\Infrastructure\Database\Models\UserModel;
use Modules\Core\Infrastructure\Database\Repositories\ActivityLogRepository;
use Modules\Core\Infrastructure\Database\Repositories\LoginHistoryRepository;
use Modules\Core\Infrastructure\Database\Repositories\InvitationRepository;
use Modules\Core\Infrastructure\Database\Repositories\RoleRepository;
use Modules\Core\Infrastructure\Database\Repositories\UserProfileRepository;
use Modules\Core\Infrastructure\Database\Repositories\UserRepository;
use Modules\Core\Infrastructure\Permissions\AuthorizationService;
use Modules\Core\Infrastructure\Permissions\PermissionCatalog;
use Modules\Core\Infrastructure\Services\CoreService;

final class CoreServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../Config/core.php', 'core');

        $this->app->singleton(
            UserRepositoryContract::class,
            UserRepository::class
        );

        $this->app->singleton(
            RoleRepositoryContract::class,
            RoleRepository::class
        );

        $this->app->singleton(
            InvitationRepositoryContract::class,
            InvitationRepository::class
        );

        $this->app->singleton(
            UserProfileRepositoryContract::class,
            UserProfileRepository::class
        );

        // Read-only: the activity feed is queried through this, while rows are
        // written by the listener. Bound so the controller depends on the
        // contract rather than on Eloquent — the architecture guard failed the
        // first version of that controller for exactly this.
        $this->app->singleton(
            ActivityLogRepositoryContract::class,
            ActivityLogRepository::class
        );

        // Read-only, over audit_logs (ADR-018 D2). Bound so the controller
        // depends on the contract rather than on Eloquent.
        $this->app->singleton(
            LoginHistoryRepositoryContract::class,
            LoginHistoryRepository::class
        );

        // The module's boundary, per ADR-002. Bound like every other
        // contract here so a consumer type-hints the interface and never
        // learns that UserModel exists.
        $this->app->singleton(
            CoreServiceContract::class,
            CoreService::class
        );
    }

    public function boot(): void
    {
        $this->registerMigrations();
        $this->registerRoutes();
        $this->registerTranslations();
        $this->registerGates();
        $this->registerActivityLog();
    }

    /**
     * The first consumer of a domain event this platform has ever had.
     *
     * 46 event classes existed before this line and nothing listened to any of
     * them — they were dispatched into nothing. ADR-017 D2 makes this the
     * activity log's only entry point.
     *
     * A WILDCARD, NOT A LIST OF EVENT CLASSES. Naming them here would mean Core
     * importing events owned by seven other modules, which ADR-002 forbids and
     * the repaired boundary guard detects. ActivityEventRegistry decides what
     * is loggable by class-name string, and RecordActivity ignores everything
     * else the framework dispatches.
     */
    private function registerActivityLog(): void
    {
        Event::listen('*', [RecordActivity::class, 'handle']);
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
