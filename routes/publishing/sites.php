<?php

use hexa_app_publish\Publishing\Sites\Http\Controllers\SiteController;

Route::get('/publish/sites', [SiteController::class, 'index'])->name('publish.sites.index');
Route::get('/publish/sites/create', [SiteController::class, 'create'])->name('publish.sites.create');
Route::post('/publish/sites', [SiteController::class, 'store'])->name('publish.sites.store');
Route::get('/publish/sites/{id}', [SiteController::class, 'show'])->name('publish.sites.show');
Route::get('/publish/sites/{id}/edit', [SiteController::class, 'edit'])->name('publish.sites.edit');
Route::put('/publish/sites/{id}', [SiteController::class, 'update'])->name('publish.sites.update');
Route::post('/publish/sites/{id}/test', [SiteController::class, 'testConnection'])->name('publish.sites.test');
Route::post('/publish/sites/{id}/test-write', [SiteController::class, 'testWriteAccess'])->name('publish.sites.test-write');
Route::get('/publish/sites/{id}/authors', [SiteController::class, 'getAuthors'])->name('publish.sites.authors');
Route::post('/publish/sites/{id}/set-author', [SiteController::class, 'setDefaultAuthor'])->name('publish.sites.set-author');
