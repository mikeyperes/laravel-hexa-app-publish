<?php

use hexa_app_publish\Http\Controllers\AiActivityController;
use hexa_app_publish\Http\Controllers\AiSmartEditController;

// AI Activity
Route::get('/publish/ai-activity', [AiActivityController::class, 'index'])->name('publish.ai-activity.index');

// AI Smart Edit Templates
Route::get('/article/smart-edits', [AiSmartEditController::class, 'index'])->name('publish.smart-edits.index');
Route::post('/article/smart-edits', [AiSmartEditController::class, 'store'])->name('publish.smart-edits.store');
Route::put('/article/smart-edits/{id}', [AiSmartEditController::class, 'update'])->name('publish.smart-edits.update');
Route::delete('/article/smart-edits/{id}', [AiSmartEditController::class, 'destroy'])->name('publish.smart-edits.destroy');
