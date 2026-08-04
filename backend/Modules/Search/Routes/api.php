<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Search\Presentation\HTTP\Controllers\PublicSearchController;

/*
|--------------------------------------------------------------------------
| Search Module — Public Routes
|--------------------------------------------------------------------------
| Prefix:  /api/v1  (applied by SearchServiceProvider)
| Middleware: api
*/

Route::get('search', [PublicSearchController::class, 'search'])->name('search');
