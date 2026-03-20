<?php

use Illuminate\Support\Facades\Route;
use hexa_app_publish\Http\Controllers\PublishAccountController;
use hexa_app_publish\Http\Controllers\PublishSiteController;
use hexa_app_publish\Http\Controllers\PublishCampaignController;
use hexa_app_publish\Http\Controllers\PublishArticleController;
use hexa_app_publish\Http\Controllers\PublishTemplateController;
use hexa_app_publish\Http\Controllers\PublishDashboardController;
use hexa_app_publish\Http\Controllers\PublishSettingsController;
use hexa_app_publish\Http\Controllers\PublishLinkController;
use hexa_app_publish\Http\Controllers\PublishSearchController;


Route::middleware(['web', 'auth', 'locked', 'system_lock', 'two_factor', 'role'])->group(function () {

    // Dashboard widget data
    Route::get('/publish/dashboard', [PublishDashboardController::class, 'index'])->name('publish.dashboard');

    // Accounts
    Route::get('/publish/accounts', [PublishAccountController::class, 'index'])->name('publish.accounts.index');
    Route::get('/publish/accounts/create', [PublishAccountController::class, 'create'])->name('publish.accounts.create');
    Route::post('/publish/accounts', [PublishAccountController::class, 'store'])->name('publish.accounts.store');
    Route::get('/publish/accounts/{id}', [PublishAccountController::class, 'show'])->name('publish.accounts.show');
    Route::get('/publish/accounts/{id}/edit', [PublishAccountController::class, 'edit'])->name('publish.accounts.edit');
    Route::put('/publish/accounts/{id}', [PublishAccountController::class, 'update'])->name('publish.accounts.update');
    Route::post('/publish/accounts/{id}/add-user', [PublishAccountController::class, 'addUser'])->name('publish.accounts.add-user');
    Route::delete('/publish/accounts/{id}/remove-user/{userId}', [PublishAccountController::class, 'removeUser'])->name('publish.accounts.remove-user');

    // Sites
    Route::get('/publish/sites', [PublishSiteController::class, 'index'])->name('publish.sites.index');
    Route::get('/publish/sites/create', [PublishSiteController::class, 'create'])->name('publish.sites.create');
    Route::post('/publish/sites', [PublishSiteController::class, 'store'])->name('publish.sites.store');
    Route::get('/publish/sites/{id}', [PublishSiteController::class, 'show'])->name('publish.sites.show');
    Route::get('/publish/sites/{id}/edit', [PublishSiteController::class, 'edit'])->name('publish.sites.edit');
    Route::put('/publish/sites/{id}', [PublishSiteController::class, 'update'])->name('publish.sites.update');
    Route::post('/publish/sites/{id}/test', [PublishSiteController::class, 'testConnection'])->name('publish.sites.test');

    // Templates
    Route::get('/publish/templates', [PublishTemplateController::class, 'index'])->name('publish.templates.index');
    Route::get('/publish/templates/create', [PublishTemplateController::class, 'create'])->name('publish.templates.create');
    Route::post('/publish/templates', [PublishTemplateController::class, 'store'])->name('publish.templates.store');
    Route::get('/publish/templates/{id}', [PublishTemplateController::class, 'show'])->name('publish.templates.show');
    Route::get('/publish/templates/{id}/edit', [PublishTemplateController::class, 'edit'])->name('publish.templates.edit');
    Route::put('/publish/templates/{id}', [PublishTemplateController::class, 'update'])->name('publish.templates.update');
    Route::delete('/publish/templates/{id}', [PublishTemplateController::class, 'destroy'])->name('publish.templates.destroy');

    // Campaigns
    Route::get('/publish/campaigns', [PublishCampaignController::class, 'index'])->name('publish.campaigns.index');
    Route::get('/publish/campaigns/create', [PublishCampaignController::class, 'create'])->name('publish.campaigns.create');
    Route::post('/publish/campaigns', [PublishCampaignController::class, 'store'])->name('publish.campaigns.store');
    Route::get('/publish/campaigns/{id}', [PublishCampaignController::class, 'show'])->name('publish.campaigns.show');
    Route::get('/publish/campaigns/{id}/edit', [PublishCampaignController::class, 'edit'])->name('publish.campaigns.edit');
    Route::put('/publish/campaigns/{id}', [PublishCampaignController::class, 'update'])->name('publish.campaigns.update');
    Route::post('/publish/campaigns/{id}/activate', [PublishCampaignController::class, 'activate'])->name('publish.campaigns.activate');
    Route::post('/publish/campaigns/{id}/pause', [PublishCampaignController::class, 'pause'])->name('publish.campaigns.pause');
    Route::post('/publish/campaigns/{id}/duplicate', [PublishCampaignController::class, 'duplicate'])->name('publish.campaigns.duplicate');

    // Articles
    Route::get('/publish/articles', [PublishArticleController::class, 'index'])->name('publish.articles.index');
    Route::get('/publish/articles/create', [PublishArticleController::class, 'create'])->name('publish.articles.create');
    Route::post('/publish/articles', [PublishArticleController::class, 'store'])->name('publish.articles.store');
    Route::get('/publish/articles/{id}', [PublishArticleController::class, 'show'])->name('publish.articles.show');
    Route::get('/publish/articles/{id}/edit', [PublishArticleController::class, 'edit'])->name('publish.articles.edit');
    Route::put('/publish/articles/{id}', [PublishArticleController::class, 'update'])->name('publish.articles.update');
    Route::post('/publish/articles/{id}/publish', [PublishArticleController::class, 'publish'])->name('publish.articles.publish');
    Route::post('/publish/articles/{id}/ai-check', [PublishArticleController::class, 'aiCheck'])->name('publish.articles.ai-check');
    Route::post('/publish/articles/{id}/seo-check', [PublishArticleController::class, 'seoCheck'])->name('publish.articles.seo-check');
    Route::post('/publish/articles/{id}/spin', [PublishArticleController::class, 'spin'])->name('publish.articles.spin');

    // Settings — integration tests
    Route::post('/settings/test-integration', [PublishSettingsController::class, 'testIntegration'])->name('settings.test-integration');

    // Photo search (unified)
    Route::get('/publish/photos/search', [PublishArticleController::class, 'searchPhotos'])->name('publish.photos.search');

    // Article source search (unified)
    Route::get('/publish/sources/search', [PublishArticleController::class, 'searchSources'])->name('publish.sources.search');

    // Web scraper
    Route::post('/publish/sources/scrape', [PublishArticleController::class, 'scrapeUrl'])->name('publish.sources.scrape');

    // Links & Sitemaps
    Route::get('/publish/links', [PublishLinkController::class, 'index'])->name('publish.links.index');
    Route::post('/publish/links', [PublishLinkController::class, 'storeLink'])->name('publish.links.store');
    Route::delete('/publish/links/{id}', [PublishLinkController::class, 'destroyLink'])->name('publish.links.destroy');
    Route::post('/publish/links/{id}/toggle', [PublishLinkController::class, 'toggleLink'])->name('publish.links.toggle');
    Route::post('/publish/sitemaps', [PublishLinkController::class, 'storeSitemap'])->name('publish.sitemaps.store');
    Route::post('/publish/sitemaps/{id}/refresh', [PublishLinkController::class, 'refreshSitemap'])->name('publish.sitemaps.refresh');
    Route::delete('/publish/sitemaps/{id}', [PublishLinkController::class, 'destroySitemap'])->name('publish.sitemaps.destroy');

    // Article link insertion (AI-powered)
    Route::post('/publish/articles/{id}/insert-links', [PublishArticleController::class, 'insertLinks'])->name('publish.articles.insert-links');

    // Search
    Route::get('/search/images', [PublishSearchController::class, 'images'])->name('publish.search.images');
    Route::post('/search/images', [PublishSearchController::class, 'searchImages'])->name('publish.search.images.post');
});
