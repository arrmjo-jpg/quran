<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Core\Presentation\HTTP\Controllers\PermissionController;
use Modules\Core\Presentation\HTTP\Controllers\RoleController;
use Modules\Core\Presentation\HTTP\Controllers\UserController;

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

/*
| Accounts. No create route: ADR-003 provisions administrators by hand and
| contestants arrive through public registration, so an admin-facing "add
| user" would be a third way in that neither document describes.
*/

Route::get('users', [UserController::class, 'index'])
    ->name('admin.users.index')
    ->middleware('can:users.view');

// ADR-016 Q7 supersedes ADR-015's decision that this endpoint would not exist.
// It is safe because of its shape: the administrator chooses the roles, the
// invitee chooses the password, and no response ever carries the token.
Route::post('users', [UserController::class, 'store'])
    ->name('admin.users.store')
    ->middleware('can:users.create');

Route::get('users/{id}', [UserController::class, 'show'])
    ->name('admin.users.show')
    ->middleware('can:users.view');

// Not users.update. Handing an account a role is the escalation surface PE-1
// guards; editing its name is not.
Route::patch('users/{id}/roles', [UserController::class, 'syncRoles'])
    ->name('admin.users.roles.sync')
    ->middleware('can:users.assign_roles');

// Separated from each other, not only from users.update: granting access back
// and cutting it off are different decisions, and the one that can strand the
// platform is the one worth granting deliberately.
Route::patch('users/{id}/activate', [UserController::class, 'activate'])
    ->name('admin.users.activate')
    ->middleware('can:users.activate');

Route::patch('users/{id}/deactivate', [UserController::class, 'deactivate'])
    ->name('admin.users.deactivate')
    ->middleware('can:users.deactivate');
