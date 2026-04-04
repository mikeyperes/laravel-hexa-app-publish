<?php

use hexa_app_publish\Quality\Detection\Http\Controllers\AiActivityController;
use hexa_app_publish\Quality\SmartEdits\Http\Controllers\SmartEditController;

// AI Activity
Route::get('/publish/ai-activity', [AiActivityController::class, 'index'])->name('publish.ai-activity.index');

// AI Smart Edit Templates
Route::get('/article/smart-edits', [SmartEditController::class, 'index'])->name('publish.smart-edits.index');
Route::post('/article/smart-edits', [SmartEditController::class, 'store'])->name('publish.smart-edits.store');
Route::put('/article/smart-edits/{id}', [SmartEditController::class, 'update'])->name('publish.smart-edits.update');
Route::delete('/article/smart-edits/{id}', [SmartEditController::class, 'destroy'])->name('publish.smart-edits.destroy');
