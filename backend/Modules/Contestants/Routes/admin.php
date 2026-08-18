<?php

// @stub-version 1.0.0
// @generated-by make:platform-module
// @adr ADR-004, ADR-011

use Illuminate\Support\Facades\Route;
use Modules\Contestants\Presentation\HTTP\Controllers\AdminContestantController;

/*
|--------------------------------------------------------------------------
| Contestants Module — Admin API Routes
|--------------------------------------------------------------------------
| Surface: Admin
| Prefix:  /api/v1/admin  (applied by ServiceProvider)
| Middleware: api, auth:sanctum, admin
*/

Route::get('contestants', [AdminContestantController::class, 'index'])->name('admin.contestants.index')->middleware('can:contestants.view');
Route::get('contestants/{id}', [AdminContestantController::class, 'show'])->name('admin.contestants.show')->middleware('can:contestants.view');
