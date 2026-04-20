<?php

namespace hexa_app_publish\Publishing\Campaigns\Services;

use hexa_app_publish\Publishing\Articles\Models\PublishArticle;
use hexa_app_publish\Publishing\Articles\Services\ArticlePersistenceService;
use hexa_app_publish\Publishing\Campaigns\Jobs\RunCampaignOperationJob;
use hexa_app_publish\Publishing\Campaigns\Models\PublishCampaign;
use hexa_app_publish\Publishing\Pipeline\Models\PublishPipelineOperation;
use hexa_app_publish\Publishing\Pipeline\Services\PipelineOperationService;

class CampaignRunOperationService
{
    public function __construct(
        private PipelineOperationService $operationService,
        private ArticlePersistenceService $articlePersistence,
        private CampaignModeResolver $modeResolver
    ) {}

    /**
     * @return array{operation: PublishPipelineOperation, article: PublishArticle}
     */
    public function start(PublishCampaign $campaign, ?string $mode = null): array
    {
        $resolvedMode = $this->modeResolver->normalizeDeliveryMode($mode ?: $campaign->delivery_mode);
        $resolved = app(CampaignSettingsResolver::class)->resolve($campaign);

        $article = $this->articlePersistence->create([
            'title' => 'Campaign run starting...',
            'status' => 'sourcing',
            'publish_site_id' => $campaign->publish_site_id,
            'publish_account_id' => $campaign->publish_account_id ?: null,
            'publish_campaign_id' => $campaign->id,
            'publish_template_id' => $resolved['publish_template_id'] ?? $campaign->publish_template_id,
            'preset_id' => $resolved['preset_id'] ?? $campaign->preset_id,
            'user_id' => $campaign->user_id,
            'created_by' => auth()->id() ?: $campaign->created_by,
            'ai_engine_used' => $resolved['ai_engine'] ?? $campaign->ai_engine,
            'author' => $resolved['author'] ?? $campaign->author,
            'article_type' => $resolved['article_type'] ?? $campaign->article_type,
            'delivery_mode' => $resolvedMode,
            'user_ip' => request()?->ip() ?: '0.0.0.0',
        ]);

        $strategy = $this->operationService->detectExecutionStrategy();
        $requestSummary = [
            'campaign_id' => $campaign->id,
            'campaign_name' => $campaign->name,
            'mode' => $resolvedMode,
            'site_id' => $campaign->publish_site_id,
            'template_id' => $resolved['publish_template_id'] ?? null,
            'preset_id' => $resolved['preset_id'] ?? null,
        ];

        $operation = $this->operationService->create($article, PublishPipelineOperation::TYPE_PUBLISH, $requestSummary, [
            'publish_site_id' => $campaign->publish_site_id,
            'created_by' => auth()->id() ?: $campaign->created_by,
            'workflow_type' => 'campaign',
            'transport' => $strategy['transport'] ?? null,
            'queue_connection' => $strategy['queue_connection'] ?? null,
            'queue_name' => $strategy['queue_name'] ?? null,
        ]);

        $payload = [
            'campaign_id' => $campaign->id,
            'mode' => $resolvedMode,
        ];

        $this->dispatch($strategy, $operation, $payload);

        return [
            'operation' => $operation->fresh(),
            'article' => $article->fresh(),
        ];
    }

    private function dispatch(array $strategy, PublishPipelineOperation $operation, array $payload): void
    {
        if (($strategy['transport'] ?? '') === 'sync') {
            app(CampaignRunOperationExecutor::class)->run($operation->id, $payload);
            return;
        }

        if (($strategy['transport'] ?? '') === 'queue') {
            RunCampaignOperationJob::dispatch($operation->id, $payload)
                ->onConnection($strategy['queue_connection'])
                ->onQueue($strategy['queue_name']);
            return;
        }

        if (($strategy['transport'] ?? '') === 'queue_once') {
            RunCampaignOperationJob::dispatch($operation->id, $payload)
                ->onConnection($strategy['queue_connection'])
                ->onQueue($strategy['queue_name']);

            $this->operationService->spawnTransientQueueWorker(
                (string) $strategy['queue_connection'],
                (string) $strategy['queue_name']
            );

            return;
        }

        app()->terminating(function () use ($operation, $payload): void {
            app(CampaignRunOperationExecutor::class)->run($operation->id, $payload);
        });
    }
}
