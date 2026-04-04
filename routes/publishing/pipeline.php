<?php

use hexa_app_publish\Http\Controllers\PublishPipelineController;

Route::get('/article/publish', [PublishPipelineController::class, 'index'])->name('publish.pipeline');
Route::post('/article/publish/check-sources', [PublishPipelineController::class, 'checkSources'])->name('publish.pipeline.check');
Route::post('/article/publish/spin', [PublishPipelineController::class, 'spin'])->name('publish.pipeline.spin');
Route::post('/article/publish/generate-metadata', [PublishPipelineController::class, 'generateMetadata'])->name('publish.pipeline.metadata');
Route::post('/article/publish/prepare', [PublishPipelineController::class, 'prepareForWordpress'])->name('publish.pipeline.prepare');
Route::post('/article/publish/publish', [PublishPipelineController::class, 'publishToWordpress'])->name('publish.pipeline.publish');
Route::post('/article/publish/save-draft', [PublishPipelineController::class, 'saveDraft'])->name('publish.pipeline.save-draft');
Route::post('/article/publish/detect-ai', [PublishPipelineController::class, 'detectAi'])->name('publish.pipeline.detect-ai');

// User search (shared AJAX endpoint for type-ahead)
Route::get('/api/users/search', [PublishPipelineController::class, 'searchUsers'])->name('publish.users.search');
