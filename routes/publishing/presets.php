<?php

use hexa_app_publish\Http\Controllers\PublishPresetController;

Route::get('/publishing/presets', [PublishPresetController::class, 'index'])->name('publish.presets.index');
Route::post('/publishing/presets', [PublishPresetController::class, 'store'])->name('publish.presets.store');
Route::get('/publishing/presets/{id}', [PublishPresetController::class, 'show'])->name('publish.presets.show');
Route::put('/publishing/presets/{id}', [PublishPresetController::class, 'update'])->name('publish.presets.update');
Route::delete('/publishing/presets/{id}', [PublishPresetController::class, 'destroy'])->name('publish.presets.destroy');
Route::post('/publishing/presets/{id}/toggle-default', [PublishPresetController::class, 'toggleDefault'])->name('publish.presets.toggle-default');
