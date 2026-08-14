<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Competition\Presentation\HTTP\Controllers\AdminSeasonController;

Route::post('seasons', [AdminSeasonController::class, 'store'])->name('admin.seasons.store');
Route::patch('seasons/{id}/rules', [AdminSeasonController::class, 'updateRules'])->name('admin.seasons.update_rules');
Route::post('seasons/{id}/open-registration', [AdminSeasonController::class, 'openRegistration'])->name('admin.seasons.open_registration');
Route::post('seasons/{id}/close-registration', [AdminSeasonController::class, 'closeRegistration'])->name('admin.seasons.close_registration');
Route::get('stages/{id}/preview-results', [AdminSeasonController::class, 'previewResults'])->name('admin.stages.preview_results');
Route::post('stages/{id}/simulate-ranking', [AdminSeasonController::class, 'simulateRanking'])->name('admin.stages.simulate_ranking');
