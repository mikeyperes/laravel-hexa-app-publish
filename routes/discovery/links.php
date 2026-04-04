<?php

use hexa_app_publish\Http\Controllers\PublishLinkController;

Route::get('/publish/links', [PublishLinkController::class, 'index'])->name('publish.links.index');
Route::post('/publish/links', [PublishLinkController::class, 'storeLink'])->name('publish.links.store');
Route::delete('/publish/links/{id}', [PublishLinkController::class, 'destroyLink'])->name('publish.links.destroy');
Route::post('/publish/links/{id}/toggle', [PublishLinkController::class, 'toggleLink'])->name('publish.links.toggle');
Route::post('/publish/sitemaps', [PublishLinkController::class, 'storeSitemap'])->name('publish.sitemaps.store');
Route::post('/publish/sitemaps/{id}/refresh', [PublishLinkController::class, 'refreshSitemap'])->name('publish.sitemaps.refresh');
Route::delete('/publish/sitemaps/{id}', [PublishLinkController::class, 'destroySitemap'])->name('publish.sitemaps.destroy');
