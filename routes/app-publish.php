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
    // Restricted publish workspace surfaces. These stay available to scoped publish users
    // and are further filtered by PublishAccessService route allowlists + query scoping.
    require __DIR__ . '/publishing/templates.php';
    require __DIR__ . '/publishing/articles.php';
    require __DIR__ . '/publishing/pipeline.php';

    // Admin-only publishing, discovery, and quality surfaces.
    Route::middleware([EnsureAdminAccess::class])->group(function () {
        require __DIR__ . '/publishing/dashboard.php';
        require __DIR__ . '/publishing/accounts.php';
        require __DIR__ . '/publishing/campaigns.php';
        require __DIR__ . '/publishing/pipeline-v2.php';
        require __DIR__ . '/publishing/presets.php';
        require __DIR__ . '/publishing/prompts.php';
        require __DIR__ . '/publishing/settings.php';
        require __DIR__ . '/publishing/schedule.php';

        require __DIR__ . '/discovery/search.php';
        require __DIR__ . '/discovery/links.php';
        require __DIR__ . '/discovery/scrape-activity.php';

        require __DIR__ . '/quality/detection.php';
    });

    require __DIR__ . '/publishing/sites.php';
});
