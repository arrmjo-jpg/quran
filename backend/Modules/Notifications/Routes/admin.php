<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Notifications\Presentation\HTTP\Controllers\AdminNotificationController;
use Modules\Notifications\Presentation\HTTP\Controllers\NotificationPreferenceController;

/*
|--------------------------------------------------------------------------
| Notifications Module — Admin Routes
|--------------------------------------------------------------------------
| Prefix:  /api/v1/admin  (applied by NotificationsServiceProvider)
| Middleware: api, auth:sanctum
*/

/*
 * ADR-020 D4/D9 -- an account's OWN preferences.
 *
 * No `can:` middleware, deliberately. A permission answers "which role may do
 * this" and the answer is "the account whose preferences these are", which no
 * role expresses. Both names are listed in SELF_SERVICE_ROUTES so the
 * architecture guard checks the exemption instead of being blind to it, the
 * same treatment ADR-018 D4 gave MFA.
 *
 * Under /admin because that is where the authenticated panel's own routes
 * live; the account is read from the token, never from the path.
 */
Route::prefix('me/notification-preferences')->group(function (): void {
    Route::get('/', [NotificationPreferenceController::class, 'index'])->name('admin.notifications.preferences.index');
    Route::put('/', [NotificationPreferenceController::class, 'update'])->name('admin.notifications.preferences.update');
});

Route::prefix('notifications')->group(function (): void {
    Route::get('/', [AdminNotificationController::class, 'index'])->name('admin.notifications.index')->middleware('can:notifications.view');
    Route::get('/{id}', [AdminNotificationController::class, 'show'])->name('admin.notifications.show')->middleware('can:notifications.view');
    Route::post('/{id}/retry', [AdminNotificationController::class, 'retry'])->name('admin.notifications.retry')->middleware('can:notifications.retry');
});
