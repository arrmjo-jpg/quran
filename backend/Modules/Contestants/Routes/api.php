<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Contestants\Presentation\HTTP\Controllers\ContestantController;

Route::middleware('auth:sanctum')->prefix('contestant')->group(function (): void {
    Route::get('profile', [ContestantController::class, 'showProfile'])->name('contestant.profile.show');
    Route::post('profile', [ContestantController::class, 'updateProfile'])->name('contestant.profile.update');
    Route::get('eligibility', [ContestantController::class, 'checkEligibility'])->name('contestant.eligibility');
});
