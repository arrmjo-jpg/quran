<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Sponsors\Presentation\HTTP\Controllers\PublicSponsorController;

/*
|--------------------------------------------------------------------------
| Sponsors Module — Public Routes
|--------------------------------------------------------------------------
| Prefix:  /api/v1  (applied by SponsorsServiceProvider)
| Middleware: api
*/

Route::prefix('sponsors')->group(function (): void {
    Route::get('/', [PublicSponsorController::class, 'index'])->name('sponsors.index');
});
