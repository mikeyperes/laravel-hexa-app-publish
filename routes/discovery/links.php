<?php

use hexa_app_publish\Discovery\Links\Http\Controllers\LinkController;

Route::get('/publish/links', [LinkController::class, 'index'])->name('publish.links.index');
Route::post('/publish/links', [LinkController::class, 'storeLink'])->name('publish.links.store');
Route::delete('/publish/links/{id}', [LinkController::class, 'destroyLink'])->name('publish.links.destroy');
Route::post('/publish/links/{id}/toggle', [LinkController::class, 'toggleLink'])->name('publish.links.toggle');
Route::post('/publish/sitemaps', [LinkController::class, 'storeSitemap'])->name('publish.sitemaps.store');
Route::post('/publish/sitemaps/{id}/refresh', [LinkController::class, 'refreshSitemap'])->name('publish.sitemaps.refresh');
Route::delete('/publish/sitemaps/{id}', [LinkController::class, 'destroySitemap'])->name('publish.sitemaps.destroy');
