<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Applications\Presentation\HTTP\Controllers\ApplicationController;

Route::middleware('auth:sanctum')->group(function (): void {
    Route::post('applications', [ApplicationController::class, 'submit'])->name('applications.submit');
    Route::get('applications/my-applications', [ApplicationController::class, 'myApplications'])->name('applications.my_applications');
});
