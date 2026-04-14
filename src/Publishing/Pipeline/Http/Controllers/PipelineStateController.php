<?php

namespace hexa_app_publish\Publishing\Pipeline\Http\Controllers;

use hexa_app_publish\Publishing\Articles\Models\PublishArticle;
use hexa_app_publish\Publishing\Pipeline\Http\Requests\SavePipelineStateRequest;
use hexa_app_publish\Publishing\Pipeline\Services\PipelineStateService;
use hexa_core\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class PipelineStateController extends Controller
{
    public function __construct(
        private PipelineStateService $stateService
    ) {}

    public function save(SavePipelineStateRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $draft = $this->resolveDraft((int) $validated['draft_id']);

        $state = $this->stateService->save(
            $draft,
            $validated['payload'],
            $validated['workflow_type'] ?? null
        );

        return response()->json([
            'success' => true,
            'message' => 'Pipeline state saved.',
            'state_id' => $state->id,
            'workflow_type' => $state->workflow_type,
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
