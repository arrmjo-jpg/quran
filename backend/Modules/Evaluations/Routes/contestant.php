<?php

use Illuminate\Support\Facades\Route;
use Modules\Evaluations\Presentation\HTTP\Controllers\AppealController;

Route::middleware('auth:sanctum')->prefix('contestant')->group(function () {
    Route::post('appeals', [AppealController::class, 'store'])->name('contestant.appeals.store');
});
