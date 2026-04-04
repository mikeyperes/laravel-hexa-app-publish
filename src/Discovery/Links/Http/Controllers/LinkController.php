<?php

namespace hexa_app_publish\Discovery\Links\Http\Controllers;

use hexa_core\Http\Controllers\Controller;
use hexa_app_publish\Models\PublishAccount;
use hexa_app_publish\Models\PublishLinkList;
use hexa_app_publish\Models\PublishSitemap;
use hexa_app_publish\Services\LinkInsertionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LinkController extends Controller
{
    protected LinkInsertionService $linkService;

    /**
     * @param LinkInsertionService $linkService
     */
    public function __construct(LinkInsertionService $linkService)
    {
        $this->linkService = $linkService;
    }

    /**
     * List links and sitemaps for an account.
     *
     * @param Request $request
     * @return View
     */
    public function index(Request $request): View
    {
        $accounts = PublishAccount::orderBy('name')->get();
        $accountId = $request->input('account_id');

        $linksQuery = PublishLinkList::with('account');
        $sitemapsQuery = PublishSitemap::with('account');

        if ($accountId) {
            $linksQuery->where('publish_account_id', $accountId);
            $sitemapsQuery->where('publish_account_id', $accountId);
        }

        $links = $linksQuery->orderByDesc('priority')->orderBy('name')->get();
        $sitemaps = $sitemapsQuery->orderBy('name')->get();

        return view('app-publish::links.index', [
            'links' => $links,
            'sitemaps' => $sitemaps,
            'accounts' => $accounts,
        ]);
    }

    /**
     * Store a new link.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function storeLink(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'publish_account_id' => 'required|exists:publish_accounts,id',
            'name' => 'required|string|max:255',
            'type' => 'required|in:backlink,internal,sitemap',
            'url' => 'required|url|max:2048',
            'anchor_text' => 'nullable|string|max:255',
            'context' => 'nullable|string|max:1000',
            'priority' => 'nullable|integer|min:0|max:100',
        ]);

        $link = PublishLinkList::create($validated);

        hexaLog('publish', 'link_created', "Link added: {$link->name} ({$link->url})");

        return response()->json(['success' => true, 'message' => "Link '{$link->name}' added.", 'link' => $link]);
    }

    /**
     * Delete a link.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function destroyLink(int $id): JsonResponse
    {
        $link = PublishLinkList::findOrFail($id);
        $name = $link->name;
        $link->delete();

        hexaLog('publish', 'link_deleted', "Link removed: {$name}");

        return response()->json(['success' => true, 'message' => "Link '{$name}' removed."]);
    }

    /**
     * Toggle a link's active status.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function toggleLink(int $id): JsonResponse
    {
        $link = PublishLinkList::findOrFail($id);
        $link->update(['active' => !$link->active]);

        return response()->json(['success' => true, 'message' => $link->active ? 'Link activated.' : 'Link deactivated.']);
    }

    /**
     * Store a new sitemap.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function storeSitemap(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'publish_account_id' => 'required|exists:publish_accounts,id',
            'name' => 'required|string|max:255',
            'sitemap_url' => 'required|url|max:2048',
        ]);

        $sitemap = PublishSitemap::create($validated);

        // Parse immediately
        $parseResult = $this->linkService->parseSitemap($sitemap);

        hexaLog('publish', 'sitemap_added', "Sitemap added: {$sitemap->name} ({$sitemap->sitemap_url}) — {$parseResult['url_count']} URLs");

        return response()->json([
            'success' => true,
            'message' => "Sitemap '{$sitemap->name}' added. {$parseResult['message']}",
            'sitemap' => $sitemap->fresh(),
        ]);
    }

    /**
     * Re-parse a sitemap.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function refreshSitemap(int $id): JsonResponse
    {
        $sitemap = PublishSitemap::findOrFail($id);
        $result = $this->linkService->parseSitemap($sitemap);

        return response()->json($result);
    }

    /**
     * Delete a sitemap.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function destroySitemap(int $id): JsonResponse
    {
        $sitemap = PublishSitemap::findOrFail($id);
        $name = $sitemap->name;
        $sitemap->delete();

        hexaLog('publish', 'sitemap_deleted', "Sitemap removed: {$name}");

        return response()->json(['success' => true, 'message' => "Sitemap '{$name}' removed."]);
    }
}
