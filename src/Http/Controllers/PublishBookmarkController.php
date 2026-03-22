<?php

namespace hexa_app_publish\Http\Controllers;

use hexa_core\Http\Controllers\Controller;
use hexa_app_publish\Models\PublishBookmark;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * PublishBookmarkController — CRUD for bookmarked article URLs.
 */
class PublishBookmarkController extends Controller
{
    /**
     * List bookmarks, optionally filtered by user.
     *
     * @param Request $request
     * @return View
     */
    public function index(Request $request): View
    {
        $query = PublishBookmark::with('user');

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->input('user_id'));
        }

        $bookmarks = $query->orderByDesc('created_at')->paginate(25);

        return view('app-publish::article.bookmarks.index', [
            'bookmarks' => $bookmarks,
        ]);
    }

    /**
     * Save a new bookmark.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'url'     => 'required|url|max:2000',
            'title'   => 'nullable|string|max:500',
            'user_id' => 'nullable|integer|exists:users,id',
            'source'  => 'nullable|string|max:100',
            'tags'    => 'nullable|string|max:500',
            'notes'   => 'nullable|string',
        ]);

        $validated['user_id'] = $validated['user_id'] ?? auth()->id();

        $bookmark = PublishBookmark::create($validated);

        activity_log('publish', 'bookmark_created', "Bookmark saved: {$bookmark->url}");

        return response()->json([
            'success'  => true,
            'message'  => 'Bookmark saved successfully.',
            'bookmark' => $bookmark->load('user'),
        ]);
    }

    /**
     * Update a bookmark.
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $bookmark = PublishBookmark::findOrFail($id);

        $validated = $request->validate([
            'url'     => 'required|url|max:2000',
            'title'   => 'nullable|string|max:500',
            'user_id' => 'nullable|integer|exists:users,id',
            'source'  => 'nullable|string|max:100',
            'tags'    => 'nullable|string|max:500',
            'notes'   => 'nullable|string',
        ]);

        $bookmark->update($validated);

        activity_log('publish', 'bookmark_updated', "Bookmark updated: {$bookmark->url}");

        return response()->json([
            'success' => true,
            'message' => 'Bookmark updated successfully.',
        ]);
    }

    /**
     * Delete a bookmark.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function destroy(int $id): JsonResponse
    {
        $bookmark = PublishBookmark::findOrFail($id);
        $url = $bookmark->url;

        $bookmark->delete();

        activity_log('publish', 'bookmark_deleted', "Bookmark deleted: {$url}");

        return response()->json([
            'success' => true,
            'message' => 'Bookmark deleted successfully.',
        ]);
    }
}
