<?php

use hexa_app_publish\Publishing\Articles\Http\Controllers\ArticleController;
use hexa_app_publish\Publishing\Articles\Http\Controllers\DraftController;
use hexa_app_publish\Publishing\Articles\Http\Controllers\BookmarkController;

// Published articles
Route::get('/publish/articles', [ArticleController::class, 'index'])->name('publish.articles.index');
Route::get('/publish/articles/create', [ArticleController::class, 'create'])->name('publish.articles.create');
Route::post('/publish/articles', [ArticleController::class, 'store'])->name('publish.articles.store');
Route::get('/publish/articles/{id}', [ArticleController::class, 'show'])->name('publish.articles.show');
Route::get('/publish/articles/{id}/edit', [ArticleController::class, 'edit'])->name('publish.articles.edit');
Route::put('/publish/articles/{id}', [ArticleController::class, 'update'])->name('publish.articles.update');
Route::post('/publish/articles/{id}/publish', [ArticleController::class, 'publish'])->name('publish.articles.publish');
Route::post('/publish/articles/{id}/ai-check', [ArticleController::class, 'aiCheck'])->name('publish.articles.ai-check');
Route::post('/publish/articles/{id}/seo-check', [ArticleController::class, 'seoCheck'])->name('publish.articles.seo-check');
Route::post('/publish/articles/{id}/spin', [ArticleController::class, 'spin'])->name('publish.articles.spin');
Route::post('/publish/articles/{id}/insert-links', [ArticleController::class, 'insertLinks'])->name('publish.articles.insert-links');

// Photo & source search
Route::get('/publish/photos/search', [ArticleController::class, 'searchPhotos'])->name('publish.photos.search');
Route::get('/publish/sources/search', [ArticleController::class, 'searchSources'])->name('publish.sources.search');
Route::post('/publish/sources/scrape', [ArticleController::class, 'scrapeUrl'])->name('publish.sources.scrape');

// Editor
Route::get('/article/editor', [ArticleController::class, 'editor'])->name('publish.editor');
Route::get('/article/editor/{id}', [ArticleController::class, 'editor'])->name('publish.editor.load');

// Drafted articles
Route::get('/article/articles', [DraftController::class, 'index'])->name('publish.drafts.index');
Route::post('/article/articles', [DraftController::class, 'store'])->name('publish.drafts.store');
Route::get('/article/articles/{id}', [DraftController::class, 'show'])->name('publish.drafts.show');
Route::put('/article/articles/{id}', [DraftController::class, 'update'])->name('publish.drafts.update');
Route::delete('/article/articles/{id}', [DraftController::class, 'destroy'])->name('publish.drafts.destroy');
Route::post('/article/articles/bulk-delete', [DraftController::class, 'bulkDestroy'])->name('publish.drafts.bulk-destroy');

// Bookmarks
Route::get('/article/bookmarks', [BookmarkController::class, 'index'])->name('publish.bookmarks.index');
Route::post('/article/bookmarks', [BookmarkController::class, 'store'])->name('publish.bookmarks.store');
Route::put('/article/bookmarks/{id}', [BookmarkController::class, 'update'])->name('publish.bookmarks.update');
Route::delete('/article/bookmarks/{id}', [BookmarkController::class, 'destroy'])->name('publish.bookmarks.destroy');

// Failed sources
Route::post('/article/failed-sources', [BookmarkController::class, 'storeFailed'])->name('publish.failed-sources.store');
Route::delete('/article/failed-sources/{id}', [BookmarkController::class, 'destroyFailed'])->name('publish.failed-sources.destroy');
