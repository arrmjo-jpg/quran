<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Sponsors\Presentation\HTTP\Controllers\AdminSponsorController;

/*
|--------------------------------------------------------------------------
| Sponsors Module — Admin Routes
|--------------------------------------------------------------------------
| Prefix:  /api/v1/admin  (applied by SponsorsServiceProvider)
| Middleware: api, auth:sanctum
*/

Route::prefix('sponsors')->group(function (): void {
    Route::get('/', [AdminSponsorController::class, 'index'])->name('admin.sponsors.index');
    Route::post('/', [AdminSponsorController::class, 'store'])->name('admin.sponsors.store');
    Route::get('/{id}', [AdminSponsorController::class, 'show'])->name('admin.sponsors.show');
    Route::delete('/{id}', [AdminSponsorController::class, 'destroy'])->name('admin.sponsors.destroy');
});
