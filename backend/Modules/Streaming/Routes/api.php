<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Streaming\Presentation\HTTP\Controllers\PublicStreamController;

/*
|--------------------------------------------------------------------------
| Streaming Module — Public Routes
|--------------------------------------------------------------------------
| Prefix:  /api/v1  (applied by StreamingServiceProvider)
| Middleware: api
*/

Route::prefix('streams')->group(function (): void {
    Route::get('/live', [PublicStreamController::class, 'live'])->name('streams.live');
    Route::get('/{id}', [PublicStreamController::class, 'show'])->name('streams.show');
});
