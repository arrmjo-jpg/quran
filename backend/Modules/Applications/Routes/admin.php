<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Applications\Presentation\HTTP\Controllers\ApplicationController;

Route::get('applications', [ApplicationController::class, 'indexAdmin'])->name('admin.applications.index')->middleware('can:applications.view');
Route::post('applications/{id}/request-reupload', [ApplicationController::class, 'requestReupload'])->name('admin.applications.request_reupload')->middleware('can:applications.request_reupload');
Route::post('applications/{id}/ready-for-judging', [ApplicationController::class, 'markReadyForJudging'])->name('admin.applications.ready_for_judging')->middleware('can:applications.ready_for_judging');
