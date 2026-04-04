<?php

use hexa_app_publish\Http\Controllers\PublishArticleController;
use hexa_app_publish\Http\Controllers\PublishDraftController;
use hexa_app_publish\Http\Controllers\PublishBookmarkController;

// Published articles
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
Route::post('/publish/articles/{id}/insert-links', [PublishArticleController::class, 'insertLinks'])->name('publish.articles.insert-links');

// Photo & source search
Route::get('/publish/photos/search', [PublishArticleController::class, 'searchPhotos'])->name('publish.photos.search');
Route::get('/publish/sources/search', [PublishArticleController::class, 'searchSources'])->name('publish.sources.search');
Route::post('/publish/sources/scrape', [PublishArticleController::class, 'scrapeUrl'])->name('publish.sources.scrape');

// Editor
Route::get('/article/editor', [PublishArticleController::class, 'editor'])->name('publish.editor');
Route::get('/article/editor/{id}', [PublishArticleController::class, 'editor'])->name('publish.editor.load');

// Drafted articles
Route::get('/article/articles', [PublishDraftController::class, 'index'])->name('publish.drafts.index');
Route::post('/article/articles', [PublishDraftController::class, 'store'])->name('publish.drafts.store');
Route::get('/article/articles/{id}', [PublishDraftController::class, 'show'])->name('publish.drafts.show');
Route::put('/article/articles/{id}', [PublishDraftController::class, 'update'])->name('publish.drafts.update');
Route::delete('/article/articles/{id}', [PublishDraftController::class, 'destroy'])->name('publish.drafts.destroy');
Route::post('/article/articles/bulk-delete', [PublishDraftController::class, 'bulkDestroy'])->name('publish.drafts.bulk-destroy');

// Bookmarks
Route::get('/article/bookmarks', [PublishBookmarkController::class, 'index'])->name('publish.bookmarks.index');
Route::post('/article/bookmarks', [PublishBookmarkController::class, 'store'])->name('publish.bookmarks.store');
Route::put('/article/bookmarks/{id}', [PublishBookmarkController::class, 'update'])->name('publish.bookmarks.update');
Route::delete('/article/bookmarks/{id}', [PublishBookmarkController::class, 'destroy'])->name('publish.bookmarks.destroy');
