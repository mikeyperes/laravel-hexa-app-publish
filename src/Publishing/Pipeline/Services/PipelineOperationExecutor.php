<?php

namespace hexa_app_publish\Publishing\Pipeline\Services;

use hexa_app_publish\Models\PublishSite;
use hexa_app_publish\Publishing\Articles\Services\ArticlePersistenceService;
use hexa_app_publish\Publishing\Delivery\Services\WordPressDeliveryService;
use hexa_app_publish\Publishing\Delivery\Services\WordPressPreparationService;
use hexa_app_publish\Publishing\Pipeline\Models\PublishPipelineOperation;
use hexa_core\Models\User;

class PipelineOperationExecutor
{
    public function __construct(
        private PipelineOperationService $operationService,
        private WordPressPreparationService $preparationService,
        private WordPressDeliveryService $deliveryService,
        private ArticlePersistenceService $articlePersistence,
        private PublishPipelineApiContext $apiContext
    ) {}

    public function runPrepare(int $operationId, array $payload): void
    {
        /** @var PublishPipelineOperation|null $operation */
        $operation = PublishPipelineOperation::query()->with('article')->find($operationId);
        if (!$operation) {
            return;
        }

        $operation = $this->operationService->start($operation);
        if (!$operation) {
            return;
        }

        $user = $operation->created_by ? User::find($operation->created_by) : null;

        $this->apiContext->withContext([
            'draft_id' => $operation->publish_article_id,
            'client_trace' => $operation->client_trace,
            'trace_id' => $operation->trace_id,
            'debug_enabled' => $operation->debug_enabled,
            'workflow_type' => $operation->workflow_type,
            'user_id' => $operation->created_by,
            'user_name' => $user?->name,
            'operation_type' => PublishPipelineOperation::TYPE_PREPARE,
            'operation_id' => $operation->id,
            'source' => 'pipeline_operation',
        ], function () use ($operation, $payload): void {
            $startedAt = microtime(true);
            $site = PublishSite::findOrFail((int) $payload['site_id']);

            $this->operationService->appendEvent($operation, 'prepare', 'info', 'Prepare operation started', [
                'stage' => 'prepare',
                'substage' => 'bootstrap',
                'details' => $this->summarizePayload($payload),
                'step' => 7,
            ]);

            try {
                $progress = function (string $type, string $message, array $extra = []) use ($operation) {
                    $this->operationService->appendEvent($operation, 'prepare', $type, $message, array_merge($extra, [
                        'trace_id' => $operation->trace_id,
                        'step' => 7,
                    ]));
                };

                $result = $this->preparationService->prepare($site, (string) $payload['html'], [
                    'title' => $payload['title'] ?? null,
                    'categories' => $payload['categories'] ?? [],
                    'tags' => $payload['tags'] ?? [],
                    'photo_suggestions' => $payload['photo_suggestions'] ?? [],
                    'photo_meta' => $payload['photo_meta'] ?? [],
                    'featured_meta' => $payload['featured_meta'] ?? null,
                    'featured_url' => $payload['featured_url'] ?? null,
                    'draft_id' => $payload['draft_id'] ?? 0,
                    'existing_uploads' => $payload['existing_uploads'] ?? [],
                    'existing_featured_media_id' => $payload['existing_featured_media_id'] ?? null,
                    'trace_id' => $operation->trace_id,
                ], $progress);

                $durationMs = (int) round((microtime(true) - $startedAt) * 1000);
                $resultPayload = array_merge($result, [
                    'trace_id' => $operation->trace_id,
                    'duration_ms' => $durationMs,
                    'stage' => 'prepare',
                    'substage' => $result['success'] ? 'complete' : 'failed',
                ]);

                $this->operationService->appendEvent(
                    $operation,
                    'prepare',
                    'done',
                    $result['success'] ? 'Preparation complete' : ($result['message'] ?? 'Preparation failed'),
                    [
                        'stage' => 'prepare',
                        'substage' => $result['success'] ? 'complete' : 'failed',
                        'trace_id' => $operation->trace_id,
                        'duration_ms' => $durationMs,
                        'success' => $result['success'],
                        'step' => 7,
                    ]
                );

                if ($result['success']) {
                    $this->operationService->complete($operation, $resultPayload, 'Preparation complete');
                    return;
                }

                $this->operationService->fail($operation, $result['message'] ?? 'Preparation failed', $resultPayload);
            } catch (\Throwable $e) {
                $durationMs = (int) round((microtime(true) - $startedAt) * 1000);
                $this->operationService->appendEvent($operation, 'prepare', 'error', 'Backend exception: ' . $e->getMessage(), [
                    'stage' => 'prepare',
                    'substage' => 'exception',
                    'trace_id' => $operation->trace_id,
                    'duration_ms' => $durationMs,
                    'error_class' => get_class($e),
                    'error_file' => basename($e->getFile()),
                    'error_line' => $e->getLine(),
                    'step' => 7,
                ]);
                $this->operationService->appendEvent($operation, 'prepare', 'done', 'Preparation failed', [
                    'stage' => 'prepare',
                    'substage' => 'exception',
                    'trace_id' => $operation->trace_id,
                    'duration_ms' => $durationMs,
                    'success' => false,
                    'step' => 7,
                ]);
                $this->operationService->fail($operation, $e->getMessage(), [
                    'success' => false,
                    'message' => $e->getMessage(),
                    'trace_id' => $operation->trace_id,
                    'duration_ms' => $durationMs,
                    'stage' => 'prepare',
                    'substage' => 'exception',
                    'error_class' => get_class($e),
                ]);
            }
        });
    }

    public function runPublish(int $operationId, array $payload): void
    {
        /** @var PublishPipelineOperation|null $operation */
        $operation = PublishPipelineOperation::query()->with('article')->find($operationId);
        if (!$operation) {
            return;
        }

        $operation = $this->operationService->start($operation);
        if (!$operation) {
            return;
        }

        $user = $operation->created_by ? User::find($operation->created_by) : null;

        $this->apiContext->withContext([
            'draft_id' => $operation->publish_article_id,
            'client_trace' => $operation->client_trace,
            'trace_id' => $operation->trace_id,
            'debug_enabled' => $operation->debug_enabled,
            'workflow_type' => $operation->workflow_type,
            'user_id' => $operation->created_by,
            'user_name' => $user?->name,
            'operation_type' => PublishPipelineOperation::TYPE_PUBLISH,
            'operation_id' => $operation->id,
            'source' => 'pipeline_operation',
        ], function () use ($operation, $payload): void {
            $startedAt = microtime(true);
            $site = PublishSite::findOrFail((int) $payload['site_id']);
            $existingPostId = !empty($payload['existing_post_id']) ? (int) $payload['existing_post_id'] : null;
            $updatingExistingPost = $existingPostId > 0;
            $publishVerb = $updatingExistingPost ? 'Updating existing WordPress post' : 'Creating WordPress post';

            $this->operationService->appendEvent($operation, 'publish', 'info', 'Publish operation started', [
                'stage' => 'publish',
                'substage' => 'bootstrap',
                'details' => $this->summarizePayload($payload),
                'step' => 7,
            ]);

            try {
                $this->operationService->appendEvent($operation, 'publish', 'step', "Connecting to {$site->name}...", [
                    'stage' => 'publish',
                    'substage' => 'connect',
                    'step' => 7,
                ]);
                $this->operationService->appendEvent($operation, 'publish', 'step', $publishVerb . ($updatingExistingPost ? ' #' . $existingPostId : '') . " ({$payload['status']})...", [
                    'stage' => 'publish',
                    'substage' => $updatingExistingPost ? 'update_post' : 'create_post',
                    'step' => 7,
                ]);
                $this->operationService->appendEvent($operation, 'publish', 'info', "Title: {$payload['title']}", [
                    'stage' => 'publish',
                    'substage' => 'title',
                    'step' => 7,
                ]);
                if (!empty($payload['author'])) {
                    $this->operationService->appendEvent($operation, 'publish', 'info', "Author: {$payload['author']}", [
                        'stage' => 'publish',
                        'substage' => 'author',
                        'step' => 7,
                    ]);
                }
                if (!empty($payload['featured_media_id'])) {
                    $this->operationService->appendEvent($operation, 'publish', 'info', "Featured media ID: {$payload['featured_media_id']}", [
                        'stage' => 'publish',
                        'substage' => 'featured_media',
                        'step' => 7,
                    ]);
                }

                $deliveryOptions = [
                    'category_ids' => $payload['category_ids'] ?? [],
                    'tag_ids' => $payload['tag_ids'] ?? [],
                    'publication_term_ids' => $payload['publication_term_ids'] ?? [],
                    'date' => ($payload['status'] === 'future' && !empty($payload['date'])) ? $payload['date'] : null,
                    'featured_media_id' => $payload['featured_media_id'] ?? null,
                    'author' => $payload['author'] ?? null,
                    'excerpt' => $payload['excerpt'] ?? null,
                ];

                $delivery = $updatingExistingPost
                    ? $this->deliveryService->updatePost($site, $existingPostId, (string) $payload['title'], (string) $payload['html'], (string) $payload['status'], $deliveryOptions)
                    : $this->deliveryService->createPost($site, (string) $payload['title'], (string) $payload['html'], (string) $payload['status'], $deliveryOptions);

                if (!$delivery['success']) {
                    $durationMs = (int) round((microtime(true) - $startedAt) * 1000);
                    $this->operationService->appendEvent($operation, 'publish', 'error', "WordPress publish failed: {$delivery['message']}", [
                        'stage' => 'publish',
                        'substage' => 'wp_error',
                        'trace_id' => $operation->trace_id,
                        'duration_ms' => $durationMs,
                        'step' => 7,
                    ]);
                    $this->operationService->appendEvent($operation, 'publish', 'done', 'Publish failed.', [
                        'stage' => 'publish',
                        'substage' => 'wp_error',
                        'trace_id' => $operation->trace_id,
                        'duration_ms' => $durationMs,
                        'success' => false,
                        'step' => 7,
                    ]);
                    $this->operationService->fail($operation, $delivery['message'], [
                        'success' => false,
                        'message' => $delivery['message'],
                        'trace_id' => $operation->trace_id,
                        'duration_ms' => $durationMs,
                        'stage' => 'publish',
                        'substage' => 'wp_error',
                    ]);
                    return;
                }

                $this->operationService->appendEvent($operation, 'publish', 'success', ($updatingExistingPost ? 'WordPress post updated' : 'WordPress post created') . " — ID: {$delivery['post_id']}", [
                    'stage' => 'publish',
                    'substage' => $updatingExistingPost ? 'wp_updated' : 'wp_created',
                    'step' => 7,
                ]);
                if (!empty($delivery['post_url'])) {
                    $this->operationService->appendEvent($operation, 'publish', 'success', "Permalink: {$delivery['post_url']}", [
                        'stage' => 'publish',
                        'substage' => 'permalink',
                        'step' => 7,
                    ]);
                }

                $this->operationService->appendEvent($operation, 'publish', 'step', 'Saving article record...', [
                    'stage' => 'publish',
                    'substage' => 'persist',
                    'step' => 7,
                ]);

                $publishedAt = ($payload['status'] ?? null) === 'publish'
                    ? ($delivery['post_date'] ?? now())
                    : null;
                $deliveryMode = match ((string) ($payload['status'] ?? 'draft')) {
                    'publish' => 'auto-publish',
                    'draft' => 'draft-wordpress',
                    'future' => 'auto-publish',
                    default => 'draft-wordpress',
                };

                $article = $this->articlePersistence->createOrUpdate([
                    'pipeline_session_id' => $payload['pipeline_session_id'] ?? null,
                    'user_id' => $payload['user_id'] ?? $operation->created_by,
                    'publish_site_id' => $site->id,
                    'publish_template_id' => $payload['template_id'] ?? null,
                    'preset_id' => $payload['preset_id'] ?? null,
                    'article_type' => $payload['article_type'] ?? null,
                    'title' => $payload['title'],
                    'body' => $payload['html'],
                    'word_count' => $payload['word_count'] ?? str_word_count(strip_tags((string) $payload['html'])),
                    'ai_engine_used' => $payload['ai_model'] ?? null,
                    'ai_cost' => $payload['ai_cost'] ?? null,
                    'ai_provider' => $payload['ai_provider'] ?? 'anthropic',
                    'ai_tokens_input' => $payload['ai_tokens_input'] ?? null,
                    'ai_tokens_output' => $payload['ai_tokens_output'] ?? null,
                    'resolved_prompt' => $payload['resolved_prompt'] ?? null,
                    'photo_suggestions' => $payload['photo_suggestions'] ?? null,
                    'featured_image_search' => $payload['featured_image_search'] ?? null,
                    'user_ip' => $payload['user_ip'] ?? null,
                    'author' => $payload['author'] ?? $site->default_author ?? null,
                    'status' => 'completed',
                    'delivery_mode' => $deliveryMode,
                    'wp_post_id' => $delivery['post_id'],
                    'wp_post_url' => $delivery['post_url'],
                    'wp_status' => $delivery['post_status'] ?? $payload['status'],
                    'published_at' => $publishedAt,
                    'source_articles' => $payload['sources'] ?? null,
                    'categories' => $payload['categories'] ?? null,
                    'tags' => $payload['tags'] ?? null,
                    'wp_images' => $payload['wp_images'] ?? null,
                    'links_injected' => null,
                    'created_by' => $operation->created_by,
                ], $payload['draft_id'] ?? null);

                $this->operationService->appendEvent($operation, 'publish', 'success', "Article saved — #{$article->article_id}", [
                    'stage' => 'publish',
                    'substage' => 'persisted',
                    'step' => 7,
                ]);

                if (in_array($payload['status'], ['publish', 'draft'], true) && !empty($payload['draft_id'])) {
                    $this->operationService->appendEvent($operation, 'publish', 'step', 'Cleaning up temporary uploads...', [
                        'stage' => 'publish',
                        'substage' => 'cleanup',
                        'step' => 7,
                    ]);
                    try {
                        $cleanup = app(\hexa_app_publish\Publishing\Uploads\Services\ArticleUploadService::class);
                        $cleanup->cleanupAfterPublish((int) $payload['draft_id']);
                        $this->operationService->appendEvent($operation, 'publish', 'success', 'Temp uploads cleaned.', [
                            'stage' => 'publish',
                            'substage' => 'cleanup_complete',
                            'step' => 7,
                        ]);
                    } catch (\Throwable $e) {
                        $this->operationService->appendEvent($operation, 'publish', 'warning', 'Upload cleanup failed: ' . $e->getMessage(), [
                            'stage' => 'publish',
                            'substage' => 'cleanup_warning',
                            'step' => 7,
                        ]);
                    }
                }

                $durationMs = (int) round((microtime(true) - $startedAt) * 1000);
                $resultMessage = match ((string) ($delivery['post_status'] ?? $payload['status'] ?? 'draft')) {
                    'publish' => "Article published to {$site->name}. WP Post ID: {$delivery['post_id']}.",
                    'future' => "Article scheduled on {$site->name}. WP Post ID: {$delivery['post_id']}.",
                    default => "WordPress draft saved on {$site->name}. WP Post ID: {$delivery['post_id']}.",
                };

                $resultPayload = [
                    'success' => true,
                    'message' => $resultMessage,
                    'post_id' => $delivery['post_id'],
                    'post_url' => $delivery['post_url'],
                    'post_status' => $delivery['post_status'] ?? $payload['status'] ?? null,
                    'used_existing_post' => $updatingExistingPost,
                    'existing_post_id' => $existingPostId,
                    'article_id' => $article->id,
                    'article_url' => route('publish.articles.show', $article->id),
                    'stage' => 'publish',
                    'substage' => 'complete',
                    'trace_id' => $operation->trace_id,
                    'duration_ms' => $durationMs,
                ];

                $this->operationService->appendEvent($operation, 'publish', 'done', (($delivery['post_status'] ?? $payload['status'] ?? 'draft') === 'publish' ? 'Published' : (($delivery['post_status'] ?? $payload['status'] ?? 'draft') === 'future' ? 'Scheduled' : 'Draft saved')) . " on {$site->name}!", [
                    'stage' => 'publish',
                    'substage' => 'complete',
                    'trace_id' => $operation->trace_id,
                    'duration_ms' => $durationMs,
                    'success' => true,
                    'step' => 7,
                ]);
                $this->operationService->complete($operation, $resultPayload, $resultPayload['message']);
            } catch (\Throwable $e) {
                $durationMs = (int) round((microtime(true) - $startedAt) * 1000);
                $this->operationService->appendEvent($operation, 'publish', 'error', 'Backend exception: ' . $e->getMessage(), [
                    'stage' => 'publish',
                    'substage' => 'exception',
                    'trace_id' => $operation->trace_id,
                    'duration_ms' => $durationMs,
                    'error_class' => get_class($e),
                    'error_file' => basename($e->getFile()),
                    'error_line' => $e->getLine(),
                    'step' => 7,
                ]);
                $this->operationService->appendEvent($operation, 'publish', 'done', 'Publish failed.', [
                    'stage' => 'publish',
                    'substage' => 'exception',
                    'trace_id' => $operation->trace_id,
                    'duration_ms' => $durationMs,
                    'success' => false,
                    'step' => 7,
                ]);
                $this->operationService->fail($operation, $e->getMessage(), [
                    'success' => false,
                    'message' => $e->getMessage(),
                    'trace_id' => $operation->trace_id,
                    'duration_ms' => $durationMs,
                    'stage' => 'publish',
                    'substage' => 'exception',
                    'error_class' => get_class($e),
                ]);
            }
        });
    }

    private function summarizePayload(array $payload): string
    {
        $summary = [
            'draft_id' => $payload['draft_id'] ?? null,
            'site_id' => $payload['site_id'] ?? null,
            'title_length' => strlen((string) ($payload['title'] ?? '')),
            'html_length' => strlen((string) ($payload['html'] ?? '')),
            'category_count' => count($payload['categories'] ?? $payload['category_ids'] ?? []),
            'tag_count' => count($payload['tags'] ?? $payload['tag_ids'] ?? []),
            'wp_image_count' => count($payload['wp_images'] ?? []),
            'has_featured' => !empty($payload['featured_url'] ?? $payload['featured_media_id'] ?? null),
            'status' => $payload['status'] ?? null,
        ];

        return json_encode($summary);
    }
}
