<?php

namespace hexa_app_publish\Publishing\Articles\Http\Controllers;

use hexa_core\Http\Controllers\Controller;
use hexa_app_publish\Models\PublishArticle;
use hexa_app_publish\Services\ArticleDeleteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * PublishDraftController — CRUD for articles (all statuses).
 */
class DraftController extends Controller
{
    /**
     * List all articles with optional search.
     *
     * @param Request $request
     * @return View|JsonResponse
     */
    public function index(Request $request): View|JsonResponse
    {
        $query = PublishArticle::with(['creator', 'site']);

        if ($request->filled('user_id')) {
            $query->where('created_by', $request->input('user_id'));
        }

        if ($request->filled('q')) {
            $q = $request->input('q');
            $query->where(function ($qb) use ($q) {
                $qb->where('title', 'like', "%{$q}%")
                    ->orWhere('article_id', 'like', "%{$q}%")
                    ->orWhereHas('site', fn($s) => $s->where('name', 'like', "%{$q}%"));
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $articles = $query->orderByDesc('updated_at')->paginate(100);

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'data' => $articles]);
        }

        return view('app-publish::article.drafts.index', [
            'drafts' => $articles,
        ]);
    }

    /**
     * Create a new draft article.
     *
     * @param Request $request
     * @return JsonResponse
     */
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

        hexaLog('publish', 'draft_created', "Draft created: {$draft->title}");

        return response()->json([
            'success'  => true,
            'message'  => 'Draft created successfully.',
            'article'  => $draft,
            'draft'    => $draft,
            'redirect' => route('publish.drafts.show', $draft->id),
        ]);
    }

    /**
     * Show a single article.
     *
     * @param Request $request
     * @param int $id
     * @return View|JsonResponse
     */
    public function show(Request $request, int $id): View|JsonResponse
    {
        $draft = PublishArticle::findOrFail($id);

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'article' => $draft]);
        }

        return view('app-publish::article.drafts.index', [
            'drafts'    => PublishArticle::orderByDesc('updated_at')->paginate(100),
            'editDraft' => $draft,
        ]);
    }

    /**
     * Update an article.
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
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

        hexaLog('publish', 'draft_updated', "Article updated: {$draft->title}");

        return response()->json([
            'success' => true,
            'message' => "Article '{$draft->title}' updated.",
            'article' => $draft,
        ]);
    }

    /**
     * Delete an article (local + WP if published).
     *
     * @param int $id
     * @return JsonResponse
     */
    public function destroy(int $id): JsonResponse
    {
        $article = PublishArticle::findOrFail($id);
        $deleteService = app(ArticleDeleteService::class);
        $result = $deleteService->delete($article);

        return response()->json([
            'success' => $result['success'],
            'message' => 'Article deleted.',
            'log'     => $result['log'],
        ]);
    }

    /**
     * Bulk delete articles.
     *
     * @param Request $request
     * @return JsonResponse
     */
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
            'message' => count($validated['ids']) . ' article(s) deleted.',
            'logs'    => $allLogs,
        ]);
    }
}
