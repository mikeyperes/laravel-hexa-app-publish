<?php

use hexa_app_publish\Publishing\Templates\Http\Controllers\TemplateController;

Route::get('/publish/article-presets', [TemplateController::class, 'index'])->name('publish.templates.index');
Route::get('/publish/article-presets/create', [TemplateController::class, 'create'])->name('publish.templates.create');
Route::post('/publish/article-presets', [TemplateController::class, 'store'])->name('publish.templates.store');
Route::get('/publish/article-presets/{id}', [TemplateController::class, 'show'])->name('publish.templates.show');
Route::get('/publish/article-presets/{id}/edit', [TemplateController::class, 'edit'])->name('publish.templates.edit');
Route::put('/publish/article-presets/{id}', [TemplateController::class, 'update'])->name('publish.templates.update');
Route::delete('/publish/article-presets/{id}', [TemplateController::class, 'destroy'])->name('publish.templates.destroy');
