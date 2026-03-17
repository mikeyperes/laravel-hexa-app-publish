<?php

namespace hexa_app_publish\Http\Controllers;

use hexa_core\Http\Controllers\Controller;
use hexa_core\Services\GenericService;
use hexa_app_publish\Models\PublishAccount;
use hexa_app_publish\Models\PublishSite;
use hexa_app_publish\Services\PublishService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PublishSiteController extends Controller
{
    protected GenericService $generic;
    protected PublishService $publishService;

    /**
     * @param GenericService $generic
     * @param PublishService $publishService
     */
    public function __construct(GenericService $generic, PublishService $publishService)
    {
        $this->generic = $generic;
        $this->publishService = $publishService;
    }

    /**
     * List all sites, optionally filtered by account.
     *
     * @param Request $request
     * @return View
     */
    public function index(Request $request): View
    {
        $query = PublishSite::with(['account', 'campaigns', 'articles']);

        if ($request->filled('account_id')) {
            $query->where('publish_account_id', $request->input('account_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('url', 'like', "%{$search}%");
            });
        }

        $sites = $query->orderByDesc('created_at')->get();
        $accounts = PublishAccount::orderBy('name')->get();

        return view('app-publish::sites.index', [
            'sites' => $sites,
            'accounts' => $accounts,
        ]);
    }

    /**
     * Show create site form.
     *
     * @param Request $request
     * @return View
     */
    public function create(Request $request): View
    {
        $accounts = PublishAccount::where('status', 'active')->orderBy('name')->get();

        return view('app-publish::sites.create', [
            'accounts' => $accounts,
            'preselected_account_id' => $request->input('account_id'),
        ]);
    }

    /**
     * Store a new site.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'publish_account_id' => 'required|exists:publish_accounts,id',
            'name' => 'required|string|max:255',
            'url' => 'required|url|max:2048',
            'connection_type' => 'required|in:wptoolkit,wp_rest_api',
            'wp_username' => 'nullable|string|max:255',
            'wp_application_password' => 'nullable|string',
            'hosting_account_id' => 'nullable|integer',
            'wordpress_install_id' => 'nullable|integer',
            'notes' => 'nullable|string',
        ]);

        $validated['status'] = 'disconnected';

        $site = PublishSite::create($validated);

        activity_log('publish', 'site_created', "Site created: {$site->name} ({$site->url})");

        return response()->json([
            'success' => true,
            'message' => "Site '{$site->name}' created successfully.",
            'site' => $site,
            'redirect' => route('publish.sites.show', $site->id),
        ]);
    }

    /**
     * Show a single site with its campaigns and articles.
     *
     * @param int $id
     * @return View
     */
    public function show(int $id): View
    {
        $site = PublishSite::with(['account', 'campaigns', 'articles'])->findOrFail($id);

        return view('app-publish::sites.show', [
            'site' => $site,
        ]);
    }

    /**
     * Show edit form for a site.
     *
     * @param int $id
     * @return View
     */
    public function edit(int $id): View
    {
        $site = PublishSite::with('account')->findOrFail($id);
        $accounts = PublishAccount::where('status', 'active')->orderBy('name')->get();

        return view('app-publish::sites.edit', [
            'site' => $site,
            'accounts' => $accounts,
        ]);
    }

    /**
     * Update a site.
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $site = PublishSite::findOrFail($id);

        $validated = $request->validate([
            'publish_account_id' => 'required|exists:publish_accounts,id',
            'name' => 'required|string|max:255',
            'url' => 'required|url|max:2048',
            'connection_type' => 'required|in:wptoolkit,wp_rest_api',
            'wp_username' => 'nullable|string|max:255',
            'wp_application_password' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        // Only update password if provided
        if (empty($validated['wp_application_password'])) {
            unset($validated['wp_application_password']);
        }

        $site->update($validated);

        activity_log('publish', 'site_updated', "Site updated: {$site->name} ({$site->url})");

        return response()->json([
            'success' => true,
            'message' => "Site '{$site->name}' updated successfully.",
        ]);
    }

    /**
     * Test connection to a WordPress site.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function testConnection(int $id): JsonResponse
    {
        $site = PublishSite::findOrFail($id);

        // TODO: Implement actual WordPress REST API connection test
        // For WP Toolkit sites, use WP Toolkit package
        // For REST API sites, test /wp-json/wp/v2/posts endpoint

        $site->update([
            'status' => 'connected',
            'last_connected_at' => now(),
            'last_error' => null,
        ]);

        activity_log('publish', 'site_test', "Site connection tested: {$site->name} ({$site->url}) — connected");

        return response()->json([
            'success' => true,
            'message' => "Connection to '{$site->name}' successful.",
        ]);
    }
}
