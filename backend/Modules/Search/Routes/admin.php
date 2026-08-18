<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Search\Presentation\HTTP\Controllers\AdminSearchController;

/*
|--------------------------------------------------------------------------
| Search Module — Admin Routes
|--------------------------------------------------------------------------
| Prefix:  /api/v1/admin  (applied by SearchServiceProvider)
| Middleware: api, auth:sanctum
*/

Route::prefix('search')->group(function (): void {
    Route::post('reindex', [AdminSearchController::class, 'reindex'])->name('admin.search.reindex')->middleware('can:search.reindex');
    Route::get('indexing-logs', [AdminSearchController::class, 'indexingLogs'])->name('admin.search.indexing_logs')->middleware('can:search.view');
});
