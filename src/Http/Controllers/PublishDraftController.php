<?php

namespace hexa_app_publish\Http\Controllers;

use hexa_core\Http\Controllers\Controller;
use hexa_app_publish\Models\PublishArticle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * PublishDraftController — CRUD for draft articles (status = drafting).
 */
class PublishDraftController extends Controller
{
    /**
     * List draft articles, optionally filtered by user.
     *
     * @param Request $request
     * @return View
     */
    public function index(Request $request): View
    {
        $query = PublishArticle::where('status', 'drafting')
            ->with(['creator', 'site']);

        if ($request->filled('user_id')) {
            $query->where('created_by', $request->input('user_id'));
        }

        $drafts = $query->orderByDesc('updated_at')->paginate(25);

        return view('app-publish::article.drafts.index', [
            'drafts' => $drafts,
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

        activity_log('publish', 'draft_created', "Draft created: {$draft->title}");

        return response()->json([
            'success'  => true,
            'message'  => 'Draft created successfully.',
            'draft'    => $draft,
            'redirect' => route('publish.drafts.show', $draft->id),
        ]);
    }

    /**
     * Show a single draft for editing.
     *
     * @param int $id
     * @return View
     */
    public function show(int $id): View
    {
        $draft = PublishArticle::where('status', 'drafting')->findOrFail($id);

        return view('app-publish::article.drafts.index', [
            'drafts'      => PublishArticle::where('status', 'drafting')->orderByDesc('updated_at')->paginate(25),
            'editDraft'   => $draft,
        ]);
    }

    /**
     * Update a draft article.
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $draft = PublishArticle::where('status', 'drafting')->findOrFail($id);

        $validated = $request->validate([
            'title'      => 'required|string|max:500',
            'body'       => 'nullable|string',
            'excerpt'    => 'nullable|string|max:1000',
            'created_by' => 'nullable|integer|exists:users,id',
            'notes'      => 'nullable|string',
        ]);

        $draft->update($validated);

        activity_log('publish', 'draft_updated', "Draft updated: {$draft->title}");

        return response()->json([
            'success' => true,
            'message' => "Draft '{$draft->title}' updated successfully.",
        ]);
    }

    /**
     * Delete a draft article.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function destroy(int $id): JsonResponse
    {
        $draft = PublishArticle::where('status', 'drafting')->findOrFail($id);
        $title = $draft->title;

        $draft->delete();

        activity_log('publish', 'draft_deleted', "Draft deleted: {$title}");

        return response()->json([
            'success' => true,
            'message' => "Draft '{$title}' deleted successfully.",
        ]);
    }
}
