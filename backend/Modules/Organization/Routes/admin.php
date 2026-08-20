<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Organization\Presentation\HTTP\Controllers\CenterController;
use Modules\Organization\Presentation\HTTP\Controllers\CircleController;
use Modules\Organization\Presentation\HTTP\Controllers\MembershipController;

/*
|--------------------------------------------------------------------------
| Organization Module — Admin API Routes
|--------------------------------------------------------------------------
| Surface: Admin
| Prefix:  /api/v1/admin  (applied by the ServiceProvider)
| Middleware: api, auth:sanctum, admin — then `can:` per route
|
| Centres and circles are administered here rather than under Contestants,
| because managing where people study is not managing the people (ADR-016 D6).
*/

Route::get('centers', [CenterController::class, 'index'])
    ->name('admin.centers.index')
    ->middleware('can:centers.view');

Route::get('centers/{id}', [CenterController::class, 'show'])
    ->name('admin.centers.show')
    ->middleware('can:centers.view');

Route::post('centers', [CenterController::class, 'store'])
    ->name('admin.centers.store')
    ->middleware('can:centers.create');

Route::patch('centers/{id}', [CenterController::class, 'update'])
    ->name('admin.centers.update')
    ->middleware('can:centers.update');

Route::delete('centers/{id}', [CenterController::class, 'destroy'])
    ->name('admin.centers.destroy')
    ->middleware('can:centers.delete');

Route::get('circles', [CircleController::class, 'index'])
    ->name('admin.circles.index')
    ->middleware('can:circles.view');

Route::get('circles/{id}', [CircleController::class, 'show'])
    ->name('admin.circles.show')
    ->middleware('can:circles.view');

Route::post('circles', [CircleController::class, 'store'])
    ->name('admin.circles.store')
    ->middleware('can:circles.create');

Route::patch('circles/{id}', [CircleController::class, 'update'])
    ->name('admin.circles.update')
    ->middleware('can:circles.update');

Route::delete('circles/{id}', [CircleController::class, 'destroy'])
    ->name('admin.circles.destroy')
    ->middleware('can:circles.delete');

Route::get('memberships', [MembershipController::class, 'index'])
    ->name('admin.memberships.index')
    ->middleware('can:memberships.view');

Route::get('memberships/{id}', [MembershipController::class, 'show'])
    ->name('admin.memberships.show')
    ->middleware('can:memberships.view');

Route::post('memberships', [MembershipController::class, 'store'])
    ->name('admin.memberships.store')
    ->middleware('can:memberships.create');

// Ending is a POST to a named action rather than a DELETE on the resource,
// because nothing is removed: the row stays and acquires `left_at`. A DELETE
// would tell every reader of this file the opposite of what Q4 decided.
Route::post('memberships/{id}/end', [MembershipController::class, 'end'])
    ->name('admin.memberships.end')
    ->middleware('can:memberships.end');

// Its own permission, not memberships.create plus memberships.end. A transfer
// is a single act with different consequences from either half, and an
// operator trusted to enrol a newcomer is not automatically trusted to move
// someone out of another supervisor's circle.
Route::post('memberships/transfer', [MembershipController::class, 'transfer'])
    ->name('admin.memberships.transfer')
    ->middleware('can:memberships.transfer');
