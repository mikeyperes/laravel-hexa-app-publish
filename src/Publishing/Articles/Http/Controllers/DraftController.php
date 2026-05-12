<?php

namespace hexa_app_publish\Publishing\Articles\Http\Controllers;

use hexa_core\Http\Controllers\Controller;
use hexa_app_publish\Publishing\Access\Services\PublishAccessService;
use hexa_app_publish\Publishing\Articles\Models\PublishArticle;
use hexa_app_publish\Publishing\Pipeline\Jobs\PreparePipelineOperationJob;
use hexa_app_publish\Publishing\Pipeline\Jobs\PublishPipelineOperationJob;
use hexa_app_publish\Publishing\Pipeline\Models\PublishPipelineOperation;
use hexa_app_publish\Publishing\Pipeline\Services\PipelineOperationExecutor;
use hexa_app_publish\Publishing\Pipeline\Services\PipelineOperationService;
use hexa_app_publish\Publishing\Pipeline\Services\PipelineStateService;
use hexa_app_publish\Services\ArticleDeleteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * PublishDraftController — CRUD for articles (all statuses).
 */
class DraftController extends Controller
{
    public function __construct(
        private PublishAccessService $access,
        private PipelineOperationService $operationService,
        private PipelineStateService $stateService
    ) {}

    public function index(Request $request): View|JsonResponse
    {
        $query = $this->access->articleQuery($request->user())->with(['creator', 'site', 'pipelineState']);

        if ($request->filled('user_id')) {
            $userId = (int) $request->input('user_id');
            $query->where(function ($qb) use ($userId) {
                $qb->where('created_by', $userId)
                    ->orWhere('user_id', $userId);
            });
        }

        if ($request->filled('q')) {
            $q = $request->input('q');
            $query->where(function ($qb) use ($q) {
                $qb->where('title', 'like', "%{$q}%")
                    ->orWhere('article_id', 'like', "%{$q}%")
                    ->orWhereHas('site', fn ($s) => $s->where('name', 'like', "%{$q}%"));
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $articles = $query->orderByDesc('updated_at')->paginate(100);

        $draftCollection = $articles->getCollection();
        $operationSnapshots = $this->latestOperationSnapshots($draftCollection);

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'data' => $articles]);
        }

        return view('app-publish::publishing.articles.drafts.index', [
            'drafts' => $articles,
            'draftOperationSnapshots' => $operationSnapshots,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title'      => 'required|string|max:500',
            'body'       => 'nullable|string',
            'excerpt'    => 'nullable|string|max:1000',
            'created_by' => 'nullable|integer|exists:users,id',
            'notes'      => 'nullable|string',
        ]);

        $validated['status'] = 'drafting';
        $validated['article_id'] = PublishArticle::generateArticleId();
        $validated['created_by'] = auth()->id();
        $validated['user_id'] = auth()->id();

        $draft = PublishArticle::create($validated);

        hexaLog('publish', 'draft_created', "Draft created: {$draft->title}");

        return response()->json([
            'success'  => true,
            'message'  => 'Draft created successfully.',
            'article'  => $draft,
            'draft'    => $draft,
            'redirect' => route('publish.drafts.show', $draft->id),
        ]);
    }

    public function show(Request $request, int $id): View|JsonResponse
    {
        $draft = $this->access->resolveArticleOrFail($request->user(), $id);

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'article' => $draft]);
        }

        return view('app-publish::publishing.articles.drafts.index', [
            'drafts' => $this->access->articleQuery($request->user())->orderByDesc('updated_at')->paginate(100),
            'editDraft' => $draft,
        ]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $draft = $this->access->resolveArticleOrFail($request->user(), $id);

        $validated = $request->validate([
            'title'      => 'required|string|max:500',
            'body'       => 'nullable|string',
            'excerpt'    => 'nullable|string|max:1000',
            'created_by' => 'nullable|integer|exists:users,id',
            'notes'      => 'nullable|string',
            'status'     => 'nullable|string',
        ]);

        unset($validated['created_by']);
        $draft->update($validated);

        hexaLog('publish', 'draft_updated', "Article updated: {$draft->title}");

        return response()->json([
            'success' => true,
            'message' => "Article '{$draft->title}' updated.",
            'article' => $draft,
        ]);
    }

    public function prepare(Request $request, int $id): JsonResponse
    {
        $draft = $this->access->resolveArticleOrFail($request->user(), $id);
        $context = $this->buildDraftContext($draft);

        if (!$context['site_id']) {
            return response()->json([
                'success' => false,
                'message' => 'No WordPress site is assigned to this article yet.',
                'code' => 'site_required',
            ], 422);
        }

        if (!$context['html']) {
            return response()->json([
                'success' => false,
                'message' => 'This article has no saved body content to prepare.',
                'code' => 'html_required',
            ], 422);
        }

        $active = $this->operationService->activeForArticle($draft, PublishPipelineOperation::TYPE_PREPARE);
        if ($active) {
            return response()->json([
                'success' => true,
                'message' => 'A prepare operation is already in progress for this article.',
                'operation' => $this->serializePipelineOperation($active),
                'requires_prepare' => true,
            ], 202);
        }

        $prepareOperation = $this->startPrepareOperation($request, $draft, $context);

        return response()->json([
            'success' => true,
            'message' => 'Prepare started.',
            'operation' => $this->serializePipelineOperation($prepareOperation->fresh()),
            'requires_prepare' => true,
        ], $prepareOperation->transport === 'sync' ? 200 : 202);
    }

    public function approve(Request $request, int $id): JsonResponse
    {
        $draft = $this->access->resolveArticleOrFail($request->user(), $id);
        $context = $this->buildDraftContext($draft);
        $targetStatus = $this->approvalTargetStatus($draft);

        if (!$context['site_id']) {
            return response()->json([
                'success' => false,
                'message' => 'No WordPress site is assigned to this article yet.',
                'code' => 'site_required',
            ], 422);
        }

        if (!$context['html']) {
            return response()->json([
                'success' => false,
                'message' => 'This article has no saved body content to publish.',
                'code' => 'html_required',
            ], 422);
        }

        $activePublish = $this->operationService->activeForArticle($draft, PublishPipelineOperation::TYPE_PUBLISH);
        if ($activePublish) {
            return response()->json([
                'success' => true,
                'message' => 'A publish operation is already in progress for this article.',
                'operation' => $this->serializePipelineOperation($activePublish),
                'target_status' => $targetStatus,
            ], 202);
        }

        $activePrepare = $this->operationService->activeForArticle($draft, PublishPipelineOperation::TYPE_PREPARE);
        if ($activePrepare) {
            return response()->json([
                'success' => true,
                'message' => 'Prepare is still running. This article will be ready to approve as soon as it completes.',
                'operation' => $this->serializePipelineOperation($activePrepare),
                'requires_prepare' => true,
                'target_status' => $targetStatus,
            ], 202);
        }

        $prepareOperation = $this->latestCompletedPrepareFor($draft);
        if ($this->needsFreshPrepare($draft, $prepareOperation)) {
            $prepareOperation = $this->startPrepareOperation($request, $draft, $context);

            return response()->json([
                'success' => true,
                'message' => 'Prepare started before approval so the latest draft state is used.',
                'operation' => $this->serializePipelineOperation($prepareOperation->fresh()),
                'requires_prepare' => true,
                'auto_approve' => true,
                'target_status' => $targetStatus,
            ], $prepareOperation->transport === 'sync' ? 200 : 202);
        }

        $publishOperation = $this->startPublishOperation($request, $draft, $context, $prepareOperation, $targetStatus);

        return response()->json([
            'success' => true,
            'message' => $targetStatus === 'publish' ? 'Live publish started.' : 'WordPress draft creation started.',
            'operation' => $this->serializePipelineOperation($publishOperation->fresh()),
            'target_status' => $targetStatus,
        ], $publishOperation->transport === 'sync' ? 200 : 202);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $article = $this->access->resolveArticleOrFail($request->user(), $id);
        $deleteService = app(ArticleDeleteService::class);
        $result = $deleteService->delete($article);

        return response()->json([
            'success' => $result['success'],
            'message' => 'Article moved to deleted archive.',
            'log'     => $result['log'],
        ]);
    }

    public function bulkDestroy(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:publish_articles,id',
        ]);

        $articles = $this->access->articleQuery($request->user())
            ->whereIn('id', $validated['ids'])
            ->get()
            ->keyBy('id');

        abort_unless($articles->count() === count($validated['ids']), 403);

        $deleteService = app(ArticleDeleteService::class);
        $allLogs = [];

        foreach ($validated['ids'] as $id) {
            $article = $articles->get($id);
            if ($article) {
                $result = $deleteService->delete($article);
                $allLogs[$id] = $result['log'];
            }
        }

        return response()->json([
            'success' => true,
            'message' => count($validated['ids']) . ' article(s) moved to deleted archive.',
            'logs'    => $allLogs,
        ]);
    }

    private function latestOperationSnapshots(Collection $drafts): array
    {
        $ids = $drafts->pluck('id')->filter()->map(fn ($id) => (int) $id)->values();
        if ($ids->isEmpty()) {
            return [];
        }

        $operations = PublishPipelineOperation::query()
            ->whereIn('publish_article_id', $ids->all())
            ->whereIn('operation_type', [
                PublishPipelineOperation::TYPE_PREPARE,
                PublishPipelineOperation::TYPE_PUBLISH,
            ])
            ->orderByDesc('id')
            ->get();

        $snapshots = [];
        foreach ($operations as $operation) {
            $articleId = (int) $operation->publish_article_id;
            $type = (string) $operation->operation_type;
            if (!isset($snapshots[$articleId][$type])) {
                $snapshots[$articleId][$type] = $this->serializePipelineOperation($operation);
            }
        }

        return $snapshots;
    }

    private function latestCompletedPrepareFor(PublishArticle $draft): ?PublishPipelineOperation
    {
        return PublishPipelineOperation::query()
            ->where('publish_article_id', $draft->id)
            ->where('operation_type', PublishPipelineOperation::TYPE_PREPARE)
            ->where('status', PublishPipelineOperation::STATUS_COMPLETED)
            ->latest('id')
            ->first();
    }

    private function needsFreshPrepare(PublishArticle $draft, ?PublishPipelineOperation $prepareOperation): bool
    {
        if (!$prepareOperation) {
            return true;
        }

        if (!$prepareOperation->completed_at) {
            return true;
        }

        $referenceUpdate = $draft->updated_at ?: $draft->created_at;
        if ($referenceUpdate && $referenceUpdate->gt($prepareOperation->completed_at)) {
            return true;
        }

        return false;
    }

    private function buildDraftContext(PublishArticle $draft): array
    {
        $state = $this->stateService->payload($draft);
        $html = trim((string) (
            $state['editorContent']
            ?? $state['spunContent']
            ?? $draft->body
            ?? ''
        ));

        $title = trim((string) ($state['articleTitle'] ?? $draft->title ?? 'Untitled'));
        $excerpt = trim((string) ($state['articleDescription'] ?? $draft->excerpt ?? ''));
        $categories = $this->stringList($draft->categories ?? []);
        $tags = $this->stringList($draft->tags ?? []);
        $photoSuggestions = $this->normalizePhotoSuggestions($state['photoSuggestions'] ?? $draft->photo_suggestions ?? []);
        $featuredPhoto = is_array($state['featuredPhoto'] ?? null) ? $state['featuredPhoto'] : null;
        $featuredUrl = $featuredPhoto
            ? $this->firstNonEmpty([
                $featuredPhoto['url_large'] ?? null,
                $featuredPhoto['url_full'] ?? null,
                $featuredPhoto['url'] ?? null,
                $featuredPhoto['url_thumb'] ?? null,
            ])
            : null;
        $featuredMeta = $featuredPhoto ? [
            'alt_text' => trim((string) ($state['featuredAlt'] ?? $featuredPhoto['alt'] ?? '')),
            'caption' => trim((string) ($state['featuredCaption'] ?? '')),
            'filename' => trim((string) ($state['featuredFilename'] ?? '')),
        ] : null;

        return [
            'state' => $state,
            'html' => $html,
            'title' => $title !== '' ? $title : 'Untitled',
            'excerpt' => $excerpt !== '' ? $excerpt : null,
            'site_id' => (int) ($draft->publish_site_id ?: data_get($state, 'selectedSite.id') ?: 0),
            'categories' => $categories,
            'tags' => $tags,
            'photo_suggestions' => $photoSuggestions,
            'photo_meta' => collect($photoSuggestions)
                ->filter(fn ($item) => empty($item['removed']) && !empty($item['autoPhoto']))
                ->values()
                ->map(fn ($item) => [
                    'alt_text' => trim((string) ($item['alt_text'] ?? '')),
                    'caption' => trim((string) ($item['caption'] ?? '')),
                    'filename' => trim((string) preg_replace(
                        '/[^a-z0-9]+/i',
                        '-',
                        strtolower((string) ($item['suggestedFilename'] ?? $item['search_term'] ?? 'photo'))
                    ), '-'),
                ])
                ->all(),
            'featured_url' => $featuredUrl,
            'featured_meta' => $featuredMeta,
            'existing_uploads' => is_array($draft->wp_images) ? $draft->wp_images : [],
            'publication_term_ids' => array_values(array_filter(array_map('intval', (array) ($state['selectedSyndicationCats'] ?? [])))),
            'author' => trim((string) ($draft->author ?: data_get($state, 'publishAuthor') ?: '')),
            'word_count' => (int) ($draft->word_count ?: str_word_count(strip_tags($html))),
            'existing_post_id' => (int) ($draft->wp_post_id ?: data_get($state, 'existingWpPostId') ?: 0),
            'article_type' => trim((string) ($draft->article_type ?: data_get($state, 'currentArticleType') ?: '')),
        ];
    }

    private function normalizePhotoSuggestions(mixed $input): array
    {
        if (!is_array($input)) {
            return [];
        }

        return array_values(array_filter(array_map(function ($item) {
            if (!is_array($item)) {
                return null;
            }

            return [
                'removed' => !empty($item['removed']),
                'alt_text' => trim((string) ($item['alt_text'] ?? '')),
                'caption' => trim((string) ($item['caption'] ?? '')),
                'search_term' => trim((string) ($item['search_term'] ?? '')),
                'suggestedFilename' => trim((string) ($item['suggestedFilename'] ?? '')),
                'autoPhoto' => is_array($item['autoPhoto'] ?? null) ? $item['autoPhoto'] : null,
            ];
        }, $input)));
    }

    private function startPrepareOperation(Request $request, PublishArticle $draft, array $context): PublishPipelineOperation
    {
        $site = $this->access->resolveSiteOrFail($request->user(), (int) $context['site_id']);
        $strategy = $this->operationService->detectExecutionStrategy();
        $clientTrace = 'draft-list-prepare-' . $draft->id . '-' . Str::lower(Str::random(8));
        $traceId = (string) Str::uuid();
        $payload = [
            'html' => $context['html'],
            'title' => $context['title'],
            'site_id' => $site->id,
            'categories' => $context['categories'],
            'tags' => $context['tags'],
            'draft_id' => $draft->id,
            'photo_suggestions' => $context['photo_suggestions'],
            'photo_meta' => $context['photo_meta'],
            'featured_meta' => $context['featured_meta'],
            'featured_url' => $context['featured_url'],
            'existing_uploads' => $context['existing_uploads'],
            'existing_featured_media_id' => $this->latestPreparedFeaturedMediaId($draft),
            'client_trace' => $clientTrace,
            'trace_id' => $traceId,
            'debug_mode' => $request->boolean('debug_mode'),
            'user_id' => auth()->id(),
            'user_ip' => $request->ip(),
        ];

        $operation = $this->operationService->create($draft, PublishPipelineOperation::TYPE_PREPARE, [
            'trace_id' => $traceId,
            'client_trace' => $clientTrace,
            'user_id' => auth()->id(),
            'site_id' => $site->id,
            'site_name' => $site->name,
            'draft_id' => $draft->id,
            'image_count' => substr_count($context['html'], '<img'),
            'category_count' => count($context['categories']),
            'tag_count' => count($context['tags']),
            'has_featured' => !empty($context['featured_url']),
            'transport' => $strategy['transport'],
        ], [
            'publish_site_id' => $site->id,
            'created_by' => auth()->id(),
            'workflow_type' => $context['article_type'] ?: ($draft->article_type ?: null),
            'transport' => $strategy['transport'],
            'queue_connection' => $strategy['queue_connection'],
            'queue_name' => $strategy['queue_name'],
            'client_trace' => $clientTrace,
            'trace_id' => $traceId,
            'debug_enabled' => $request->boolean('debug_mode'),
        ]);

        $this->dispatchPipelineOperation($strategy, $operation, $payload, PublishPipelineOperation::TYPE_PREPARE);

        return $operation;
    }

    private function startPublishOperation(
        Request $request,
        PublishArticle $draft,
        array $context,
        ?PublishPipelineOperation $prepareOperation,
        string $targetStatus = 'publish'
    ): PublishPipelineOperation {
        $site = $this->access->resolveSiteOrFail($request->user(), (int) $context['site_id']);
        $strategy = $this->operationService->detectExecutionStrategy();
        $clientTrace = 'draft-list-publish-' . $draft->id . '-' . Str::lower(Str::random(8));
        $traceId = (string) Str::uuid();
        $preparedResult = is_array($prepareOperation?->result_payload) ? $prepareOperation->result_payload : [];

        $payload = [
            'html' => $context['html'],
            'title' => $context['title'],
            'excerpt' => $context['excerpt'],
            'site_id' => $site->id,
            'category_ids' => array_values(array_filter(array_map('intval', (array) ($preparedResult['category_ids'] ?? [])))),
            'tag_ids' => array_values(array_filter(array_map('intval', (array) ($preparedResult['tag_ids'] ?? [])))),
            'publication_term_ids' => $context['publication_term_ids'],
            'featured_media_id' => isset($preparedResult['featured_media_id']) ? (int) $preparedResult['featured_media_id'] : null,
            'status' => $targetStatus,
            'date' => null,
            'draft_id' => $draft->id,
            'existing_post_id' => $context['existing_post_id'] ?: null,
            'categories' => $context['categories'],
            'tags' => $context['tags'],
            'wp_images' => is_array($preparedResult['wp_images'] ?? null) ? $preparedResult['wp_images'] : (is_array($draft->wp_images) ? $draft->wp_images : []),
            'word_count' => $context['word_count'],
            'ai_model' => $draft->ai_engine_used,
            'ai_cost' => $draft->ai_cost,
            'ai_provider' => $draft->ai_provider,
            'ai_tokens_input' => $draft->ai_tokens_input,
            'ai_tokens_output' => $draft->ai_tokens_output,
            'resolved_prompt' => $draft->resolved_prompt,
            'photo_suggestions' => $context['photo_suggestions'],
            'featured_image_search' => data_get($context['state'], 'featuredImageSearch') ?: $draft->featured_image_search,
            'author' => $context['author'] ?: null,
            'sources' => is_array($draft->source_articles) ? $draft->source_articles : [],
            'template_id' => $draft->publish_template_id,
            'preset_id' => $draft->preset_id,
            'article_type' => $context['article_type'] ?: null,
            'client_trace' => $clientTrace,
            'trace_id' => $traceId,
            'debug_mode' => $request->boolean('debug_mode'),
            'user_id' => auth()->id(),
            'user_ip' => $request->ip(),
        ];

        $operation = $this->operationService->create($draft, PublishPipelineOperation::TYPE_PUBLISH, [
            'trace_id' => $traceId,
            'client_trace' => $clientTrace,
            'user_id' => auth()->id(),
            'draft_id' => $draft->id,
            'site_id' => $site->id,
            'site_name' => $site->name,
            'status' => $targetStatus,
            'category_count' => count($payload['category_ids']),
            'tag_count' => count($payload['tag_ids']),
            'wp_image_count' => count($payload['wp_images']),
            'has_featured' => !empty($payload['featured_media_id']),
            'transport' => $strategy['transport'],
        ], [
            'publish_site_id' => $site->id,
            'created_by' => auth()->id(),
            'workflow_type' => $context['article_type'] ?: ($draft->article_type ?: null),
            'transport' => $strategy['transport'],
            'queue_connection' => $strategy['queue_connection'],
            'queue_name' => $strategy['queue_name'],
            'client_trace' => $clientTrace,
            'trace_id' => $traceId,
            'debug_enabled' => $request->boolean('debug_mode'),
        ]);

        $this->dispatchPipelineOperation($strategy, $operation, $payload, PublishPipelineOperation::TYPE_PUBLISH);

        return $operation;
    }

    private function latestPreparedFeaturedMediaId(PublishArticle $draft): ?int
    {
        $prepareOperation = $this->latestCompletedPrepareFor($draft);
        if (!$prepareOperation || !is_array($prepareOperation->result_payload)) {
            return null;
        }

        $featuredId = $prepareOperation->result_payload['featured_media_id'] ?? null;

        return $featuredId ? (int) $featuredId : null;
    }

    private function stringList(mixed $values): array
    {
        if (!is_array($values)) {
            return [];
        }

        return array_values(array_filter(array_map(
            fn ($value) => trim((string) $value),
            $values
        )));
    }

    private function firstNonEmpty(array $values): ?string
    {
        foreach ($values as $value) {
            $text = trim((string) ($value ?? ''));
            if ($text !== '') {
                return $text;
            }
        }

        return null;
    }

    private function approvalTargetStatus(PublishArticle $draft): string
    {
        $wpStatus = Str::lower(trim((string) ($draft->wp_status ?? '')));

        if ($draft->wp_post_id && $wpStatus !== 'publish') {
            return 'publish';
        }

        return 'draft';
    }

    private function dispatchPipelineOperation(array $strategy, PublishPipelineOperation $operation, array $payload, string $type): void
    {
        if ($strategy['transport'] === 'sync') {
            $executor = app(PipelineOperationExecutor::class);
            if ($type === PublishPipelineOperation::TYPE_PREPARE) {
                $executor->runPrepare($operation->id, $payload);
            } else {
                $executor->runPublish($operation->id, $payload);
            }

            return;
        }

        if ($strategy['transport'] === 'queue') {
            if ($type === PublishPipelineOperation::TYPE_PREPARE) {
                PreparePipelineOperationJob::dispatch($operation->id, $payload)
                    ->onConnection($strategy['queue_connection'])
                    ->onQueue($strategy['queue_name']);
            } else {
                PublishPipelineOperationJob::dispatch($operation->id, $payload)
                    ->onConnection($strategy['queue_connection'])
                    ->onQueue($strategy['queue_name']);
            }

            return;
        }

        if ($strategy['transport'] === 'queue_once') {
            if ($type === PublishPipelineOperation::TYPE_PREPARE) {
                PreparePipelineOperationJob::dispatch($operation->id, $payload)
                    ->onConnection($strategy['queue_connection'])
                    ->onQueue($strategy['queue_name']);
            } else {
                PublishPipelineOperationJob::dispatch($operation->id, $payload)
                    ->onConnection($strategy['queue_connection'])
                    ->onQueue($strategy['queue_name']);
            }

            $this->operationService->spawnTransientQueueWorker(
                (string) $strategy['queue_connection'],
                (string) $strategy['queue_name']
            );

            return;
        }

        app()->terminating(function () use ($operation, $payload, $type) {
            $executor = app(PipelineOperationExecutor::class);
            if ($type === PublishPipelineOperation::TYPE_PREPARE) {
                $executor->runPrepare($operation->id, $payload);
            } else {
                $executor->runPublish($operation->id, $payload);
            }
        });
    }

    private function serializePipelineOperation(?PublishPipelineOperation $operation): ?array
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
            'show_url' => route('publish.pipeline.operations.show', ['operation' => $operation->id]),
            'stream_url' => route('publish.pipeline.operations.stream', ['operation' => $operation->id]),
        ];
    }
}
