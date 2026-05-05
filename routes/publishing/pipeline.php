<?php

use hexa_app_publish\Publishing\Pipeline\Http\Controllers\PipelineController;
use hexa_app_publish\Publishing\Pipeline\Http\Controllers\PipelineActivityController;
use hexa_app_publish\Publishing\Pipeline\Http\Controllers\PipelineOperationController;
use hexa_app_publish\Publishing\Pipeline\Http\Controllers\PipelineStateController;
use hexa_app_publish\Publishing\Pipeline\Http\Controllers\PressReleaseWorkflowController;
use hexa_app_publish\Publishing\Pipeline\Http\Controllers\PrArticleWorkflowController;

Route::get('/article/publish', [PipelineController::class, 'index'])->name('publish.pipeline');
Route::post('/article/publish/check-sources', [PipelineController::class, 'checkSources'])->name('publish.pipeline.check');
Route::post('/article/publish/ai-search', [PipelineController::class, 'aiSearchArticles'])->name('publish.pipeline.ai-search');
Route::post('/article/publish/link-status', [PipelineController::class, 'checkLinkStatus'])->name('publish.pipeline.link-status');
Route::post('/article/publish/spin', [PipelineController::class, 'spin'])->name('publish.pipeline.spin');
Route::post('/article/publish/generate-metadata', [PipelineController::class, 'generateMetadata'])->name('publish.pipeline.metadata');
Route::post('/article/publish/prepare', [PipelineController::class, 'prepareForWordpress'])->name('publish.pipeline.prepare');
Route::post('/article/publish/publish', [PipelineController::class, 'publishToWordpress'])->name('publish.pipeline.publish');
Route::post('/article/publish/send-publication-notification', [PipelineController::class, 'sendPublicationNotification'])->name('publish.pipeline.send-publication-notification');
Route::post('/article/publish/save-draft', [PipelineController::class, 'saveDraft'])->name('publish.pipeline.save-draft');
Route::post('/article/publish/detect-ai', [PipelineController::class, 'detectAi'])->name('publish.pipeline.detect-ai');
Route::post('/article/publish/preview-prompt', [PipelineController::class, 'previewPrompt'])->name('publish.pipeline.preview-prompt');
Route::post('/article/publish/photo-meta', [PipelineController::class, 'generatePhotoMeta'])->name('publish.pipeline.photo-meta');
Route::post('/article/publish/state', [PipelineStateController::class, 'save'])->name('publish.pipeline.state.save');
Route::get('/article/publish/activity', [PipelineActivityController::class, 'index'])->name('publish.pipeline.activity.index');
Route::post('/article/publish/activity', [PipelineActivityController::class, 'sync'])->name('publish.pipeline.activity.sync');
Route::delete('/article/publish/activity', [PipelineActivityController::class, 'destroy'])->name('publish.pipeline.activity.clear');
Route::get('/article/publish/operations/latest', [PipelineOperationController::class, 'latest'])->name('publish.pipeline.operations.latest');
Route::get('/article/publish/operations/{operation}', [PipelineOperationController::class, 'show'])->name('publish.pipeline.operations.show');
Route::get('/article/publish/operations/{operation}/stream', [PipelineOperationController::class, 'stream'])->name('publish.pipeline.operations.stream');
Route::post('/article/publish/upload-source-doc', [PipelineController::class, 'uploadSourceDocument'])->name('publish.pipeline.upload-source-doc');
Route::post('/article/publish/upload-photo', [PipelineController::class, 'uploadPhoto'])->name('publish.pipeline.upload-photo');
Route::post('/article/publish/delete-media', [PipelineController::class, 'deleteMedia'])->name('publish.pipeline.delete-media');
Route::post('/article/publish/press-release/upload-documents', [PressReleaseWorkflowController::class, 'uploadDocuments'])->name('publish.pipeline.press-release.upload-documents');
Route::get('/article/publish/press-release/notion/search-episodes/live', [PressReleaseWorkflowController::class, 'smartSearchNotionEpisodes'])->name('publish.pipeline.press-release.search-notion-episodes.live');
Route::post('/article/publish/press-release/notion/search-episodes', [PressReleaseWorkflowController::class, 'searchNotionEpisodes'])->name('publish.pipeline.press-release.search-notion-episodes');
Route::post('/article/publish/press-release/notion/import-episode', [PressReleaseWorkflowController::class, 'importNotionEpisode'])->name('publish.pipeline.press-release.import-notion-episode');
Route::post('/article/publish/press-release/detect-fields', [PressReleaseWorkflowController::class, 'detectFields'])->name('publish.pipeline.press-release.detect-fields');
Route::post('/article/publish/press-release/detect-photos', [PressReleaseWorkflowController::class, 'detectPhotos'])->name('publish.pipeline.press-release.detect-photos');
Route::post('/article/publish/pr-article/import-context-url', [PrArticleWorkflowController::class, 'importContextUrl'])->name('publish.pipeline.pr-article.import-context-url');
Route::post('/article/publish/pr-article/import-google-docs', [PrArticleWorkflowController::class, 'importGoogleDocsContext'])->name('publish.pipeline.pr-article.import-google-docs');

// User search (shared AJAX endpoint for type-ahead)
Route::get('/api/users/search', [PipelineController::class, 'searchUsers'])->name('publish.users.search');

// Profile search (for PR subject picker)
Route::get('/api/profiles/search', [PipelineController::class, 'searchProfiles'])->name('publish.profiles.search');
Route::post('/api/profiles/search/resolve-notion', [PipelineController::class, 'resolveNotionSubject'])->name('publish.profiles.resolve-notion');
