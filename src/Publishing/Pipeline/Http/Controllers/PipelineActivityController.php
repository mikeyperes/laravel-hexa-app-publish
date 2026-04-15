<?php

namespace hexa_app_publish\Publishing\Pipeline\Http\Controllers;

use hexa_app_publish\Publishing\Articles\Models\PublishArticle;
use hexa_app_publish\Publishing\Pipeline\Http\Requests\SyncPipelineActivityRequest;
use hexa_app_publish\Publishing\Pipeline\Services\PipelineActivityService;
use hexa_core\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PipelineActivityController extends Controller
{
    public function __construct(
        private PipelineActivityService $activityService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'draft_id' => 'required|integer|exists:publish_articles,id',
            'limit' => 'nullable|integer|min:1|max:500',
            'trace' => 'nullable|string|max:255',
            'runs_limit' => 'nullable|integer|min:1|max:25',
            'include_cross_draft_runs' => 'nullable|boolean',
            'exclude_draft_id' => 'nullable|integer|min:1',
            'all_drafts_limit' => 'nullable|integer|min:1|max:25',
        ]);

        $draft = $this->resolveDraft((int) $validated['draft_id']);
        $result = $this->activityService->latestRunEvents(
            $draft,
            (int) ($validated['limit'] ?? 300),
            $validated['trace'] ?? null
        );
        $run = $result['run'];
        $recentRuns = $this->activityService->recentRuns($draft, (int) ($validated['runs_limit'] ?? 10));
        $recentDraftRuns = [];

        if ((bool) ($validated['include_cross_draft_runs'] ?? false) && auth()->user()) {
            $recentDraftRuns = $this->activityService->recentRunsForUser(
                auth()->user(),
                (int) ($validated['all_drafts_limit'] ?? 12),
                (int) ($validated['exclude_draft_id'] ?? $draft->id)
            );
        }

        return response()->json([
            'success' => true,
            'run' => $run ? [
                'id' => $run->id,
                'client_trace' => $run->client_trace,
                'workflow_type' => $run->workflow_type,
                'debug_enabled' => $run->debug_enabled,
                'started_at' => optional($run->started_at)->toIso8601String(),
                'last_event_at' => optional($run->last_event_at)->toIso8601String(),
                'last_scope' => $run->last_scope,
                'last_type' => $run->last_type,
                'last_stage' => $run->last_stage,
                'last_substage' => $run->last_substage,
                'total_events' => $run->total_events,
            ] : null,
            'events' => $result['events'],
            'selected_trace' => $run?->client_trace,
            'recent_runs' => $recentRuns,
            'recent_draft_runs' => $recentDraftRuns,
        ]);
    }

    public function sync(SyncPipelineActivityRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $draft = $this->resolveDraft((int) $validated['draft_id']);
        $result = $this->activityService->sync(
            $draft,
            (string) $validated['client_trace'],
            (array) $validated['entries'],
            $validated['workflow_type'] ?? null,
            (bool) ($validated['debug_enabled'] ?? false),
            auth()->id()
        );

        return response()->json([
            'success' => true,
            'message' => 'Pipeline activity synced.',
            'run_id' => $result['run']?->id,
            'total_events' => $result['total_events'],
            'synced_event_ids' => $result['synced_event_ids'],
        ]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'draft_id' => 'required|integer|exists:publish_articles,id',
        ]);

        $draft = $this->resolveDraft((int) $validated['draft_id']);
        $deleted = $this->activityService->clear($draft);

        return response()->json([
            'success' => true,
            'message' => 'Pipeline activity log cleared.',
            'deleted_runs' => $deleted,
        ]);
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
