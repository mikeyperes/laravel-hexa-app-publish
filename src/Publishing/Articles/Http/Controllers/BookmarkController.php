<?php

namespace hexa_app_publish\Publishing\Articles\Http\Controllers;

use hexa_core\Http\Controllers\Controller;
use hexa_app_publish\Publishing\Articles\Models\PublishBookmark;
use hexa_app_publish\Publishing\Articles\Models\PublishFailedSource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * PublishBookmarkController — CRUD for bookmarked article URLs.
 */
class BookmarkController extends Controller
{
    /**
     * List bookmarks, optionally filtered by user.
     *
     * @param Request $request
     * @return View
     */
    public function index(Request $request): View|JsonResponse
    {
        $query = PublishBookmark::with('user');

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->input('user_id'));
        }

        // Return JSON for AJAX calls (pipeline bookmarks tab)
        if ($request->input('format') === 'json' || $request->wantsJson()) {
            $bookmarks = $query->orderByDesc('created_at')->get();
            return response()->json(['success' => true, 'data' => $bookmarks]);
        }

        $bookmarks = $query->orderByDesc('created_at')->paginate(25);

        $failedSources = PublishFailedSource::orderByDesc('created_at')->paginate(25, ['*'], 'failed_page');

        return view('app-publish::publishing.articles.bookmarks.index', [
            'bookmarks' => $bookmarks,
            'failedSources' => $failedSources,
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

        hexaLog('publish', 'bookmark_created', "Bookmark saved: {$bookmark->url}");

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

        hexaLog('publish', 'bookmark_updated', "Bookmark updated: {$bookmark->url}");

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

        hexaLog('publish', 'bookmark_deleted', "Bookmark deleted: {$url}");

        return response()->json([
            'success' => true,
            'message' => 'Bookmark deleted successfully.',
        ]);
    }

    /**
     * Fetch the page title for a bookmark URL and update it.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function fetchTitle(int $id): JsonResponse
    {
        $bookmark = PublishBookmark::findOrFail($id);
        $title = null;

        try {
            $ctx = stream_context_create([
                'http' => ['timeout' => 8, 'user_agent' => 'Mozilla/5.0', 'follow_location' => true],
                'ssl' => ['verify_peer' => false],
            ]);
            $html = @file_get_contents($bookmark->url, false, $ctx);
            if ($html && preg_match('/<title[^>]*>([^<]+)<\/title>/i', $html, $m)) {
                $title = trim(html_entity_decode($m[1], ENT_QUOTES, 'UTF-8'));
            }
        } catch (\Exception $e) {
            // Silently fail — bookmark is already saved
        }

        if ($title) {
            $bookmark->update(['title' => $title]);
        }

        return response()->json([
            'success' => !empty($title),
            'title'   => $title,
            'message' => $title ? 'Title fetched.' : 'Could not fetch title.',
        ]);
    }

    /**
     * Store a failed source URL.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function storeFailed(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'url'           => 'required|url|max:2000',
            'title'         => 'nullable|string|max:500',
            'error_message' => 'nullable|string|max:2000',
            'source_api'    => 'nullable|string|max:50',
        ]);

        $validated['user_id'] = auth()->id();

        $failed = PublishFailedSource::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Failed source saved.',
            'id'      => $failed->id,
        ]);
    }

    /**
     * Delete a failed source.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function destroyFailed(int $id): JsonResponse
    {
        PublishFailedSource::findOrFail($id)->delete();

        return response()->json([
            'success' => true,
            'message' => 'Failed source removed.',
        ]);
    }
}
