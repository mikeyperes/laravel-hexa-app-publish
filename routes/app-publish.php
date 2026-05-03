<?php

/**
 * App Publish route loader.
 *
 * Routes are split by domain for maintainability.
 * All route names and URLs are preserved exactly as before.
 */

use Illuminate\Support\Facades\Route;
use hexa_package_user_roles\Http\Middleware\EnsureAdminAccess;

Route::middleware(['web', 'auth', 'locked', 'system_lock', 'two_factor', 'role'])->group(function () {

    Route::middleware([EnsureAdminAccess::class])->group(function () {
        // Publishing domain
        require __DIR__ . '/publishing/dashboard.php';
        require __DIR__ . '/publishing/accounts.php';
        require __DIR__ . '/publishing/templates.php';
        require __DIR__ . '/publishing/campaigns.php';
        require __DIR__ . '/publishing/articles.php';
        require __DIR__ . '/publishing/pipeline.php';
        require __DIR__ . '/publishing/pipeline-v2.php';
        require __DIR__ . '/publishing/presets.php';
        require __DIR__ . '/publishing/prompts.php';
        require __DIR__ . '/publishing/settings.php';
        require __DIR__ . '/publishing/schedule.php';

        // Discovery domain
        require __DIR__ . '/discovery/search.php';
        require __DIR__ . '/discovery/links.php';
        require __DIR__ . '/discovery/scrape-activity.php';

        // Quality domain
        require __DIR__ . '/quality/detection.php';
    });

    require __DIR__ . '/publishing/sites.php';
});
