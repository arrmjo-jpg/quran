<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Media\Presentation\HTTP\Controllers\AdminMediaController;

/*
|--------------------------------------------------------------------------
| Media Module — Admin Routes
|--------------------------------------------------------------------------
| Surface: Admin
| Prefix:  /api/v1/admin  (applied by MediaServiceProvider)
| Middleware: api, auth:sanctum
*/

Route::prefix('media')->group(function (): void {
    Route::get('/', [AdminMediaController::class, 'index'])->name('admin.media.index');
    Route::post('/', [AdminMediaController::class, 'upload'])->name('admin.media.upload');
    Route::get('/{id}', [AdminMediaController::class, 'show'])->name('admin.media.show');
    Route::patch('/{id}', [AdminMediaController::class, 'update'])->name('admin.media.update');
    Route::delete('/{id}', [AdminMediaController::class, 'destroy'])->name('admin.media.destroy');
    Route::post('/{id}/reprocess', [AdminMediaController::class, 'reprocess'])->name('admin.media.reprocess');
});
