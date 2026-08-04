<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Videos\Presentation\HTTP\Controllers\ContestantVideoController;

/*
|--------------------------------------------------------------------------
| Videos Module — Contestant API Routes
|--------------------------------------------------------------------------
| Prefix:  /api/v1  (applied by VideosServiceProvider)
| Middleware: api
*/

Route::middleware('auth:sanctum')->prefix('contestant/videos')->group(function (): void {
    Route::post('/', [ContestantVideoController::class, 'store'])->name('contestant.videos.store');
    Route::get('/{id}', [ContestantVideoController::class, 'show'])->name('contestant.videos.show');
    Route::get('/{id}/status', [ContestantVideoController::class, 'status'])->name('contestant.videos.status');
});
