<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Content\Presentation\HTTP\Controllers\PublicContentController;

/*
|--------------------------------------------------------------------------
| Content Module — Public Routes
|--------------------------------------------------------------------------
| Prefix:  /api/v1  (applied by ContentServiceProvider)
| Middleware: api
*/

Route::prefix('content')->group(function (): void {
    Route::get('announcements', [PublicContentController::class, 'announcements'])->name('content.announcements');
});
