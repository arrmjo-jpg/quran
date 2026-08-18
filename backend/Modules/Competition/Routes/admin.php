<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Competition\Presentation\HTTP\Controllers\AdminLookupController;
use Modules\Competition\Presentation\HTTP\Controllers\AdminSeasonController;
use Modules\Competition\Presentation\HTTP\Controllers\AdminStageController;

// Catalogs behind the season/stage rule pickers.
Route::get('participation-types', [AdminLookupController::class, 'participationTypes'])->name('admin.lookups.participation_types')->middleware('can:lookups.view');
Route::get('tajweed-levels', [AdminLookupController::class, 'tajweedLevels'])->name('admin.lookups.tajweed_levels')->middleware('can:lookups.view');
Route::get('judge-score-systems', [AdminLookupController::class, 'judgeScoreSystems'])->name('admin.lookups.judge_score_systems')->middleware('can:lookups.view');

Route::get('seasons', [AdminSeasonController::class, 'index'])->name('admin.seasons.index')->middleware('can:seasons.view');
Route::post('seasons', [AdminSeasonController::class, 'store'])->name('admin.seasons.store')->middleware('can:seasons.create');
Route::get('seasons/{id}', [AdminSeasonController::class, 'show'])->name('admin.seasons.show')->middleware('can:seasons.view');
Route::patch('seasons/{id}', [AdminSeasonController::class, 'update'])->name('admin.seasons.update')->middleware('can:seasons.update');
Route::patch('seasons/{id}/rules', [AdminSeasonController::class, 'updateRules'])->name('admin.seasons.update_rules')->middleware('can:season_rules.update');
Route::post('seasons/{id}/archive', [AdminSeasonController::class, 'archive'])->name('admin.seasons.archive')->middleware('can:seasons.archive');
Route::post('seasons/{id}/cancel', [AdminSeasonController::class, 'cancel'])->name('admin.seasons.cancel')->middleware('can:seasons.cancel');
Route::post('seasons/{id}/restore', [AdminSeasonController::class, 'restore'])->name('admin.seasons.restore')->middleware('can:seasons.restore');
Route::post('seasons/{id}/open-registration', [AdminSeasonController::class, 'openRegistration'])->name('admin.seasons.open_registration')->middleware('can:seasons.open_registration');
Route::post('seasons/{id}/close-registration', [AdminSeasonController::class, 'closeRegistration'])->name('admin.seasons.close_registration')->middleware('can:seasons.close_registration');
Route::post('seasons/{id}/reopen-registration', [AdminSeasonController::class, 'reopenRegistration'])->name('admin.seasons.reopen_registration')->middleware('can:seasons.reopen_registration');

Route::get('seasons/{seasonId}/stages', [AdminStageController::class, 'index'])->name('admin.stages.index')->middleware('can:stages.view');
Route::post('seasons/{seasonId}/stages', [AdminStageController::class, 'store'])->name('admin.stages.store')->middleware('can:stages.create');
Route::put('seasons/{seasonId}/stages/order', [AdminStageController::class, 'reorder'])->name('admin.stages.reorder')->middleware('can:stages.update');
Route::get('seasons/{seasonId}/stage-rules', [AdminStageController::class, 'stageRules'])->name('admin.stages.rules_index')->middleware('can:season_rules.view');
Route::patch('seasons/{seasonId}/stage-rules', [AdminStageController::class, 'updateStageRules'])->name('admin.stages.update_rules')->middleware('can:season_rules.update');
Route::patch('stages/{id}', [AdminStageController::class, 'update'])->name('admin.stages.update')->middleware('can:stages.update');
Route::delete('stages/{id}', [AdminStageController::class, 'destroy'])->name('admin.stages.destroy')->middleware('can:stages.delete');
Route::get('stages/{id}/preview-results', [AdminSeasonController::class, 'previewResults'])->name('admin.stages.preview_results')->middleware('can:stages.preview_results');
Route::post('stages/{id}/simulate-ranking', [AdminSeasonController::class, 'simulateRanking'])->name('admin.stages.simulate_ranking')->middleware('can:stages.simulate_ranking');
