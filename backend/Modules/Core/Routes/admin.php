<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Core\Presentation\HTTP\Controllers\PermissionController;
use Modules\Core\Presentation\HTTP\Controllers\RoleController;

/*
|--------------------------------------------------------------------------
| Core Module — Admin API Routes
|--------------------------------------------------------------------------
| Surface: Admin
| Prefix:  /api/v1/admin  (applied by the ServiceProvider)
| Middleware: api, auth:sanctum, admin — then `can:` per route
|
| The identity surface, and the last part of ADR-015 to reach HTTP. Until
| now roles could only be changed by a seeder or a test: every use case
| existed and nothing exposed them.
*/

Route::get('permissions', [PermissionController::class, 'index'])
    ->name('admin.permissions.index')
    ->middleware('can:permissions.view');

Route::get('roles', [RoleController::class, 'index'])
    ->name('admin.roles.index')
    ->middleware('can:roles.view');

Route::get('roles/{id}', [RoleController::class, 'show'])
    ->name('admin.roles.show')
    ->middleware('can:roles.view');

Route::post('roles', [RoleController::class, 'store'])
    ->name('admin.roles.store')
    ->middleware('can:roles.create');

Route::patch('roles/{id}', [RoleController::class, 'rename'])
    ->name('admin.roles.rename')
    ->middleware('can:roles.update');

// Deliberately not roles.update. Renaming a role is cosmetic; changing what
// it can do is the escalation surface PE-2 guards, and the two are granted
// separately for the same reason streaming.start is not streaming.create.
Route::patch('roles/{id}/permissions', [RoleController::class, 'syncPermissions'])
    ->name('admin.roles.permissions.sync')
    ->middleware('can:roles.grant_permissions');

Route::delete('roles/{id}', [RoleController::class, 'destroy'])
    ->name('admin.roles.destroy')
    ->middleware('can:roles.delete');
