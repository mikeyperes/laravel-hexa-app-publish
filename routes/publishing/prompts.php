<?php

use hexa_app_publish\Http\Controllers\PublishPromptController;

Route::get('/publishing/prompts', [PublishPromptController::class, 'index'])->name('publish.prompts.index');
Route::post('/publishing/prompts', [PublishPromptController::class, 'store'])->name('publish.prompts.store');
Route::get('/publishing/prompts/{id}', [PublishPromptController::class, 'show'])->name('publish.prompts.show');
Route::put('/publishing/prompts/{id}', [PublishPromptController::class, 'update'])->name('publish.prompts.update');
Route::delete('/publishing/prompts/{id}', [PublishPromptController::class, 'destroy'])->name('publish.prompts.destroy');
