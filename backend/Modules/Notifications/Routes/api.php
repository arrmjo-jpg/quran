<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Notifications\Presentation\HTTP\Controllers\ContestantNotificationController;

/*
|--------------------------------------------------------------------------
| Notifications Module — Contestant / Public API Routes
|--------------------------------------------------------------------------
| Prefix:  /api/v1  (applied by NotificationsServiceProvider)
| Middleware: api
*/

Route::middleware('auth:sanctum')->prefix('contestant/notifications')->group(function (): void {
    Route::get('/', [ContestantNotificationController::class, 'index'])->name('contestant.notifications.index');
    Route::get('/{id}', [ContestantNotificationController::class, 'show'])->name('contestant.notifications.show');
});
