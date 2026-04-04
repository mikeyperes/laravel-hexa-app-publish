<?php

use hexa_app_publish\Http\Controllers\PublishSearchController;

Route::get('/search/images', [PublishSearchController::class, 'images'])->name('publish.search.images');
Route::post('/search/images', [PublishSearchController::class, 'searchImages'])->name('publish.search.images.post');
Route::get('/search/articles', [PublishSearchController::class, 'articles'])->name('publish.search.articles');
Route::post('/search/articles', [PublishSearchController::class, 'searchArticles'])->name('publish.search.articles.post');
