<?php

use hexa_app_publish\Publishing\Prompts\Http\Controllers\PromptController;

Route::get('/publishing/prompts', [PromptController::class, 'index'])->name('publish.prompts.index');
Route::post('/publishing/prompts', [PromptController::class, 'store'])->name('publish.prompts.store');
Route::get('/publishing/prompts/{id}', [PromptController::class, 'show'])->name('publish.prompts.show');
Route::put('/publishing/prompts/{id}', [PromptController::class, 'update'])->name('publish.prompts.update');
Route::delete('/publishing/prompts/{id}', [PromptController::class, 'destroy'])->name('publish.prompts.destroy');
