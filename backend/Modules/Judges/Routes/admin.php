<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Judges\Presentation\HTTP\Controllers\JudgeController;

Route::get('judges', [JudgeController::class, 'index'])->name('admin.judges.index')->middleware('can:judges.view');
Route::post('judges', [JudgeController::class, 'store'])->name('admin.judges.store')->middleware('can:judges.create');
