<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Judges\Presentation\HTTP\Controllers\JudgeController;

Route::middleware('auth:sanctum')->prefix('judge')->group(function (): void {
    Route::get('profile', [JudgeController::class, 'profile'])->name('judge.profile');
});
