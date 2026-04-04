<?php

use hexa_app_publish\Http\Controllers\PublishTemplateController;

Route::get('/publish/templates', [PublishTemplateController::class, 'index'])->name('publish.templates.index');
Route::get('/publish/templates/create', [PublishTemplateController::class, 'create'])->name('publish.templates.create');
Route::post('/publish/templates', [PublishTemplateController::class, 'store'])->name('publish.templates.store');
Route::get('/publish/templates/{id}', [PublishTemplateController::class, 'show'])->name('publish.templates.show');
Route::get('/publish/templates/{id}/edit', [PublishTemplateController::class, 'edit'])->name('publish.templates.edit');
Route::put('/publish/templates/{id}', [PublishTemplateController::class, 'update'])->name('publish.templates.update');
Route::delete('/publish/templates/{id}', [PublishTemplateController::class, 'destroy'])->name('publish.templates.destroy');
