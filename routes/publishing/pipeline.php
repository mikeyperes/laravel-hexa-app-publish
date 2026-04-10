<?php

use hexa_app_publish\Publishing\Pipeline\Http\Controllers\PipelineController;

Route::get('/article/publish', [PipelineController::class, 'index'])->name('publish.pipeline');
Route::post('/article/publish/check-sources', [PipelineController::class, 'checkSources'])->name('publish.pipeline.check');
Route::post('/article/publish/ai-search', [PipelineController::class, 'aiSearchArticles'])->name('publish.pipeline.ai-search');
Route::post('/article/publish/spin', [PipelineController::class, 'spin'])->name('publish.pipeline.spin');
Route::post('/article/publish/generate-metadata', [PipelineController::class, 'generateMetadata'])->name('publish.pipeline.metadata');
Route::post('/article/publish/prepare', [PipelineController::class, 'prepareForWordpress'])->name('publish.pipeline.prepare');
Route::post('/article/publish/publish', [PipelineController::class, 'publishToWordpress'])->name('publish.pipeline.publish');
Route::post('/article/publish/save-draft', [PipelineController::class, 'saveDraft'])->name('publish.pipeline.save-draft');
Route::post('/article/publish/detect-ai', [PipelineController::class, 'detectAi'])->name('publish.pipeline.detect-ai');
Route::post('/article/publish/preview-prompt', [PipelineController::class, 'previewPrompt'])->name('publish.pipeline.preview-prompt');
Route::post('/article/publish/photo-meta', [PipelineController::class, 'generatePhotoMeta'])->name('publish.pipeline.photo-meta');

// User search (shared AJAX endpoint for type-ahead)
Route::get('/api/users/search', [PipelineController::class, 'searchUsers'])->name('publish.users.search');

// Profile search (for PR subject picker)
Route::get('/api/profiles/search', [PipelineController::class, 'searchProfiles'])->name('publish.profiles.search');
