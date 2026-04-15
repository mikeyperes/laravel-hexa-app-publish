<?php

use hexa_app_publish\Publishing\Settings\Http\Controllers\MasterSettingController;
use hexa_app_publish\Publishing\Settings\Http\Controllers\SettingsController;

// Master settings
Route::get('/publishing/settings', [MasterSettingController::class, 'index'])->name('publish.settings.master');
Route::post('/publishing/settings', [MasterSettingController::class, 'store'])->name('publish.settings.master.store');
Route::put('/publishing/settings/{id}', [MasterSettingController::class, 'update'])->name('publish.settings.master.update');
Route::delete('/publishing/settings/{id}', [MasterSettingController::class, 'destroy'])->name('publish.settings.master.destroy');
Route::post('/publishing/settings/save-prompt', [MasterSettingController::class, 'savePrompt'])->name('publish.settings.master.save-prompt');
Route::post('/publishing/settings/save-setting', [MasterSettingController::class, 'saveSetting'])->name('publish.settings.master.save-setting');

// Press release sources
Route::post('/publishing/settings/toggle-pr-source/{site}', [MasterSettingController::class, 'togglePressReleaseSource'])->name('publish.settings.toggle-pr-source');

// Integration tests
Route::post('/settings/test-integration', [SettingsController::class, 'testIntegration'])->name('settings.test-integration');
Route::post('/settings/image-copyright-blacklist', [SettingsController::class, 'saveImageCopyrightBlacklist'])->name('settings.image-copyright-blacklist.save');
