<?php

namespace hexa_app_publish\Publishing\Pipeline\Services;

use Carbon\Carbon;
use hexa_app_publish\Publishing\Articles\Models\PublishArticle;
use hexa_app_publish\Publishing\Pipeline\Models\PublishPipelineRun;
use hexa_app_publish\Publishing\Pipeline\Models\PublishPipelineRunEvent;
use hexa_core\Models\User;

class PipelineActivityService
{
    public function sync(
        PublishArticle $article,
        string $clientTrace,
        array $entries,
        ?string $workflowType = null,
        bool $debugEnabled = false,
        ?int $userId = null
    ): array {
        $userId = $this->resolveExistingUserId($userId);
        $entries = array_values(array_filter($entries, fn ($entry) => !empty($entry['client_event_id'] ?? null)));
        if (count($entries) === 0) {
            return [
                'run' => null,
                'synced_event_ids' => [],
                'total_events' => 0,
            ];
        }

        $firstAt = $this->resolveCapturedAt($entries[0] ?? null);
        $lastEntry = $entries[count($entries) - 1] ?? [];
        $lastAt = $this->resolveCapturedAt($lastEntry) ?: now();

        $run = PublishPipelineRun::updateOrCreate(
            [
                'publish_article_id' => $article->id,
                'client_trace' => $clientTrace,
            ],
            [
                'created_by' => $userId,
                'workflow_type' => $workflowType,
                'debug_enabled' => $debugEnabled,
                'started_at' => $firstAt ?: now(),
                'last_event_at' => $lastAt,
                'last_scope' => $this->stringOrNull($lastEntry['scope'] ?? null),
                'last_type' => $this->stringOrNull($lastEntry['type'] ?? null),
                'last_stage' => $this->stringOrNull($lastEntry['stage'] ?? null),
                'last_substage' => $this->stringOrNull($lastEntry['substage'] ?? null),
            ]
        );

        $now = now();
        $rows = [];
        foreach ($entries as $entry) {
            $rows[] = [
                'publish_pipeline_run_id' => $run->id,
                'publish_article_id' => $article->id,
                'client_event_id' => (string) $entry['client_event_id'],
                'run_trace' => $this->stringOrNull($entry['run_trace'] ?? $clientTrace),
                'captured_at' => $this->resolveCapturedAt($entry),
                'client_sequence' => $this->intOrNull($entry['id'] ?? $entry['client_sequence'] ?? null),
                'scope' => $this->stringOrNull($entry['scope'] ?? null),
                'type' => $this->stringOrNull($entry['type'] ?? null),
                'message' => $this->stringOrNull($entry['message'] ?? null),
                'stage' => $this->stringOrNull($entry['stage'] ?? null),
                'substage' => $this->stringOrNull($entry['substage'] ?? null),
                'trace_id' => $this->stringOrNull($entry['trace_id'] ?? null),
                'sequence_no' => $this->intOrNull($entry['sequence_no'] ?? null),
                'method' => $this->stringOrNull($entry['method'] ?? null),
                'status_code' => $this->intOrNull($entry['status'] ?? $entry['status_code'] ?? null),
                'duration_ms' => $this->intOrNull($entry['duration_ms'] ?? null),
                'step' => $this->intOrNull($entry['step'] ?? null),
                'url' => $this->stringOrNull($entry['url'] ?? null),
                'details' => $this->stringOrNull($entry['details'] ?? null),
                'payload_preview' => $this->stringOrNull($entry['payload_preview'] ?? null),
                'response_preview' => $this->stringOrNull($entry['response_preview'] ?? null),
                'debug_only' => (bool) ($entry['debug_only'] ?? false),
                'meta' => $this->buildMeta($entry),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        PublishPipelineRunEvent::insertOrIgnore($rows);

        $totalEvents = (int) $run->events()->count();
        $run->forceFill([
            'workflow_type' => $workflowType ?: $run->workflow_type,
            'debug_enabled' => $debugEnabled || $run->debug_enabled,
            'last_event_at' => $lastAt,
            'last_scope' => $this->stringOrNull($lastEntry['scope'] ?? null),
            'last_type' => $this->stringOrNull($lastEntry['type'] ?? null),
            'last_stage' => $this->stringOrNull($lastEntry['stage'] ?? null),
            'last_substage' => $this->stringOrNull($lastEntry['substage'] ?? null),
            'total_events' => $totalEvents,
        ])->save();

        return [
            'run' => $run->fresh(),
            'synced_event_ids' => array_values(array_map(fn ($row) => $row['client_event_id'], $rows)),
            'total_events' => $totalEvents,
        ];
    }

    public function latestRunEvents(PublishArticle $article, int $limit = 400, ?string $trace = null): array
    {
        $run = null;

        if ($trace !== null && trim($trace) !== '') {
            $run = PublishPipelineRun::where('publish_article_id', $article->id)
                ->where('client_trace', trim($trace))
                ->first();
        }

        if (!$run) {
            $run = PublishPipelineRun::where('publish_article_id', $article->id)
                ->orderByDesc('last_event_at')
                ->orderByDesc('id')
                ->first();
        }

        if (!$run) {
            return ['run' => null, 'events' => []];
        }

        $events = $run->events()
            ->orderByDesc('client_sequence')
            ->orderByDesc('id')
            ->limit(max(1, min($limit, 500)))
            ->get()
            ->reverse()
            ->values()
            ->map(fn (PublishPipelineRunEvent $event) => $this->formatEvent($event))
            ->all();

        return [
            'run' => $run,
            'events' => $events,
        ];
    }

    public function recentRuns(PublishArticle $article, int $limit = 10): array
    {
        return PublishPipelineRun::query()
            ->with(['article:id,title'])
            ->where('publish_article_id', $article->id)
            ->orderByDesc('last_event_at')
            ->orderByDesc('id')
            ->limit(max(1, min($limit, 25)))
            ->get()
            ->map(fn (PublishPipelineRun $run) => $this->formatRun($run))
            ->all();
    }

    public function draftApiEvents(PublishArticle $article, int $limit = 500): array
    {
        return PublishPipelineRunEvent::query()
            ->where('publish_article_id', $article->id)
            ->where('scope', 'api')
            ->orderByDesc('captured_at')
            ->orderByDesc('id')
            ->limit(max(1, min($limit, 2000)))
            ->get()
            ->map(fn (PublishPipelineRunEvent $event) => $this->formatEvent($event))
            ->all();
    }

    public function recentRunsForUser(User $user, int $limit = 12, ?int $excludeDraftId = null): array
    {
        return PublishPipelineRun::query()
            ->with(['article:id,title,created_by,user_id'])
            ->when(!$user->isAdmin(), function ($query) use ($user) {
                $query->whereHas('article', function ($articleQuery) use ($user) {
                    $articleQuery->where(function ($builder) use ($user) {
                        $builder->where('created_by', $user->id)
                            ->orWhere('user_id', $user->id);
                    });
                });
            })
            ->when($excludeDraftId, fn ($query) => $query->where('publish_article_id', '!=', $excludeDraftId))
            ->orderByDesc('last_event_at')
            ->orderByDesc('id')
            ->limit(max(1, min($limit, 25)))
            ->get()
            ->map(fn (PublishPipelineRun $run) => $this->formatRun($run))
            ->all();
    }

    public function clear(PublishArticle $article): int
    {
        return (int) PublishPipelineRun::where('publish_article_id', $article->id)->delete();
    }

    public function appendServerEvent(
        PublishArticle $article,
        string $clientTrace,
        array $entry,
        ?string $workflowType = null,
        bool $debugEnabled = false,
        ?int $userId = null
    ): PublishPipelineRunEvent {
        $userId = $this->resolveExistingUserId($userId);
        $capturedAt = $this->resolveCapturedAt($entry) ?: now();

        $run = PublishPipelineRun::updateOrCreate(
            [
                'publish_article_id' => $article->id,
                'client_trace' => $clientTrace,
            ],
            [
                'created_by' => $userId,
                'workflow_type' => $workflowType,
                'debug_enabled' => $debugEnabled,
                'started_at' => $capturedAt,
                'last_event_at' => $capturedAt,
                'last_scope' => $this->stringOrNull($entry['scope'] ?? null),
                'last_type' => $this->stringOrNull($entry['type'] ?? null),
                'last_stage' => $this->stringOrNull($entry['stage'] ?? null),
                'last_substage' => $this->stringOrNull($entry['substage'] ?? null),
            ]
        );

        $event = PublishPipelineRunEvent::updateOrCreate(
            [
                'publish_article_id' => $article->id,
                'client_event_id' => (string) $entry['client_event_id'],
            ],
            [
                'publish_pipeline_run_id' => $run->id,
                'run_trace' => $this->stringOrNull($entry['run_trace'] ?? $clientTrace),
                'captured_at' => $capturedAt,
                'client_sequence' => $this->intOrNull($entry['id'] ?? $entry['client_sequence'] ?? null),
                'scope' => $this->stringOrNull($entry['scope'] ?? null),
                'type' => $this->stringOrNull($entry['type'] ?? null),
                'message' => $this->stringOrNull($entry['message'] ?? null),
                'stage' => $this->stringOrNull($entry['stage'] ?? null),
                'substage' => $this->stringOrNull($entry['substage'] ?? null),
                'trace_id' => $this->stringOrNull($entry['trace_id'] ?? null),
                'sequence_no' => $this->intOrNull($entry['sequence_no'] ?? null),
                'method' => $this->stringOrNull($entry['method'] ?? null),
                'status_code' => $this->intOrNull($entry['status'] ?? $entry['status_code'] ?? null),
                'duration_ms' => $this->intOrNull($entry['duration_ms'] ?? null),
                'step' => $this->intOrNull($entry['step'] ?? null),
                'url' => $this->stringOrNull($entry['url'] ?? null),
                'details' => $this->stringOrNull($entry['details'] ?? null),
                'payload_preview' => $this->stringOrNull($entry['payload_preview'] ?? null),
                'response_preview' => $this->stringOrNull($entry['response_preview'] ?? null),
                'debug_only' => (bool) ($entry['debug_only'] ?? false),
                'meta' => $this->buildMeta($entry),
            ]
        );

        $run->forceFill([
            'workflow_type' => $workflowType ?: $run->workflow_type,
            'debug_enabled' => $debugEnabled || $run->debug_enabled,
            'last_event_at' => $capturedAt,
            'last_scope' => $this->stringOrNull($entry['scope'] ?? null),
            'last_type' => $this->stringOrNull($entry['type'] ?? null),
            'last_stage' => $this->stringOrNull($entry['stage'] ?? null),
            'last_substage' => $this->stringOrNull($entry['substage'] ?? null),
            'total_events' => (int) $run->events()->count(),
        ])->save();

        return $event->fresh();
    }

    private function resolveExistingUserId(?int $userId): ?int
    {
        if (!$userId) {
            return null;
        }

        return User::query()->whereKey($userId)->exists() ? $userId : null;
    }

    public function eventsForTrace(PublishArticle $article, string $clientTrace, int $afterSequence = 0, int $limit = 200): array
    {
        if (trim($clientTrace) === '') {
            return [];
        }

        $query = PublishPipelineRunEvent::query()
            ->where('publish_article_id', $article->id)
            ->where('run_trace', $clientTrace)
            ->when($afterSequence > 0, fn ($builder) => $builder->where('client_sequence', '>', $afterSequence));

        $limit = max(1, min($limit, 500));

        if ($afterSequence > 0) {
            return $query
                ->orderBy('client_sequence')
                ->orderBy('id')
                ->limit($limit)
                ->get()
                ->map(fn (PublishPipelineRunEvent $event) => $this->formatEvent($event))
                ->all();
        }

        return $query
            ->orderByDesc('client_sequence')
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->reverse()
            ->map(fn (PublishPipelineRunEvent $event) => $this->formatEvent($event))
            ->all();
    }

    private function resolveCapturedAt($entry): ?Carbon
    {
        $capturedAt = $entry['captured_at'] ?? null;
        if (!$capturedAt) {
            return null;
        }

        try {
            return Carbon::parse($capturedAt);
        } catch (\Throwable) {
            return null;
        }
    }

    private function buildMeta(array $entry): ?array
    {
        $meta = $entry['meta'] ?? null;
        if (is_array($meta) && !empty($meta)) {
            return $meta;
        }

        return null;
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

    private function formatEvent(PublishPipelineRunEvent $event): array
    {
        $payload = [
            'id' => $event->client_sequence ?: $event->id,
            'client_event_id' => $event->client_event_id,
            'run_trace' => $event->run_trace,
            'captured_at' => optional($event->captured_at)->toIso8601String(),
            'time' => $event->captured_at ? $event->captured_at->format('H:i:s') : '',
            'scope' => $event->scope ?: 'pipeline',
            'type' => $event->type ?: 'info',
            'message' => $event->message ?: '',
            'stage' => $event->stage ?: '',
            'substage' => $event->substage ?: '',
            'trace_id' => $event->trace_id ?: '',
            'duration_ms' => $event->duration_ms,
            'sequence_no' => $event->sequence_no,
            'method' => $event->method ?: '',
            'status' => $event->status_code,
            'url' => $event->url ?: '',
            'details' => $event->details ?: '',
            'payload_preview' => $event->payload_preview ?: '',
            'response_preview' => $event->response_preview ?: '',
            'debug_only' => (bool) $event->debug_only,
            'draft_id' => $event->publish_article_id,
            'step' => $event->step,
            'server_persisted' => true,
            'meta' => $event->meta ?: null,
        ];

        if (is_array($event->meta)) {
            $payload = array_merge($payload, $event->meta);
        }

        return $payload;
    }

    private function formatRun(PublishPipelineRun $run): array
    {
        return [
            'id' => $run->id,
            'draft_id' => $run->publish_article_id,
            'draft_title' => $run->article?->title,
            'client_trace' => $run->client_trace,
            'workflow_type' => $run->workflow_type,
            'debug_enabled' => (bool) $run->debug_enabled,
            'started_at' => optional($run->started_at)->toIso8601String(),
            'last_event_at' => optional($run->last_event_at)->toIso8601String(),
            'last_scope' => $run->last_scope,
            'last_type' => $run->last_type,
            'last_stage' => $run->last_stage,
            'last_substage' => $run->last_substage,
            'total_events' => (int) $run->total_events,
        ];
    }
}
