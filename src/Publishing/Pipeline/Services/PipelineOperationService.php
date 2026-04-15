<?php

namespace hexa_app_publish\Publishing\Pipeline\Services;

use hexa_app_publish\Publishing\Articles\Models\PublishArticle;
use hexa_app_publish\Publishing\Pipeline\Models\PublishPipelineOperation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;

class PipelineOperationService
{
    private const DEFAULT_QUEUE = 'publish-pipeline';

    public function __construct(
        private PipelineActivityService $activityService
    ) {}

    public function activeForArticle(PublishArticle $article, string $operationType): ?PublishPipelineOperation
    {
        return PublishPipelineOperation::query()
            ->where('publish_article_id', $article->id)
            ->where('operation_type', $operationType)
            ->whereIn('status', [PublishPipelineOperation::STATUS_QUEUED, PublishPipelineOperation::STATUS_RUNNING])
            ->latest('id')
            ->first();
    }

    public function latestForArticle(PublishArticle $article, string $operationType): ?PublishPipelineOperation
    {
        return PublishPipelineOperation::query()
            ->where('publish_article_id', $article->id)
            ->where('operation_type', $operationType)
            ->latest('id')
            ->first();
    }

    public function create(
        PublishArticle $article,
        string $operationType,
        array $requestSummary = [],
        array $options = []
    ): PublishPipelineOperation {
        return PublishPipelineOperation::create([
            'publish_article_id' => $article->id,
            'publish_site_id' => $options['publish_site_id'] ?? null,
            'created_by' => $options['created_by'] ?? null,
            'operation_type' => $operationType,
            'status' => PublishPipelineOperation::STATUS_QUEUED,
            'workflow_type' => $options['workflow_type'] ?? null,
            'transport' => $options['transport'] ?? null,
            'queue_connection' => $options['queue_connection'] ?? null,
            'queue_name' => $options['queue_name'] ?? null,
            'client_trace' => ($options['client_trace'] ?? null) ?: ('pipeline-' . $operationType . '-' . Str::lower((string) Str::uuid())),
            'trace_id' => $options['trace_id'] ?? (string) Str::uuid(),
            'debug_enabled' => (bool) ($options['debug_enabled'] ?? false),
            'request_summary' => $requestSummary ?: null,
        ]);
    }

    public function supportsLiveStream(): bool
    {
        if (app()->runningUnitTests()) {
            return false;
        }

        return !(app()->environment('local') && php_sapi_name() === 'cli-server');
    }

    public function detectExecutionStrategy(): array
    {
        $connection = (string) config('queue.default', 'sync');
        $queueName = (string) config('hws-publish.pipeline_operation_queue', self::DEFAULT_QUEUE);

        if (app()->runningUnitTests()) {
            return [
                'transport' => 'sync',
                'queue_connection' => $connection,
                'queue_name' => $queueName,
            ];
        }

        if ($connection === 'sync') {
            return [
                'transport' => 'after_response',
                'queue_connection' => $connection,
                'queue_name' => $queueName,
            ];
        }

        return [
            'transport' => $this->queueWorkerRunning()
                ? 'queue'
                : ($this->shouldSpawnTransientQueueWorker($connection) ? 'queue_once' : 'after_response'),
            'queue_connection' => $connection,
            'queue_name' => $queueName,
        ];
    }

    public function spawnTransientQueueWorker(string $connection, string $queueName): void
    {
        if ($connection === 'sync') {
            return;
        }

        if ($this->queueWorkerRunning()) {
            return;
        }

        $basePath = base_path();
        $phpBinary = PHP_BINARY ?: 'php';
        $queueArg = escapeshellarg($queueName);
        $connectionArg = escapeshellarg($connection);
        $phpArg = escapeshellarg($phpBinary);
        $baseArg = escapeshellarg($basePath);
        $logPath = storage_path('logs/pipeline-queue-worker-' . Str::lower((string) Str::uuid()) . '.log');
        $logArg = escapeshellarg($logPath);

        $command = "cd {$baseArg} && nohup {$phpArg} artisan queue:work {$connectionArg} --queue={$queueArg} --once --stop-when-empty --tries=1 --timeout=1800 > {$logArg} 2>&1 &";

        try {
            Process::run($command);
        } catch (\Throwable) {
            // Best-effort only. The web request should still return the queued operation.
        }
    }

    public function start(PublishPipelineOperation $operation): ?PublishPipelineOperation
    {
        $updated = PublishPipelineOperation::query()
            ->whereKey($operation->id)
            ->where('status', PublishPipelineOperation::STATUS_QUEUED)
            ->update([
                'status' => PublishPipelineOperation::STATUS_RUNNING,
                'started_at' => now(),
                'last_event_at' => now(),
                'updated_at' => now(),
            ]);

        if ($updated !== 1) {
            return null;
        }

        return $operation->fresh();
    }

    public function appendEvent(
        PublishPipelineOperation $operation,
        string $scope,
        string $type,
        string $message,
        array $extra = []
    ): array {
        return DB::transaction(function () use ($operation, $scope, $type, $message, $extra) {
            /** @var PublishPipelineOperation $locked */
            $locked = PublishPipelineOperation::query()->lockForUpdate()->findOrFail($operation->id);
            $sequence = (int) $locked->event_sequence + 1;
            $capturedAt = now();

            $entry = [
                'id' => $sequence,
                'client_event_id' => $locked->client_trace . ':server:' . $sequence,
                'run_trace' => $locked->client_trace,
                'captured_at' => $capturedAt->toIso8601String(),
                'scope' => $scope,
                'type' => $type,
                'message' => $message,
                'stage' => $this->stringOrNull($extra['stage'] ?? null),
                'substage' => $this->stringOrNull($extra['substage'] ?? null),
                'trace_id' => $this->stringOrNull($extra['trace_id'] ?? $locked->trace_id),
                'duration_ms' => $this->intOrNull($extra['duration_ms'] ?? null),
                'sequence_no' => $this->intOrNull($extra['sequence_no'] ?? $sequence),
                'method' => $this->stringOrNull($extra['method'] ?? null),
                'status' => $this->intOrNull($extra['status'] ?? $extra['status_code'] ?? null),
                'url' => $this->stringOrNull($extra['url'] ?? null),
                'details' => $this->stringOrNull($extra['details'] ?? null),
                'payload_preview' => $this->stringOrNull($extra['payload_preview'] ?? null),
                'response_preview' => $this->stringOrNull($extra['response_preview'] ?? null),
                'debug_only' => (bool) ($extra['debug_only'] ?? false),
                'step' => $this->intOrNull($extra['step'] ?? 7),
                'meta' => $this->normalizeMeta($extra),
            ];

            $this->activityService->appendServerEvent(
                $locked->article,
                $locked->client_trace,
                $entry,
                $locked->workflow_type,
                $locked->debug_enabled,
                $locked->created_by
            );

            $locked->forceFill([
                'event_sequence' => $sequence,
                'total_events' => $sequence,
                'last_stage' => $entry['stage'],
                'last_substage' => $entry['substage'],
                'last_message' => $message,
                'last_event_at' => $capturedAt,
            ])->save();

            return $entry;
        });
    }

    public function complete(PublishPipelineOperation $operation, array $resultPayload = [], ?string $message = null): PublishPipelineOperation
    {
        $operation->forceFill([
            'status' => PublishPipelineOperation::STATUS_COMPLETED,
            'result_payload' => $resultPayload ?: null,
            'last_message' => $message ?? ($resultPayload['message'] ?? $operation->last_message),
            'error_message' => null,
            'completed_at' => now(),
            'last_event_at' => now(),
        ])->save();

        return $operation->fresh();
    }

    public function fail(PublishPipelineOperation $operation, string $message, array $resultPayload = []): PublishPipelineOperation
    {
        $operation->forceFill([
            'status' => PublishPipelineOperation::STATUS_FAILED,
            'result_payload' => $resultPayload ?: null,
            'last_message' => $message,
            'error_message' => $message,
            'completed_at' => now(),
            'last_event_at' => now(),
        ])->save();

        return $operation->fresh();
    }

    private function queueWorkerRunning(): bool
    {
        try {
            $result = Process::run('ps aux 2>/dev/null | grep "[q]ueue:work" | grep -v grep');
            $workers = array_filter(explode("\n", trim($result->output())));

            return count($workers) > 0;
        } catch (\Throwable) {
            return false;
        }
    }

    private function shouldSpawnTransientQueueWorker(string $connection): bool
    {
        if ($connection === 'sync') {
            return false;
        }

        if (app()->environment('local') && php_sapi_name() === 'cli-server') {
            return true;
        }

        return false;
    }

    private function normalizeMeta(array $extra): ?array
    {
        $excluded = [
            'stage',
            'substage',
            'trace_id',
            'duration_ms',
            'sequence_no',
            'method',
            'status',
            'status_code',
            'url',
            'details',
            'payload_preview',
            'response_preview',
            'debug_only',
            'step',
        ];

        $meta = [];
        foreach ($extra as $key => $value) {
            if (in_array($key, $excluded, true)) {
                continue;
            }
            if ($value === null || $value === '') {
                continue;
            }
            $meta[$key] = $value;
        }

        return $meta ?: null;
    }

    private function intOrNull($value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    private function stringOrNull($value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
