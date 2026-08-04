<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Notifications\Presentation\HTTP\Controllers\AdminNotificationController;

/*
|--------------------------------------------------------------------------
| Notifications Module — Admin Routes
|--------------------------------------------------------------------------
| Prefix:  /api/v1/admin  (applied by NotificationsServiceProvider)
| Middleware: api, auth:sanctum
*/

Route::prefix('notifications')->group(function (): void {
    Route::get('/', [AdminNotificationController::class, 'index'])->name('admin.notifications.index');
    Route::get('/{id}', [AdminNotificationController::class, 'show'])->name('admin.notifications.show');
    Route::post('/{id}/retry', [AdminNotificationController::class, 'retry'])->name('admin.notifications.retry');
});
