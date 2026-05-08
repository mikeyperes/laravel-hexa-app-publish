<?php

namespace hexa_app_publish\Publishing\Pipeline\Http\Controllers;

use hexa_app_publish\Publishing\Articles\Models\PublishArticle;
use hexa_app_publish\Publishing\Pipeline\Http\Requests\SavePipelineStateRequest;
use hexa_app_publish\Publishing\Pipeline\Services\PipelineDraftSessionService;
use hexa_app_publish\Publishing\Pipeline\Services\PipelineStateService;
use hexa_core\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class PipelineStateController extends Controller
{
    public function __construct(
        private PipelineStateService $stateService,
        private PipelineDraftSessionService $draftSession
    ) {}

    public function save(SavePipelineStateRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $draft = $this->resolveDraft((int) $validated['draft_id']);
        $debugMode = $request->boolean('debug_mode') || $request->headers->get('X-Pipeline-Debug') === '1';
        $clientTrace = (string) ($request->headers->get('X-Pipeline-Client-Trace') ?: '');
        $tabId = trim((string) $request->headers->get('X-Pipeline-Tab-Id', ''));
        $startedAt = microtime(true);

        if ($conflict = $this->draftSession->conflictFor($draft, $tabId, auth()->id())) {
            return response()->json([
                'success' => false,
                'code' => 'draft_session_conflict',
                'message' => 'Another tab is actively editing this draft. Pipeline state saves are paused in this tab to avoid overwriting draft #' . $draft->id . '.',
                'conflict' => array_merge($conflict, [
                    'scope' => 'pipeline state',
                    'draft_id' => $draft->id,
                ]),
            ], 409);
        }

        if ($debugMode) {
            hexaLogDebug('publish.pipeline-state', 'PipelineState save requested', [
                'client_trace' => $clientTrace,
                'draft_id' => $draft->id,
                'tab_id' => $tabId,
                'workflow_type' => $validated['workflow_type'] ?? null,
                'state_version' => (int) ($validated['payload']['_v'] ?? 0),
                'payload_keys' => array_keys($validated['payload']),
            ]);
        }

        $state = $this->stateService->save(
            $draft,
            $validated["payload"],
            $validated["workflow_type"] ?? null
        );

        $resolvedArticleType = trim((string) (
            data_get($validated, "payload.template_overrides.article_type")
            ?? data_get($validated, "payload.article_type")
            ?? data_get($validated, "payload.currentArticleType")
            ?? data_get($validated, "payload.selectedTemplate.article_type")
            ?? data_get($validated, "payload.pressRelease.article_type")
            ?? $state->workflow_type
            ?? ""
        ));

        if ($resolvedArticleType !== "" && $draft->article_type !== $resolvedArticleType) {
            $draft->forceFill(["article_type" => $resolvedArticleType])->save();
        }

        $this->draftSession->claim($draft, $tabId, auth()->id(), [
            'source' => 'pipeline_state',
            'client_trace' => $clientTrace,
        ]);

        $response = [
            'success' => true,
            'message' => 'Pipeline state saved.',
            'state_id' => $state->id,
            'workflow_type' => $state->workflow_type,
        ];

        if ($debugMode) {
            $response['debug'] = [
                'client_trace' => $clientTrace,
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                'state_version' => $state->state_version,
            ];
        }

        return response()->json($response);
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
