<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Content\Presentation\HTTP\Controllers\AdminContentController;

/*
|--------------------------------------------------------------------------
| Content Module — Admin Routes
|--------------------------------------------------------------------------
| Prefix:  /api/v1/admin  (applied by ContentServiceProvider)
| Middleware: api, auth:sanctum
*/

Route::prefix('content')->group(function (): void {
    Route::get('announcements', [AdminContentController::class, 'indexAnnouncements'])->name('admin.content.announcements.index');
    Route::post('announcements', [AdminContentController::class, 'storeAnnouncement'])->name('admin.content.announcements.store');
    Route::post('announcements/{id}/publish', [AdminContentController::class, 'toggleAnnouncementPublish'])->name('admin.content.announcements.publish');
    Route::delete('announcements/{id}', [AdminContentController::class, 'destroyAnnouncement'])->name('admin.content.announcements.destroy');
});
