<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Streaming\Presentation\HTTP\Controllers\AdminStreamController;

/*
|--------------------------------------------------------------------------
| Streaming Module — Admin Routes
|--------------------------------------------------------------------------
| Prefix:  /api/v1/admin  (applied by StreamingServiceProvider)
| Middleware: api, auth:sanctum
*/

Route::prefix('streams')->group(function (): void {
    Route::get('/', [AdminStreamController::class, 'index'])->name('admin.streams.index');
    Route::post('/', [AdminStreamController::class, 'store'])->name('admin.streams.store');
    Route::get('/{id}', [AdminStreamController::class, 'show'])->name('admin.streams.show');
    Route::post('/{id}/start', [AdminStreamController::class, 'start'])->name('admin.streams.start');
    Route::post('/{id}/stop', [AdminStreamController::class, 'stop'])->name('admin.streams.stop');
    Route::delete('/{id}', [AdminStreamController::class, 'destroy'])->name('admin.streams.destroy');
});
