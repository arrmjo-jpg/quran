<?php

use Illuminate\Support\Facades\Route;
use Modules\Evaluations\Presentation\HTTP\Controllers\AdminEvaluationController;
use Modules\Evaluations\Presentation\HTTP\Controllers\AppealController;

// (already protected by auth:sanctum from ServiceProvider)
Route::get('evaluations', [AdminEvaluationController::class, 'index'])->name('admin.evaluations.index');
Route::post('stages/{id}/calculate-results', [AdminEvaluationController::class, 'calculateResults'])->name('admin.stages.calculate_results');
Route::post('stages/{id}/publish-results', [AdminEvaluationController::class, 'publishResults'])->name('admin.stages.publish_results');
Route::post('stages/{id}/reopen-results', [AdminEvaluationController::class, 'reopenResults'])->name('admin.stages.reopen_results');
Route::get('appeals', [AppealController::class, 'index'])->name('admin.appeals.index');
Route::post('appeals/{id}/accept', [AppealController::class, 'accept'])->name('admin.appeals.accept');
Route::post('appeals/{id}/reject', [AppealController::class, 'reject'])->name('admin.appeals.reject');
