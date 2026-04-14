<?php

use hexa_app_publish\Discovery\Sources\Http\Controllers\ScrapeActivityController;

Route::get('/publish/scrape-activity', [ScrapeActivityController::class, 'index'])->name('publish.scrape-activity');
Route::post('/publish/scrape-activity/ban', [ScrapeActivityController::class, 'ban'])->name('publish.scrape-activity.ban');
Route::post('/publish/scrape-activity/unban', [ScrapeActivityController::class, 'unban'])->name('publish.scrape-activity.unban');
Route::post('/publish/scrape-activity/note', [ScrapeActivityController::class, 'saveNote'])->name('publish.scrape-activity.save-note');
Route::get('/publish/scrape-activity/note', [ScrapeActivityController::class, 'getNote'])->name('publish.scrape-activity.get-note');
