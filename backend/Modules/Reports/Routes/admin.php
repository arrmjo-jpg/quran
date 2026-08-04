<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Reports\Presentation\HTTP\Controllers\AdminReportController;

/*
|--------------------------------------------------------------------------
| Reports Module — Admin Routes
|--------------------------------------------------------------------------
| Prefix:  /api/v1/admin  (applied by ReportsServiceProvider)
| Middleware: api, auth:sanctum
*/

Route::prefix('reports')->group(function (): void {
    Route::get('summary', [AdminReportController::class, 'summary'])->name('admin.reports.summary');
    Route::get('exports', [AdminReportController::class, 'indexExports'])->name('admin.reports.exports.index');
    Route::post('exports', [AdminReportController::class, 'createExport'])->name('admin.reports.exports.create');
});
