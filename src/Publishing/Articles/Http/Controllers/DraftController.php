<?php

namespace hexa_app_publish\Publishing\Articles\Http\Controllers;

use hexa_core\Http\Controllers\Controller;
use hexa_app_publish\Publishing\Access\Services\PublishAccessService;
use hexa_app_publish\Publishing\Articles\Models\PublishArticle;
use hexa_app_publish\Services\ArticleDeleteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * PublishDraftController — CRUD for articles (all statuses).
 */
class DraftController extends Controller
{
    public function __construct(private PublishAccessService $access)
    {
    }

    public function index(Request $request): View|JsonResponse
    {
        $query = $this->access->articleQuery($request->user())->with(['creator', 'site', 'latestApprovalEmail.sender'])->withCount('approvalEmails');

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

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'data' => $articles]);
        }

        return view('app-publish::publishing.articles.drafts.index', [
            'drafts' => $articles,
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
        $draft->loadMissing(['creator', 'site', 'latestApprovalEmail.sender'])->loadCount('approvalEmails');

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'article' => $draft]);
        }

        return view('app-publish::publishing.articles.drafts.index', [
            'drafts' => $this->access->articleQuery($request->user())->with(['creator', 'site', 'latestApprovalEmail.sender'])->withCount('approvalEmails')->orderByDesc('updated_at')->paginate(100),
            'editDraft' => $draft,
        ]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $draft = $this->access->resolveArticleOrFail($request->user(), $id);
        $draft->loadMissing(['creator', 'site', 'latestApprovalEmail.sender'])->loadCount('approvalEmails');

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
}
