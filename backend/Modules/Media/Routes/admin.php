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
    Route::get('/', [AdminMediaController::class, 'index'])->name('admin.media.index')->middleware('can:media.view');
    Route::post('/', [AdminMediaController::class, 'upload'])->name('admin.media.upload')->middleware('can:media.create');
    Route::get('/{id}', [AdminMediaController::class, 'show'])->name('admin.media.show')->middleware('can:media.view');
    Route::patch('/{id}', [AdminMediaController::class, 'update'])->name('admin.media.update')->middleware('can:media.update');
    Route::delete('/{id}', [AdminMediaController::class, 'destroy'])->name('admin.media.destroy')->middleware('can:media.delete');
    Route::post('/{id}/reprocess', [AdminMediaController::class, 'reprocess'])->name('admin.media.reprocess')->middleware('can:media.reprocess');
});
