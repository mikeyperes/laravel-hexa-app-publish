<?php

use hexa_app_publish\Discovery\Search\Http\Controllers\SearchController;

Route::get('/search/images', [SearchController::class, 'images'])->name('publish.search.images');
Route::post('/search/images', [SearchController::class, 'searchImages'])->name('publish.search.images.post');
Route::get('/search/articles', [SearchController::class, 'articles'])->name('publish.search.articles');
Route::post('/search/articles', [SearchController::class, 'searchArticles'])->name('publish.search.articles.post');
