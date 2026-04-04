<?php

use hexa_app_publish\Publishing\Templates\Http\Controllers\TemplateController;

Route::get('/publish/templates', [TemplateController::class, 'index'])->name('publish.templates.index');
Route::get('/publish/templates/create', [TemplateController::class, 'create'])->name('publish.templates.create');
Route::post('/publish/templates', [TemplateController::class, 'store'])->name('publish.templates.store');
Route::get('/publish/templates/{id}', [TemplateController::class, 'show'])->name('publish.templates.show');
Route::get('/publish/templates/{id}/edit', [TemplateController::class, 'edit'])->name('publish.templates.edit');
Route::put('/publish/templates/{id}', [TemplateController::class, 'update'])->name('publish.templates.update');
Route::delete('/publish/templates/{id}', [TemplateController::class, 'destroy'])->name('publish.templates.destroy');
