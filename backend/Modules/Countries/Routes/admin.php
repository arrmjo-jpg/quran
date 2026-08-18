<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Countries\Presentation\HTTP\Controllers\CountryController;

Route::post('countries', [CountryController::class, 'store'])->name('admin.countries.store')->middleware('can:countries.create');
Route::patch('countries/{id}/activate', [CountryController::class, 'activate'])->name('admin.countries.activate')->middleware('can:countries.activate');
Route::patch('countries/{id}/deactivate', [CountryController::class, 'deactivate'])->name('admin.countries.deactivate')->middleware('can:countries.deactivate');
