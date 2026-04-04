<?php

use hexa_app_publish\Http\Controllers\PublishMasterSettingController;
use hexa_app_publish\Http\Controllers\PublishSettingsController;

// Master settings
Route::get('/publishing/settings', [PublishMasterSettingController::class, 'index'])->name('publish.settings.master');
Route::post('/publishing/settings', [PublishMasterSettingController::class, 'store'])->name('publish.settings.master.store');
Route::put('/publishing/settings/{id}', [PublishMasterSettingController::class, 'update'])->name('publish.settings.master.update');
Route::delete('/publishing/settings/{id}', [PublishMasterSettingController::class, 'destroy'])->name('publish.settings.master.destroy');
Route::post('/publishing/settings/save-prompt', [PublishMasterSettingController::class, 'savePrompt'])->name('publish.settings.master.save-prompt');
Route::post('/publishing/settings/save-setting', [PublishMasterSettingController::class, 'saveSetting'])->name('publish.settings.master.save-setting');

// Integration tests
Route::post('/settings/test-integration', [PublishSettingsController::class, 'testIntegration'])->name('settings.test-integration');
