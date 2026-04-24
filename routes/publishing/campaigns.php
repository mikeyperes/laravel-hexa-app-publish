<?php

use hexa_app_publish\Publishing\Campaigns\Http\Controllers\CampaignController;
use hexa_app_publish\Publishing\Campaigns\Http\Controllers\CampaignPresetController;

Route::get('/campaigns', [CampaignController::class, 'index'])->name('campaigns.index');
Route::get('/campaigns/create', [CampaignController::class, 'create'])->name('campaigns.create');
Route::post('/campaigns', [CampaignController::class, 'store'])->name('campaigns.store');
// Campaign Presets (before {id} wildcard)
Route::get('/campaigns/presets', [CampaignPresetController::class, 'index'])->name('campaigns.presets.index');
Route::post('/campaigns/presets', [CampaignPresetController::class, 'store'])->name('campaigns.presets.store');
Route::get('/campaigns/presets/{id}', [CampaignPresetController::class, 'show'])->name('campaigns.presets.show');
Route::put('/campaigns/presets/{id}', [CampaignPresetController::class, 'update'])->name('campaigns.presets.update');
Route::delete('/campaigns/presets/{id}', [CampaignPresetController::class, 'destroy'])->name('campaigns.presets.destroy');
Route::post('/campaigns/presets/{id}/toggle-default', [CampaignPresetController::class, 'toggleDefault'])->name('campaigns.presets.toggle-default');
// Campaign CRUD
Route::get('/campaigns/{id}', [CampaignController::class, 'show'])->name('campaigns.show');
Route::get('/campaigns/{id}/edit', [CampaignController::class, 'edit'])->name('campaigns.edit');
Route::put('/campaigns/{id}', [CampaignController::class, 'update'])->name('campaigns.update');
Route::post('/campaigns/{id}/activate', [CampaignController::class, 'activate'])->name('campaigns.activate');
Route::post('/campaigns/{id}/pause', [CampaignController::class, 'pause'])->name('campaigns.pause');
Route::post('/campaigns/{id}/duplicate', [CampaignController::class, 'duplicate'])->name('campaigns.duplicate');
Route::post('/campaigns/{id}/start-operation', [CampaignController::class, 'startOperation'])->name('campaigns.start-operation');
Route::post('/campaigns/{id}/run-now', [CampaignController::class, 'runNow'])->name('campaigns.run-now');
Route::get('/campaigns/{id}/authors/search', [CampaignController::class, 'searchAuthors'])->name('campaigns.authors.search');
Route::delete('/campaigns/{id}', [CampaignController::class, 'destroy'])->name('campaigns.destroy');
