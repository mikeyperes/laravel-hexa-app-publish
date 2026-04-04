<?php

use hexa_app_publish\Publishing\Accounts\Http\Controllers\AccountController;

Route::get('/publish/users', [AccountController::class, 'index'])->name('publish.accounts.index');
Route::get('/publish/users/{id}', [AccountController::class, 'show'])->name('publish.accounts.show');
Route::post('/publish/users/{id}/attach-account', [AccountController::class, 'attachAccount'])->name('publish.accounts.attach');
Route::delete('/publish/users/{id}/detach-account/{accountId}', [AccountController::class, 'detachAccount'])->name('publish.accounts.detach');
Route::post('/publish/users/{id}/scan-wordpress', [AccountController::class, 'scanWordPress'])->name('publish.accounts.scan-wp');
Route::post('/publish/users/{id}/scan-wordpress-single', [AccountController::class, 'scanWordPressSingle'])->name('publish.accounts.scan-wp-single');
Route::post('/publish/users/{id}/add-site', [AccountController::class, 'addSite'])->name('publish.accounts.add-site');
Route::delete('/publish/users/{id}/remove-site/{siteId}', [AccountController::class, 'removeSite'])->name('publish.accounts.remove-site');
Route::post('/publish/users/{id}/default-site', [AccountController::class, 'updateDefaultSite'])->name('publish.accounts.update-default-site');
