<?php

use Illuminate\Support\Facades\Route;
use Modules\Contestants\Presentation\HTTP\Controllers\AdminContestantController;

/*
|--------------------------------------------------------------------------
| Contestants Module — Admin API Routes
|--------------------------------------------------------------------------
| Surface: Admin
| Prefix:  /api/v1/admin  (applied by ServiceProvider)
| Middleware: api, auth:sanctum, admin — then `can:` per route
|
| Management arrived with Epic 4 Story 1 (ADR-016 D15). Before it, this file
| held index and show alone: contestant records could be read from the panel
| and created only by the person themselves, through public registration.
|
| delete and restore carry their own permissions rather than reusing
| contestants.update, per D17 — data_entry may enter and correct records but
| not remove them.
*/

Route::get('contestants', [AdminContestantController::class, 'index'])
    ->name('admin.contestants.index')
    ->middleware('can:contestants.view');

Route::get('contestants/{id}', [AdminContestantController::class, 'show'])
    ->name('admin.contestants.show')
    ->middleware('can:contestants.view');

Route::post('contestants', [AdminContestantController::class, 'store'])
    ->name('admin.contestants.store')
    ->middleware('can:contestants.create');

Route::patch('contestants/{id}', [AdminContestantController::class, 'update'])
    ->name('admin.contestants.update')
    ->middleware('can:contestants.update');

Route::delete('contestants/{id}', [AdminContestantController::class, 'destroy'])
    ->name('admin.contestants.destroy')
    ->middleware('can:contestants.delete');

// POST rather than PATCH, matching the shape the epic prompt specified and
// the memberships module's `memberships/{id}/end`. Users restore over PATCH;
// the two conventions already coexist and neither is wrong.
Route::post('contestants/{id}/restore', [AdminContestantController::class, 'restore'])
    ->name('admin.contestants.restore')
    ->middleware('can:contestants.restore');
