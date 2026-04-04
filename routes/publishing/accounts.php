<?php

use hexa_app_publish\Http\Controllers\PublishAccountController;

Route::get('/publish/users', [PublishAccountController::class, 'index'])->name('publish.accounts.index');
Route::get('/publish/users/{id}', [PublishAccountController::class, 'show'])->name('publish.accounts.show');
Route::post('/publish/users/{id}/attach-account', [PublishAccountController::class, 'attachAccount'])->name('publish.accounts.attach');
Route::delete('/publish/users/{id}/detach-account/{accountId}', [PublishAccountController::class, 'detachAccount'])->name('publish.accounts.detach');
Route::post('/publish/users/{id}/scan-wordpress', [PublishAccountController::class, 'scanWordPress'])->name('publish.accounts.scan-wp');
Route::post('/publish/users/{id}/scan-wordpress-single', [PublishAccountController::class, 'scanWordPressSingle'])->name('publish.accounts.scan-wp-single');
Route::post('/publish/users/{id}/add-site', [PublishAccountController::class, 'addSite'])->name('publish.accounts.add-site');
Route::delete('/publish/users/{id}/remove-site/{siteId}', [PublishAccountController::class, 'removeSite'])->name('publish.accounts.remove-site');
Route::post('/publish/users/{id}/default-site', [PublishAccountController::class, 'updateDefaultSite'])->name('publish.accounts.update-default-site');
