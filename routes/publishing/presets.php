<?php

use hexa_app_publish\Publishing\Presets\Http\Controllers\PresetController;

Route::get('/publishing/presets', [PresetController::class, 'index'])->name('publish.presets.index');
Route::post('/publishing/presets', [PresetController::class, 'store'])->name('publish.presets.store');
Route::get('/publishing/presets/{id}', [PresetController::class, 'show'])->name('publish.presets.show');
Route::put('/publishing/presets/{id}', [PresetController::class, 'update'])->name('publish.presets.update');
Route::delete('/publishing/presets/{id}', [PresetController::class, 'destroy'])->name('publish.presets.destroy');
Route::post('/publishing/presets/{id}/toggle-default', [PresetController::class, 'toggleDefault'])->name('publish.presets.toggle-default');
