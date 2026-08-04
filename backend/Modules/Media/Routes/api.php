<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Media\Presentation\HTTP\Controllers\ContestantMediaController;

/*
|--------------------------------------------------------------------------
| Media Module — Contestant / Public API Routes
|--------------------------------------------------------------------------
| Surface: Contestant
| Prefix:  /api/v1  (applied by MediaServiceProvider)
| Middleware: api
*/

// Contestant media upload (auth enforced inside route group below)
Route::middleware('auth:sanctum')->prefix('contestant/media')->group(function (): void {
    Route::post('/upload', [ContestantMediaController::class, 'upload'])
        ->name('contestant.media.upload');

    Route::get('/{id}', [ContestantMediaController::class, 'show'])
        ->name('contestant.media.show');
});
