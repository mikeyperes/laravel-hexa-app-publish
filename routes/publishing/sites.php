<?php

use hexa_app_publish\Http\Controllers\PublishSiteController;

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
