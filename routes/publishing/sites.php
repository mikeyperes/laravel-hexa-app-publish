<?php

use hexa_app_publish\Http\Middleware\EnsurePublishSiteAccess;
use hexa_app_publish\Publishing\Sites\Http\Controllers\SiteController;
use hexa_package_user_roles\Http\Middleware\EnsureAdminAccess;

Route::get('/publish/sites', [SiteController::class, 'index'])->name('publish.sites.index');
Route::get('/publish/sites/{id}', [SiteController::class, 'show'])->middleware(EnsurePublishSiteAccess::class)->name('publish.sites.show');

Route::middleware([EnsureAdminAccess::class])->group(function () {
    Route::get('/publish/sites/create', [SiteController::class, 'create'])->name('publish.sites.create');
    Route::post('/publish/sites/scan-installs', [SiteController::class, 'scanInstalls'])->name('publish.sites.scan-installs');
    Route::post('/publish/sites', [SiteController::class, 'store'])->name('publish.sites.store');
    Route::get('/publish/sites/{id}/edit', [SiteController::class, 'edit'])->name('publish.sites.edit');
    Route::put('/publish/sites/{id}', [SiteController::class, 'update'])->name('publish.sites.update');
    Route::post('/publish/sites/{id}/test', [SiteController::class, 'testConnection'])->name('publish.sites.test');
    Route::post('/publish/sites/{id}/test-write', [SiteController::class, 'testWriteAccess'])->name('publish.sites.test-write');
    Route::get('/publish/sites/{id}/authors', [SiteController::class, 'getAuthors'])->name('publish.sites.authors');
    Route::get('/publish/sites/{id}/categories', [SiteController::class, 'getCategories'])->name('publish.sites.categories');
    Route::post('/publish/sites/{id}/set-author', [SiteController::class, 'setDefaultAuthor'])->name('publish.sites.set-author');
    Route::post('/publish/sites/{id}/set-author-cast', [SiteController::class, 'setAuthorCast'])->name('publish.sites.set-author-cast');
});
