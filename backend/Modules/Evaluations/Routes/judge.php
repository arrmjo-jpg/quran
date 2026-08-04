<?php

use Illuminate\Support\Facades\Route;
use Modules\Evaluations\Presentation\HTTP\Controllers\JudgeEvaluationController;

Route::middleware('auth:sanctum')->prefix('judge')->group(function () {
    Route::get('evaluations', [JudgeEvaluationController::class, 'index'])->name('judge.evaluations.index');
    Route::get('evaluations/{id}', [JudgeEvaluationController::class, 'show'])->name('judge.evaluations.show');
    Route::post('evaluations/{id}/start', [JudgeEvaluationController::class, 'start'])->name('judge.evaluations.start');
    Route::post('evaluations/{id}/submit', [JudgeEvaluationController::class, 'submit'])->name('judge.evaluations.submit');
    Route::post('evaluations/{id}/save-draft', [JudgeEvaluationController::class, 'saveDraft'])->name('judge.evaluations.save_draft');
});
