<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Core\Presentation\HTTP\Controllers\ActivityLogController;
use Modules\Core\Presentation\HTTP\Controllers\LoginHistoryController;
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

Route::patch('users/{id}', [UserController::class, 'update'])
    ->name('admin.users.update')
    ->middleware('can:users.update');

// Soft delete only — ADR-016 D5, enforced by AccountDeletionTest. Restoring is
// its own permission because deleting and undeleting are different decisions,
// and the one that can strand the platform is the one worth granting
// deliberately.
Route::delete('users/{id}', [UserController::class, 'destroy'])
    ->name('admin.users.destroy')
    ->middleware('can:users.delete');

Route::patch('users/{id}/restore', [UserController::class, 'restore'])
    ->name('admin.users.restore')
    ->middleware('can:users.restore');

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

// The activity log — ADR-017. Read-only: rows arrive from the event listener,
// and a log that could be posted to is not a record of what happened.
//
// THIS MAKES `audit.view` LIVE. The permission has been in the catalogue since
// it was written and held by super_admin alone, and no route has ever consulted
// it — a dormant grant of exactly the kind ADR-016 D17 described. Recorded here
// so the moment it started to mean something is findable.
Route::get('activity-logs', [ActivityLogController::class, 'index'])
    ->name('admin.activity-logs.index')
    ->middleware('can:audit.view');

// The login history -- ADR-018 D2, D3. Read-only, over audit_logs: the rows
// have been landing there since the platform booted, and a second table would
// mean writing a second row for an event already recorded.
//
// THIS MAKES `security.view` LIVE. Deliberately NOT audit.view, which Epic 5
// gave a specific meaning three commits ago -- one permission governing both
// screens would let a grant issued for the activity feed silently confer a
// view of every login attempt on the platform.
Route::get('security/login-history', [LoginHistoryController::class, 'index'])
    ->name('admin.security.login-history.index')
    ->middleware('can:security.view');
