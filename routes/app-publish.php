<?php

use Illuminate\Support\Facades\Route;
use hexa_app_publish\Http\Controllers\PublishAccountController;
use hexa_app_publish\Http\Controllers\PublishSiteController;
use hexa_app_publish\Campaigns\Http\Controllers\CampaignController;
use hexa_app_publish\Campaigns\Http\Controllers\CampaignPresetController;
use hexa_app_publish\Http\Controllers\AiActivityController;
use hexa_app_publish\Http\Controllers\AiSmartEditController;
use hexa_app_publish\Http\Controllers\PublishArticleController;
use hexa_app_publish\Http\Controllers\PublishTemplateController;
use hexa_app_publish\Http\Controllers\PublishDashboardController;
use hexa_app_publish\Http\Controllers\PublishSettingsController;
use hexa_app_publish\Http\Controllers\PublishLinkController;
use hexa_app_publish\Http\Controllers\PublishSearchController;
use hexa_app_publish\Http\Controllers\PublishPipelineController;
use hexa_app_publish\Http\Controllers\PublishDraftController;
use hexa_app_publish\Http\Controllers\PublishBookmarkController;
use hexa_app_publish\Http\Controllers\PublishPromptController;
use hexa_app_publish\Http\Controllers\PublishPresetController;
use hexa_app_publish\Http\Controllers\PublishMasterSettingController;
use hexa_app_publish\Http\Controllers\PublishScheduleController;


Route::middleware(['web', 'auth', 'locked', 'system_lock', 'two_factor', 'role'])->group(function () {

    // Dashboard widget data
    Route::get('/publish/dashboard', [PublishDashboardController::class, 'index'])->name('publish.dashboard');

    // Users (publishing profiles)
    Route::get('/publish/users', [PublishAccountController::class, 'index'])->name('publish.accounts.index');
    Route::get('/publish/users/{id}', [PublishAccountController::class, 'show'])->name('publish.accounts.show');
    Route::post('/publish/users/{id}/attach-account', [PublishAccountController::class, 'attachAccount'])->name('publish.accounts.attach');
    Route::delete('/publish/users/{id}/detach-account/{accountId}', [PublishAccountController::class, 'detachAccount'])->name('publish.accounts.detach');
    Route::post('/publish/users/{id}/scan-wordpress', [PublishAccountController::class, 'scanWordPress'])->name('publish.accounts.scan-wp');
    Route::post('/publish/users/{id}/scan-wordpress-single', [PublishAccountController::class, 'scanWordPressSingle'])->name('publish.accounts.scan-wp-single');
    Route::post('/publish/users/{id}/add-site', [PublishAccountController::class, 'addSite'])->name('publish.accounts.add-site');
    Route::delete('/publish/users/{id}/remove-site/{siteId}', [PublishAccountController::class, 'removeSite'])->name('publish.accounts.remove-site');
    Route::post('/publish/users/{id}/default-site', [PublishAccountController::class, 'updateDefaultSite'])->name('publish.accounts.update-default-site');

    // Sites
    Route::get('/publish/sites', [PublishSiteController::class, 'index'])->name('publish.sites.index');
    Route::get('/publish/sites/create', [PublishSiteController::class, 'create'])->name('publish.sites.create');
    Route::post('/publish/sites', [PublishSiteController::class, 'store'])->name('publish.sites.store');
    Route::get('/publish/sites/{id}', [PublishSiteController::class, 'show'])->name('publish.sites.show');
    Route::get('/publish/sites/{id}/edit', [PublishSiteController::class, 'edit'])->name('publish.sites.edit');
    Route::put('/publish/sites/{id}', [PublishSiteController::class, 'update'])->name('publish.sites.update');
    Route::post('/publish/sites/{id}/test', [PublishSiteController::class, 'testConnection'])->name('publish.sites.test');
    Route::post('/publish/sites/{id}/test-write', [PublishSiteController::class, 'testWriteAccess'])->name('publish.sites.test-write');
    Route::get('/publish/sites/{id}/authors', [PublishSiteController::class, 'getAuthors'])->name('publish.sites.authors');
    Route::post('/publish/sites/{id}/set-author', [PublishSiteController::class, 'setDefaultAuthor'])->name('publish.sites.set-author');

    // Templates
    Route::get('/publish/templates', [PublishTemplateController::class, 'index'])->name('publish.templates.index');
    Route::get('/publish/templates/create', [PublishTemplateController::class, 'create'])->name('publish.templates.create');
    Route::post('/publish/templates', [PublishTemplateController::class, 'store'])->name('publish.templates.store');
    Route::get('/publish/templates/{id}', [PublishTemplateController::class, 'show'])->name('publish.templates.show');
    Route::get('/publish/templates/{id}/edit', [PublishTemplateController::class, 'edit'])->name('publish.templates.edit');
    Route::put('/publish/templates/{id}', [PublishTemplateController::class, 'update'])->name('publish.templates.update');
    Route::delete('/publish/templates/{id}', [PublishTemplateController::class, 'destroy'])->name('publish.templates.destroy');

    // ═══ Campaigns ═══
    Route::get('/campaigns', [CampaignController::class, 'index'])->name('campaigns.index');
    Route::get('/campaigns/create', [CampaignController::class, 'create'])->name('campaigns.create');
    Route::post('/campaigns', [CampaignController::class, 'store'])->name('campaigns.store');
    // Campaign Presets (before {id} wildcard)
    Route::get('/campaigns/presets', [CampaignPresetController::class, 'index'])->name('campaigns.presets.index');
    Route::post('/campaigns/presets', [CampaignPresetController::class, 'store'])->name('campaigns.presets.store');
    Route::get('/campaigns/presets/{id}', [CampaignPresetController::class, 'show'])->name('campaigns.presets.show');
    Route::put('/campaigns/presets/{id}', [CampaignPresetController::class, 'update'])->name('campaigns.presets.update');
    Route::delete('/campaigns/presets/{id}', [CampaignPresetController::class, 'destroy'])->name('campaigns.presets.destroy');
    Route::post('/campaigns/presets/{id}/toggle-default', [CampaignPresetController::class, 'toggleDefault'])->name('campaigns.presets.toggle-default');
    // Campaign CRUD
    Route::get('/campaigns/{id}', [CampaignController::class, 'show'])->name('campaigns.show');
    Route::get('/campaigns/{id}/edit', [CampaignController::class, 'edit'])->name('campaigns.edit');
    Route::put('/campaigns/{id}', [CampaignController::class, 'update'])->name('campaigns.update');
    Route::post('/campaigns/{id}/activate', [CampaignController::class, 'activate'])->name('campaigns.activate');
    Route::post('/campaigns/{id}/pause', [CampaignController::class, 'pause'])->name('campaigns.pause');
    Route::post('/campaigns/{id}/duplicate', [CampaignController::class, 'duplicate'])->name('campaigns.duplicate');
    Route::post('/campaigns/{id}/run-now', [CampaignController::class, 'runNow'])->name('campaigns.run-now');

    // AI Activity
    Route::get('/publish/ai-activity', [AiActivityController::class, 'index'])->name('publish.ai-activity.index');

    // AI Smart Edit Templates
    Route::get('/article/smart-edits', [AiSmartEditController::class, 'index'])->name('publish.smart-edits.index');
    Route::post('/article/smart-edits', [AiSmartEditController::class, 'store'])->name('publish.smart-edits.store');
    Route::put('/article/smart-edits/{id}', [AiSmartEditController::class, 'update'])->name('publish.smart-edits.update');
    Route::delete('/article/smart-edits/{id}', [AiSmartEditController::class, 'destroy'])->name('publish.smart-edits.destroy');

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
    Route::get('/search/articles', [PublishSearchController::class, 'articles'])->name('publish.search.articles');
    Route::post('/search/articles', [PublishSearchController::class, 'searchArticles'])->name('publish.search.articles.post');

    // ═══ Article Pipeline ═══
    Route::get('/article/publish', [PublishPipelineController::class, 'index'])->name('publish.pipeline');
    Route::post('/article/publish/check-sources', [PublishPipelineController::class, 'checkSources'])->name('publish.pipeline.check');
    Route::post('/article/publish/spin', [PublishPipelineController::class, 'spin'])->name('publish.pipeline.spin');
    Route::post('/article/publish/generate-metadata', [PublishPipelineController::class, 'generateMetadata'])->name('publish.pipeline.metadata');
    Route::post('/article/publish/prepare', [PublishPipelineController::class, 'prepareForWordpress'])->name('publish.pipeline.prepare');
    Route::post('/article/publish/publish', [PublishPipelineController::class, 'publishToWordpress'])->name('publish.pipeline.publish');
    Route::post('/article/publish/save-draft', [PublishPipelineController::class, 'saveDraft'])->name('publish.pipeline.save-draft');
    Route::post('/article/publish/detect-ai', [PublishPipelineController::class, 'detectAi'])->name('publish.pipeline.detect-ai');

    // ═══ Article Editor ═══
    Route::get('/article/editor', [PublishArticleController::class, 'editor'])->name('publish.editor');
    Route::get('/article/editor/{id}', [PublishArticleController::class, 'editor'])->name('publish.editor.load');

    // ═══ Drafted Articles ═══
    Route::get('/article/articles', [PublishDraftController::class, 'index'])->name('publish.drafts.index');
    Route::post('/article/articles', [PublishDraftController::class, 'store'])->name('publish.drafts.store');
    Route::get('/article/articles/{id}', [PublishDraftController::class, 'show'])->name('publish.drafts.show');
    Route::put('/article/articles/{id}', [PublishDraftController::class, 'update'])->name('publish.drafts.update');
    Route::delete('/article/articles/{id}', [PublishDraftController::class, 'destroy'])->name('publish.drafts.destroy');
    Route::post('/article/articles/bulk-delete', [PublishDraftController::class, 'bulkDestroy'])->name('publish.drafts.bulk-destroy');

    // ═══ Bookmarked Articles ═══
    Route::get('/article/bookmarks', [PublishBookmarkController::class, 'index'])->name('publish.bookmarks.index');
    Route::post('/article/bookmarks', [PublishBookmarkController::class, 'store'])->name('publish.bookmarks.store');
    Route::put('/article/bookmarks/{id}', [PublishBookmarkController::class, 'update'])->name('publish.bookmarks.update');
    Route::delete('/article/bookmarks/{id}', [PublishBookmarkController::class, 'destroy'])->name('publish.bookmarks.destroy');

    // ═══ Prompts ═══
    Route::get('/publishing/prompts', [PublishPromptController::class, 'index'])->name('publish.prompts.index');
    Route::post('/publishing/prompts', [PublishPromptController::class, 'store'])->name('publish.prompts.store');
    Route::get('/publishing/prompts/{id}', [PublishPromptController::class, 'show'])->name('publish.prompts.show');
    Route::put('/publishing/prompts/{id}', [PublishPromptController::class, 'update'])->name('publish.prompts.update');
    Route::delete('/publishing/prompts/{id}', [PublishPromptController::class, 'destroy'])->name('publish.prompts.destroy');

    // ═══ Article Presets ═══
    Route::get('/publishing/presets', [PublishPresetController::class, 'index'])->name('publish.presets.index');
    Route::post('/publishing/presets', [PublishPresetController::class, 'store'])->name('publish.presets.store');
    Route::get('/publishing/presets/{id}', [PublishPresetController::class, 'show'])->name('publish.presets.show');
    Route::put('/publishing/presets/{id}', [PublishPresetController::class, 'update'])->name('publish.presets.update');
    Route::delete('/publishing/presets/{id}', [PublishPresetController::class, 'destroy'])->name('publish.presets.destroy');
    Route::post('/publishing/presets/{id}/toggle-default', [PublishPresetController::class, 'toggleDefault'])->name('publish.presets.toggle-default');

    // ═══ Master Settings ═══
    Route::get('/publishing/settings', [PublishMasterSettingController::class, 'index'])->name('publish.settings.master');
    Route::post('/publishing/settings', [PublishMasterSettingController::class, 'store'])->name('publish.settings.master.store');
    Route::put('/publishing/settings/{id}', [PublishMasterSettingController::class, 'update'])->name('publish.settings.master.update');
    Route::delete('/publishing/settings/{id}', [PublishMasterSettingController::class, 'destroy'])->name('publish.settings.master.destroy');
    Route::post('/publishing/settings/save-prompt', [PublishMasterSettingController::class, 'savePrompt'])->name('publish.settings.master.save-prompt');
    Route::post('/publishing/settings/save-setting', [PublishMasterSettingController::class, 'saveSetting'])->name('publish.settings.master.save-setting');

    // ═══ Schedule ═══
    Route::get('/schedule', [PublishScheduleController::class, 'index'])->name('publish.schedule.index');
    Route::post('/schedule/fetch', [PublishScheduleController::class, 'fetchScheduled'])->name('publish.schedule.fetch');

    // ═══ User search (shared AJAX endpoint for type-ahead) ═══
    Route::get('/api/users/search', [PublishPipelineController::class, 'searchUsers'])->name('publish.users.search');
});
