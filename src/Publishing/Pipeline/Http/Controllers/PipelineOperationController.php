<?php

namespace hexa_app_publish\Publishing\Pipeline\Http\Controllers;

use hexa_app_publish\Publishing\Articles\Models\PublishArticle;
use hexa_app_publish\Publishing\Pipeline\Models\PublishPipelineOperation;
use hexa_app_publish\Publishing\Pipeline\Services\PipelineActivityService;
use hexa_app_publish\Publishing\Pipeline\Services\PipelineOperationService;
use hexa_core\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PipelineOperationController extends Controller
{
    public function __construct(
        private PipelineActivityService $activityService,
        private PipelineOperationService $operationService
    ) {}

    public function latest(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'draft_id' => 'required|integer|exists:publish_articles,id',
            'operation_type' => 'required|in:prepare,publish',
            'after_sequence' => 'nullable|integer|min:0',
            'limit' => 'nullable|integer|min:1|max:500',
        ]);

        $draft = $this->resolveDraft((int) $validated['draft_id']);
        $operation = PublishPipelineOperation::query()
            ->where('publish_article_id', $draft->id)
            ->where('operation_type', $validated['operation_type'])
            ->latest('id')
            ->first();

        return $this->operationResponse($draft, $operation, (int) ($validated['after_sequence'] ?? 0), (int) ($validated['limit'] ?? 200));
    }

    public function show(Request $request, PublishPipelineOperation $operation): JsonResponse
    {
        $validated = $request->validate([
            'after_sequence' => 'nullable|integer|min:0',
            'limit' => 'nullable|integer|min:1|max:500',
        ]);

        $draft = $this->resolveDraft((int) $operation->publish_article_id);

        abort_unless((int) $operation->publish_article_id === (int) $draft->id, 404);

        return $this->operationResponse($draft, $operation, (int) ($validated['after_sequence'] ?? 0), (int) ($validated['limit'] ?? 200));
    }

    public function stream(Request $request, PublishPipelineOperation $operation): StreamedResponse
    {
        $validated = $request->validate([
            'after_sequence' => 'nullable|integer|min:0',
            'limit' => 'nullable|integer|min:1|max:500',
            'heartbeat_ms' => 'nullable|integer|min:250|max:10000',
            'timeout_seconds' => 'nullable|integer|min:5|max:180',
        ]);

        $draft = $this->resolveDraft((int) $operation->publish_article_id);

        abort_unless((int) $operation->publish_article_id === (int) $draft->id, 404);

        $afterSequence = (int) ($validated['after_sequence'] ?? 0);
        $limit = (int) ($validated['limit'] ?? 200);
        $heartbeatMs = (int) ($validated['heartbeat_ms'] ?? 1250);
        $timeoutSeconds = (int) ($validated['timeout_seconds'] ?? 90);

        return response()->stream(function () use ($draft, $operation, $afterSequence, $limit, $heartbeatMs, $timeoutSeconds) {
            ignore_user_abort(true);
            @set_time_limit(0);

            if (function_exists('session_write_close')) {
                @session_write_close();
            }

            $lastSequence = $afterSequence;
            $startedAt = microtime(true);
            $lastHeartbeatAt = microtime(true);
            $terminalDelivered = false;

            $flush = static function (array $payload): void {
                echo json_encode($payload, JSON_UNESCAPED_SLASHES) . "\n";
                @ob_flush();
                @flush();
            };

            $flush([
                'kind' => 'snapshot',
                'operation' => $this->serializeOperation($operation->fresh()),
                'events' => [],
            ]);

            while (!connection_aborted()) {
                /** @var PublishPipelineOperation|null $fresh */
                $fresh = PublishPipelineOperation::query()->find($operation->id);
                if (!$fresh || (int) $fresh->publish_article_id !== (int) $draft->id) {
                    $flush([
                        'kind' => 'terminal',
                        'operation' => null,
                        'message' => 'Operation no longer exists.',
                    ]);
                    break;
                }

                $events = $this->activityService->eventsForTrace($draft, $fresh->client_trace, $lastSequence, $limit);
                foreach ($events as $event) {
                    $lastSequence = max($lastSequence, (int) ($event['id'] ?? 0));
                    $flush([
                        'kind' => 'event',
                        'operation' => $this->serializeOperation($fresh),
                        'event' => $event,
                    ]);
                    $lastHeartbeatAt = microtime(true);
                }

                if ($fresh->isTerminal()) {
                    $flush([
                        'kind' => 'terminal',
                        'operation' => $this->serializeOperation($fresh->fresh()),
                        'final_sequence' => $lastSequence,
                    ]);
                    $terminalDelivered = true;
                    break;
                }

                $now = microtime(true);
                if (($now - $lastHeartbeatAt) * 1000 >= $heartbeatMs) {
                    $flush([
                        'kind' => 'heartbeat',
                        'operation' => $this->serializeOperation($fresh),
                        'last_sequence' => $lastSequence,
                    ]);
                    $lastHeartbeatAt = $now;
                }

                if (($now - $startedAt) >= $timeoutSeconds) {
                    break;
                }

                usleep($heartbeatMs * 1000);
            }

            if (!$terminalDelivered && !connection_aborted()) {
                /** @var PublishPipelineOperation|null $fresh */
                $fresh = PublishPipelineOperation::query()->find($operation->id);
                $flush([
                    'kind' => 'timeout',
                    'operation' => $fresh ? $this->serializeOperation($fresh) : null,
                    'last_sequence' => $lastSequence,
                ]);
            }
        }, 200, [
            'Content-Type' => 'application/x-ndjson',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'X-Accel-Buffering' => 'no',
            'Connection' => 'keep-alive',
        ]);
    }

    private function operationResponse(
        PublishArticle $draft,
        ?PublishPipelineOperation $operation,
        int $afterSequence,
        int $limit
    ): JsonResponse {
        if (!$operation) {
            return response()->json([
                'success' => true,
                'operation' => null,
                'events' => [],
            ]);
        }

        $events = $this->activityService->eventsForTrace($draft, $operation->client_trace, $afterSequence, $limit);

        return response()->json([
            'success' => true,
            'operation' => $this->serializeOperation($operation),
            'events' => $events,
        ]);
    }

    private function serializeOperation(?PublishPipelineOperation $operation): ?array
    {
        if (!$operation) {
            return null;
        }

        return [
            'id' => $operation->id,
            'draft_id' => $operation->publish_article_id,
            'site_id' => $operation->publish_site_id,
            'operation_type' => $operation->operation_type,
            'status' => $operation->status,
            'workflow_type' => $operation->workflow_type,
            'transport' => $operation->transport,
            'queue_connection' => $operation->queue_connection,
            'queue_name' => $operation->queue_name,
            'client_trace' => $operation->client_trace,
            'trace_id' => $operation->trace_id,
            'debug_enabled' => $operation->debug_enabled,
            'event_sequence' => $operation->event_sequence,
            'total_events' => $operation->total_events,
            'last_stage' => $operation->last_stage,
            'last_substage' => $operation->last_substage,
            'last_message' => $operation->last_message,
            'error_message' => $operation->error_message,
            'request_summary' => $operation->request_summary,
            'result_payload' => $operation->result_payload,
            'started_at' => optional($operation->started_at)->toIso8601String(),
            'completed_at' => optional($operation->completed_at)->toIso8601String(),
            'last_event_at' => optional($operation->last_event_at)->toIso8601String(),
            'stream_supported' => $this->operationService->supportsLiveStream(),
            'stream_url' => route('publish.pipeline.operations.stream', ['operation' => $operation->id]),
        ];
    }

    private function resolveDraft(int $draftId): PublishArticle
    {
        $draft = PublishArticle::findOrFail($draftId);
        $user = auth()->user();

        abort_unless(
            $user && ($user->isAdmin() || $draft->created_by === $user->id || $draft->user_id === $user->id),
            403
        );

        return $draft;
    }
}
