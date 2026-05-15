<?php

namespace hexa_app_publish\Publishing\Pipeline\Http\Controllers;

use hexa_app_publish\Publishing\Articles\Models\PublishArticle;
use hexa_app_publish\Publishing\Pipeline\Http\Requests\SavePipelineStateRequest;
use hexa_app_publish\Publishing\Pipeline\Services\PipelineDraftSessionService;
use hexa_app_publish\Publishing\Pipeline\Services\PipelineStateService;
use hexa_app_publish\Publishing\Sites\Models\PublishSite;
use hexa_app_publish\Publishing\Sites\Services\SiteAuthorResolutionService;
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

        $resolvedArticleType = trim((string) (
            data_get($validated, "payload.template_overrides.article_type")
            ?? data_get($validated, "payload.article_type")
            ?? data_get($validated, "payload.currentArticleType")
            ?? data_get($validated, "payload.selectedTemplate.article_type")
            ?? data_get($validated, "payload.pressRelease.article_type")
            ?? $draft->article_type
            ?? ""
        ));

        $previousArticleType = (string) ($draft->article_type ?: '');
        $resolvedSiteId = (int) (
            data_get($validated, "payload.selectedSiteId")
            ?? data_get($validated, "payload.selectedSite.id")
            ?? 0
        );
        $previousSiteId = (int) ($draft->publish_site_id ?: 0);
        $articleTypeChanged = $resolvedArticleType !== '' && $resolvedArticleType !== $previousArticleType;
        $siteChanged = $resolvedSiteId !== $previousSiteId;
        $site = $resolvedSiteId > 0
            ? PublishSite::query()->select(['id', 'publish_account_id', 'is_press_release_source'])->find($resolvedSiteId)
            : null;

        if ($resolvedArticleType === 'press-release' && !($site?->is_press_release_source)) {
            $fallbackSite = PublishSite::query()
                ->select(['id', 'publish_account_id', 'is_press_release_source', 'name', 'url', 'status', 'default_author', 'wp_username', 'connection_type'])
                ->where('status', 'connected')
                ->where('is_press_release_source', true)
                ->orderBy('id')
                ->first();

            if ($fallbackSite) {
                $resolvedSiteId = (int) $fallbackSite->id;
                $site = $fallbackSite;
                $validated['payload']['selectedSiteId'] = (string) $fallbackSite->id;
                $validated['payload']['selectedSite'] = [
                    'id' => $fallbackSite->id,
                    'name' => $fallbackSite->name,
                    'url' => $fallbackSite->url,
                    'status' => $fallbackSite->status,
                    'default_author' => $fallbackSite->default_author,
                    'is_press_release_source' => (bool) $fallbackSite->is_press_release_source,
                    'wp_username' => $fallbackSite->wp_username,
                    'connection_type' => $fallbackSite->connection_type,
                ];
            }
        }

        if ($resolvedArticleType !== 'press-release' || !($site?->is_press_release_source)) {
            $validated['payload']['selectedSyndicationCats'] = [];
        }

        if ($siteChanged || $articleTypeChanged) {
            $validated['payload'] = $this->stateService->clearPublishContextState(
                $validated['payload'],
                true,
                $resolvedSiteId > 0 ? 'draft_wp' : 'draft_local'
            );
        }
        $payloadAuthor = trim((string) (
            data_get($validated, "payload.publishAuthor")
            ?? data_get($validated, "payload.author")
            ?? ""
        ));
        $payloadAuthorSiteId = (int) (data_get($validated, "payload.publishAuthorSiteId") ?: 0);
        if ($resolvedSiteId > 0) {
            $preferredAuthor = $payloadAuthor;
            if ($siteChanged || ($payloadAuthorSiteId > 0 && $payloadAuthorSiteId !== $resolvedSiteId)) {
                $preferredAuthor = trim((string) ($site?->default_author ?? ""));
            }

            $resolvedAuthor = app(SiteAuthorResolutionService::class)->resolvePreferredAuthor($site, $preferredAuthor);
            $payloadAuthor = trim((string) ($resolvedAuthor ?: $preferredAuthor));
            if ($payloadAuthor !== "") {
                $validated["payload"]["publishAuthor"] = $payloadAuthor;
                $validated["payload"]["publishAuthorSiteId"] = $resolvedSiteId;
            } else {
                unset($validated["payload"]["publishAuthor"], $validated["payload"]["publishAuthorSiteId"]);
            }
        } else {
            unset($validated["payload"]["publishAuthorSiteId"]);
        }

        $state = $this->stateService->save(
            $draft,
            $validated["payload"],
            $validated["workflow_type"] ?? null
        );

        if ($resolvedArticleType !== "" && $articleTypeChanged) {
            $draft->article_type = $resolvedArticleType;
        }

        if ($resolvedSiteId > 0 && $previousSiteId !== $resolvedSiteId) {
            $draft->publish_site_id = $site?->id ?: null;
            $draft->publish_account_id = $site?->publish_account_id ?: null;
        } elseif ($resolvedSiteId === 0 && $previousSiteId !== 0) {
            $draft->publish_site_id = null;
            $draft->publish_account_id = null;
        }

        $payloadAuthor = trim((string) (
            data_get($validated, "payload.publishAuthor")
            ?? data_get($validated, "payload.author")
            ?? ''
        ));
        if ($payloadAuthor !== '' && $draft->author !== $payloadAuthor) {
            $draft->author = $payloadAuthor;
        }

        if (($siteChanged || $articleTypeChanged) && ($draft->wp_post_id || $draft->wp_status || $draft->wp_post_url)) {
            $draft->wp_post_id = null;
            $draft->wp_status = null;
            $draft->wp_post_url = null;
        }

        if ($draft->isDirty()) {
            $draft->save();
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
