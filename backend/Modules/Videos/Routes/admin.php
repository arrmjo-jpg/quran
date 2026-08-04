<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Videos\Presentation\HTTP\Controllers\AdminVideoController;

/*
|--------------------------------------------------------------------------
| Videos Module — Admin Routes
|--------------------------------------------------------------------------
| Prefix:  /api/v1/admin  (applied by VideosServiceProvider)
| Middleware: api, auth:sanctum
*/

Route::prefix('videos')->group(function (): void {
    Route::get('/', [AdminVideoController::class, 'index'])->name('admin.videos.index');
    Route::get('/{id}', [AdminVideoController::class, 'show'])->name('admin.videos.show');
    Route::post('/{id}/reprocess', [AdminVideoController::class, 'reprocess'])->name('admin.videos.reprocess');
    Route::delete('/{id}', [AdminVideoController::class, 'destroy'])->name('admin.videos.destroy');
});
