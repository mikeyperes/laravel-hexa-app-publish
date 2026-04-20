<?php

namespace hexa_app_publish\Publishing\Campaigns\Services;

use hexa_app_publish\Publishing\Articles\Models\PublishArticle;
use hexa_app_publish\Publishing\Pipeline\Models\PublishPipelineOperation;
use hexa_app_publish\Publishing\Pipeline\Services\PipelineOperationService;
use Throwable;

class CampaignRunOperationExecutor
{
    public function __construct(
        private PipelineOperationService $operationService,
        private CampaignExecutionService $executionService
    ) {}

    public function run(int $operationId, array $payload): void
    {
        /** @var PublishPipelineOperation|null $operation */
        $operation = PublishPipelineOperation::query()->with('article')->find($operationId);
        if (!$operation || !$operation->article instanceof PublishArticle) {
            return;
        }

        $operation = $this->operationService->start($operation);
        if (!$operation) {
            return;
        }

        $this->operationService->appendEvent($operation, 'campaign', 'info', 'Campaign run started.', [
            'stage' => 'settings',
            'substage' => 'bootstrap',
            'trace_id' => $operation->trace_id,
            'step' => 16,
        ]);

        try {
            $result = $this->executionService->runWithArticle(
                $operation->article,
                (int) ($payload['campaign_id'] ?? 0),
                (string) ($payload['mode'] ?? 'draft-wordpress'),
                null,
                function (string $type, string $message, array $extra = []) use ($operation): void {
                    $this->operationService->appendEvent($operation, 'campaign', $type, $message, array_merge($extra, [
                        'trace_id' => $operation->trace_id,
                        'step' => 16,
                    ]));
                }
            );

            $resultPayload = [
                'success' => $result['success'],
                'message' => $result['success']
                    ? ('Campaign completed. Article: ' . ($result['article']->article_id ?? '—'))
                    : ($result['message'] ?? 'Campaign run failed.'),
                'article_id' => $result['article']->id ?? null,
                'article_url' => isset($result['article']) && $result['article']
                    ? route('publish.articles.show', $result['article']->id)
                    : null,
                'wp_post_url' => $result['article']->wp_post_url ?? null,
                'log' => $result['log'] ?? [],
            ];

            $finalType = $result['success'] ? 'done' : 'error';
            $finalMessage = $resultPayload['message'];

            $this->operationService->appendEvent($operation, 'campaign', $finalType, $finalMessage, [
                'stage' => $result['success'] ? 'schedule' : ($result['failed_stage'] ?? 'persistence'),
                'substage' => $result['success'] ? 'complete' : 'failed',
                'trace_id' => $operation->trace_id,
                'step' => 16,
            ]);

            if ($result['success']) {
                $this->operationService->complete($operation, $resultPayload, $finalMessage);
                return;
            }

            $this->operationService->fail($operation, $finalMessage, $resultPayload);
        } catch (Throwable $e) {
            $message = 'Campaign exception: ' . $e->getMessage();
            $this->operationService->appendEvent($operation, 'campaign', 'error', $message, [
                'stage' => 'persistence',
                'substage' => 'exception',
                'trace_id' => $operation->trace_id,
                'error_class' => get_class($e),
                'error_file' => basename($e->getFile()),
                'error_line' => $e->getLine(),
                'step' => 16,
            ]);
            $this->operationService->fail($operation, $message, [
                'success' => false,
                'message' => $message,
                'error_class' => get_class($e),
            ]);
        }
    }
}
