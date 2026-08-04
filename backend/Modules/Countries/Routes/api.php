<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Countries\Presentation\HTTP\Controllers\CountryController;

Route::get('countries', [CountryController::class, 'index'])->name('countries.index');
Route::get('countries/{id}', [CountryController::class, 'show'])->name('countries.show');
