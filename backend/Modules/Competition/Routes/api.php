<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Competition\Presentation\HTTP\Controllers\PublicSeasonController;

Route::get('seasons', [PublicSeasonController::class, 'index'])->name('seasons.index');
Route::get('seasons/current', [PublicSeasonController::class, 'current'])->name('seasons.current');
Route::get('seasons/{id}', [PublicSeasonController::class, 'show'])->name('seasons.show');
