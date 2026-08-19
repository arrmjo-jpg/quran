<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Organization\Presentation\HTTP\Controllers\CenterController;
use Modules\Organization\Presentation\HTTP\Controllers\CircleController;

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
