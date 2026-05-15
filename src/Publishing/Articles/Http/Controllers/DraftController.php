<?php

namespace hexa_app_publish\Publishing\Articles\Http\Controllers;

use hexa_core\Http\Controllers\Controller;
use hexa_core\Models\User;
use hexa_app_publish\Publishing\Articles\Models\PublishArticle;
use hexa_app_publish\Publishing\Pipeline\Services\PipelineStateService;
use hexa_app_publish\Publishing\Sites\Models\PublishSite;
use hexa_app_publish\Services\ArticleDeleteService;
use hexa_package_google_docs\Services\GoogleDocsWriteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DraftController extends Controller
{
    public function index(Request $request): View|JsonResponse
    {
        $query = PublishArticle::with(['creator', 'site', 'pipelineState']);

        if ($request->filled('user_id')) {
            $query->where('created_by', $request->input('user_id'));
        }

        if ($request->filled('q')) {
            $q = $request->input('q');
            $query->where(function ($qb) use ($q) {
                $qb->where('title', 'like', '%' . $q . '%')
                    ->orWhere('article_id', 'like', '%' . $q . '%')
                    ->orWhereHas('site', fn($s) => $s->where('name', 'like', '%' . $q . '%'));
            });
        }

        if ($request->filled('status')) {
            $this->applyStatusFilter($query, (string) $request->input('status'));
        }

        if ($request->filled('article_type')) {
            $query->where('article_type', $request->input('article_type'));
        }

        $siteFilter = trim((string) $request->input('site_id', ''));
        if ($siteFilter !== '') {
            if ($siteFilter === '__none__') {
                $query->whereNull('publish_site_id');
            } elseif (ctype_digit($siteFilter)) {
                $query->where('publish_site_id', (int) $siteFilter);
            }
        }

        $articles = $query->orderByDesc('updated_at')->paginate(100)->withQueryString();
        $filterOptions = $this->filterOptions();

        $viewData = [
            'drafts' => $articles,
            'articleTypes' => $filterOptions['articleTypes'],
            'sites' => $filterOptions['sites'],
            'statuses' => $filterOptions['statuses'],
            'users' => $filterOptions['users'],
        ];

        if (
            $request->wantsJson()
            || $request->ajax()
            || $request->header('X-Requested-With') === 'XMLHttpRequest'
        ) {
            return response()->json([
                'success' => true,
                'html' => view('app-publish::publishing.articles.drafts.index', $viewData)->render(),
                'total' => $articles->total(),
                'current_page' => $articles->currentPage(),
                'last_page' => $articles->lastPage(),
                'from' => $articles->firstItem(),
                'to' => $articles->lastItem(),
                'ids' => $articles->getCollection()->pluck('id')->values()->all(),
            ]);
        }

        return view('app-publish::publishing.articles.drafts.index', $viewData);
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
        $validated['created_by'] = $validated['created_by'] ?? auth()->id();

        $draft = PublishArticle::create($validated);

        hexaLog('publish', 'draft_created', 'Draft created: ' . $draft->title);

        return response()->json([
            'success'  => true,
            'message'  => 'Draft created successfully.',
            'article'  => $draft,
            'draft'    => $draft,
            'redirect' => route('publish.drafts.show', $draft->id),
        ]);
    }

    public function prepare(Request $request, int $id): JsonResponse
    {
        return app(\hexa_app_publish\Publishing\Pipeline\Http\Controllers\PipelineController::class)->prepare($request, $id);
    }

    public function approve(Request $request, int $id): JsonResponse
    {
        return app(\hexa_app_publish\Publishing\Pipeline\Http\Controllers\PipelineController::class)->publish($request, $id);
    }

    public function exportGoogleDoc(Request $request, int $id): JsonResponse
    {
        $article = PublishArticle::with(['creator', 'site', 'pipelineState'])->findOrFail($id);
        $stateService = app(PipelineStateService::class);
        $payload = $stateService->payload($article);
        $docService = app(GoogleDocsWriteService::class);

        $title = trim((string) ($request->input('title') ?: ($payload['articleTitle'] ?? $article->title ?? 'Untitled')));
        if ($title === '') {
            $title = 'Untitled';
        }

        $excerpt = trim((string) ($request->input('excerpt') ?: ($payload['articleDescription'] ?? $article->excerpt ?? '')));
        $body = $this->resolveGoogleDocBody($article, $payload, (string) $request->input('body', ''));
        if (trim(strip_tags($body)) === '') {
            return response()->json([
                'success' => false,
                'message' => 'Google Doc export requires article content.',
            ], 422);
        }

        $html = $this->googleDocExportHtml($article, $title, $excerpt, $body);
        $existing = is_array($payload['googleDocExport'] ?? null) ? $payload['googleDocExport'] : [];
        $existingId = trim((string) ($existing['document_id'] ?? ''));
        $result = $existingId !== ''
            ? $docService->updateDocumentFromHtml($existingId, $title, $html)
            : $docService->createDocumentFromHtml($title, $html);

        if (!((bool) ($result['success'] ?? false))) {
            return response()->json([
                'success' => false,
                'message' => (string) ($result['message'] ?? 'Google Doc export failed.'),
                'google_doc' => $this->googleDocDescriptor($article, $payload, $title, $excerpt, $body),
            ], 422);
        }

        $payload['googleDocExport'] = [
            'document_id' => (string) ($result['document_id'] ?? $existingId),
            'url' => trim((string) ($result['web_view_link'] ?? $result['normalized_url'] ?? '')),
            'owner_email' => trim((string) ($result['owner_email'] ?? $docService->ownerEmail())),
            'connected_email' => trim((string) ($result['connected_email'] ?? '')),
            'last_exported_at' => now()->toIso8601String(),
            'last_export_hash' => $this->googleDocHash($title, $excerpt, $body, (string) ($article->article_type ?? ''), (string) ($article->site?->name ?? '')),
            'shared_with_requested_owner' => (bool) ($result['shared_with_requested_owner'] ?? false),
        ];
        $stateService->save($article, $payload);

        return response()->json([
            'success' => true,
            'message' => (string) ($result['message'] ?? 'Google Doc exported successfully.'),
            'google_doc' => $this->googleDocDescriptor($article, $payload, $title, $excerpt, $body),
        ]);
    }

    public function show(Request $request, int $id): View|JsonResponse
    {
        $draft = PublishArticle::with(['creator', 'site', 'pipelineState'])->findOrFail($id);
        $filterOptions = $this->filterOptions();

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'article' => $draft]);
        }

        return view('app-publish::publishing.articles.drafts.index', [
            'drafts'    => PublishArticle::with(['creator', 'site', 'pipelineState'])->orderByDesc('updated_at')->paginate(100),
            'editDraft' => $draft,
            'articleTypes' => $filterOptions['articleTypes'],
            'sites' => $filterOptions['sites'],
            'statuses' => $filterOptions['statuses'],
            'users' => $filterOptions['users'],
        ]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $draft = PublishArticle::findOrFail($id);

        $validated = $request->validate([
            'title'      => 'required|string|max:500',
            'body'       => 'nullable|string',
            'excerpt'    => 'nullable|string|max:1000',
            'created_by' => 'nullable|integer|exists:users,id',
            'notes'      => 'nullable|string',
            'status'     => 'nullable|string',
        ]);

        $draft->update($validated);

        hexaLog('publish', 'draft_updated', 'Article updated: ' . $draft->title);

        return response()->json([
            'success' => true,
            'message' => 'Article ' . $draft->title . ' updated.',
            'article' => $draft,
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $article = PublishArticle::findOrFail($id);
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

        $deleteService = app(ArticleDeleteService::class);
        $allLogs = [];

        foreach ($validated['ids'] as $id) {
            $article = PublishArticle::find($id);
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

    private function filterOptions(): array
    {
        $configuredArticleTypes = array_values(array_filter(array_map('strval', (array) config('hws-publish.article_types', []))));
        $liveArticleTypes = PublishArticle::query()
            ->whereNotNull('article_type')
            ->where('article_type', '!=', '')
            ->distinct()
            ->orderBy('article_type')
            ->pluck('article_type')
            ->map(fn ($value) => (string) $value)
            ->all();

        return [
            'articleTypes' => array_values(array_unique(array_merge($configuredArticleTypes, $liveArticleTypes))),
            'sites' => PublishSite::query()->select(['id', 'name'])->orderBy('name')->get(),
            'statuses' => [
                'completed' => 'Completed',
                'drafted' => 'Drafted',
                'drafted-wordpress' => 'Drafted to WordPress',
                'failed' => 'Failed',
            ],
            'users' => User::query()->select(['id', 'name'])->orderBy('name')->get(),
        ];
    }

    private function applyStatusFilter($query, string $status): void
    {
        $normalized = trim($status);

        match ($normalized) {
            'completed' => $query->where(function ($qb) {
                $qb->where('wp_status', 'publish')
                    ->orWhere('status', 'published')
                    ->orWhere(function ($nested) {
                        $nested->where('status', 'completed')->whereNull('wp_post_id');
                    });
            }),
            'drafted' => $query->whereNull('wp_post_id')->whereNotIn('status', ['failed', 'deleted', 'completed', 'published']),
            'drafted-wordpress' => $query->whereNotNull('wp_post_id')->where(function ($qb) {
                $qb->whereNull('wp_status')->orWhereIn('wp_status', ['draft', 'pending']);
            }),
            'failed' => $query->where('status', 'failed'),
            default => $query->where('status', $normalized),
        };
    }

    private function resolveGoogleDocBody(PublishArticle $article, array $payload, string $overrideBody = ''): string
    {
        if (trim($overrideBody) !== '') {
            return $overrideBody;
        }

        return (string) ($payload['editorContent'] ?? $payload['spunContent'] ?? $article->body ?? '');
    }

    private function googleDocExportHtml(PublishArticle $article, string $title, string $excerpt, string $body): string
    {
        $bodyHtml = trim($body);
        if ($bodyHtml !== '' && strip_tags($bodyHtml) === $bodyHtml) {
            $bodyHtml = nl2br(e($bodyHtml));
        }

        $siteName = trim((string) ($article->site?->name ?? ''));
        $creatorName = trim((string) ($article->creator?->name ?? ''));
        $html = '<h1>' . e($title) . '</h1>';
        if ($excerpt !== '') {
            $html .= '<p><em>' . e($excerpt) . '</em></p>';
        }

        $meta = [];
        if ($siteName !== '') $meta[] = '<strong>Publication:</strong> ' . e($siteName);
        if ($creatorName !== '') $meta[] = '<strong>Submitted by:</strong> ' . e($creatorName);
        if (trim((string) $article->article_id) !== '') $meta[] = '<strong>Article ID:</strong> ' . e((string) $article->article_id);
        if (trim((string) ($article->wp_post_url ?? '')) !== '') $meta[] = '<strong>WordPress URL:</strong> <a href="' . e((string) $article->wp_post_url) . '">' . e((string) $article->wp_post_url) . '</a>';
        if ($meta !== []) {
            $html .= '<p>' . implode('<br>', $meta) . '</p>';
        }

        return trim($html . '<hr>' . $bodyHtml);
    }

    private function googleDocHash(string $title, string $excerpt, string $body, string $articleType, string $siteName): string
    {
        return md5(implode("\n", [trim($title), trim($excerpt), trim($body), trim($articleType), trim($siteName)]));
    }

    private function googleDocDescriptor(PublishArticle $article, array $payload, ?string $title = null, ?string $excerpt = null, ?string $body = null): array
    {
        $state = is_array($payload['googleDocExport'] ?? null) ? $payload['googleDocExport'] : [];
        $resolvedTitle = trim((string) ($title ?: ($payload['articleTitle'] ?? $article->title ?? 'Untitled')));
        if ($resolvedTitle === '') {
            $resolvedTitle = 'Untitled';
        }
        $resolvedExcerpt = trim((string) ($excerpt ?? ($payload['articleDescription'] ?? $article->excerpt ?? '')));
        $resolvedBody = $body ?? $this->resolveGoogleDocBody($article, $payload);
        $hash = $this->googleDocHash($resolvedTitle, $resolvedExcerpt, (string) $resolvedBody, (string) ($article->article_type ?? ''), (string) ($article->site?->name ?? ''));
        $documentId = trim((string) ($state['document_id'] ?? ''));
        $url = trim((string) ($state['url'] ?? $state['web_view_link'] ?? $state['normalized_url'] ?? ''));
        $exists = $documentId !== '';
        $isStale = $exists && ((string) ($state['last_export_hash'] ?? '')) !== $hash;

        return [
            'exists' => $exists,
            'is_stale' => $isStale,
            'document_id' => $documentId,
            'url' => $url,
            'owner_email' => trim((string) ($state['owner_email'] ?? app(\hexa_package_google_docs\Services\GoogleDocsWriteService::class)->ownerEmail())),
            'connected_email' => trim((string) ($state['connected_email'] ?? '')),
            'last_exported_at' => trim((string) ($state['last_exported_at'] ?? '')),
            'last_export_hash' => trim((string) ($state['last_export_hash'] ?? '')),
            'status_label' => !$exists ? 'No Google Doc yet' : ($isStale ? 'Google Doc out of date' : 'Google Doc up to date'),
            'action_label' => !$exists ? 'Create Google Doc' : ($isStale ? 'Update Google Doc' : 'Refresh Google Doc'),
            'open_label' => $exists ? 'Open Google Doc' : '',
        ];
    }
}
