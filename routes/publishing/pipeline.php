<?php

use hexa_app_publish\Publishing\Pipeline\Http\Controllers\PipelineController;
use hexa_app_publish\Publishing\Pipeline\Http\Controllers\PipelineStateController;
use hexa_app_publish\Publishing\Pipeline\Http\Controllers\PressReleaseWorkflowController;

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
Route::post('/article/publish/state', [PipelineStateController::class, 'save'])->name('publish.pipeline.state.save');
Route::post('/article/publish/upload-source-doc', [PipelineController::class, 'uploadSourceDocument'])->name('publish.pipeline.upload-source-doc');
Route::post('/article/publish/upload-photo', [PipelineController::class, 'uploadPhoto'])->name('publish.pipeline.upload-photo');
Route::post('/article/publish/press-release/upload-documents', [PressReleaseWorkflowController::class, 'uploadDocuments'])->name('publish.pipeline.press-release.upload-documents');
Route::post('/article/publish/press-release/detect-fields', [PressReleaseWorkflowController::class, 'detectFields'])->name('publish.pipeline.press-release.detect-fields');
Route::post('/article/publish/press-release/detect-photos', [PressReleaseWorkflowController::class, 'detectPhotos'])->name('publish.pipeline.press-release.detect-photos');

// User search (shared AJAX endpoint for type-ahead)
Route::get('/api/users/search', [PipelineController::class, 'searchUsers'])->name('publish.users.search');

// Profile search (for PR subject picker)
Route::get('/api/profiles/search', [PipelineController::class, 'searchProfiles'])->name('publish.profiles.search');
