<?php

namespace hexa_app_publish\Http\Controllers;

use hexa_core\Http\Controllers\Controller;
use hexa_core\Models\User;
use hexa_core\Services\GenericService;
use hexa_app_publish\Models\PublishSite;
use hexa_app_publish\Services\PublishService;
use hexa_package_wordpress\Services\WordPressService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PublishSiteController extends Controller
{
    protected GenericService $generic;
    protected PublishService $publishService;
    protected WordPressService $wp;

    /**
     * @param GenericService $generic
     * @param PublishService $publishService
     * @param WordPressService $wp
     */
    public function __construct(GenericService $generic, PublishService $publishService, WordPressService $wp)
    {
        $this->generic = $generic;
        $this->publishService = $publishService;
        $this->wp = $wp;
    }

    /**
     * List all sites, optionally filtered by account.
     *
     * @param Request $request
     * @return View
     */
    public function index(Request $request): View
    {
        $query = PublishSite::with(['user', 'campaigns', 'articles']);

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->input('user_id'));
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
        $users = User::orderBy('name')->get();

        return view('app-publish::sites.index', [
            'sites' => $sites,
            'users' => $users,
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
        $users = User::orderBy('name')->get();

        return view('app-publish::sites.create', [
            'users' => $users,
            'preselected_user_id' => $request->input('user_id'),
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
            'user_id' => 'required|exists:users,id',
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
        $site = PublishSite::with(['user', 'campaigns', 'articles'])->findOrFail($id);

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
        $site = PublishSite::with('user')->findOrFail($id);
        $users = User::orderBy('name')->get();

        return view('app-publish::sites.edit', [
            'site' => $site,
            'users' => $users,
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
            'user_id' => 'required|exists:users,id',
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

        if ($site->connection_type === 'wp_rest_api') {
            if (!$site->wp_username || !$site->wp_application_password) {
                return response()->json([
                    'success' => false,
                    'message' => 'WordPress username and application password are required. Edit the site to add credentials.',
                ]);
            }

            $result = $this->wp->testConnection($site->url, $site->wp_username, $site->wp_application_password);

            $site->update([
                'status' => $result['success'] ? 'connected' : 'error',
                'last_connected_at' => $result['success'] ? now() : $site->last_connected_at,
                'last_error' => $result['success'] ? null : $result['message'],
            ]);

            activity_log('publish', 'site_test', "Site connection tested: {$site->name} ({$site->url}) — " . ($result['success'] ? 'connected' : 'failed: ' . $result['message']));

            return response()->json($result);
        }

        // WP Toolkit connection — TODO: implement via wptoolkit package
        return response()->json([
            'success' => false,
            'message' => 'WP Toolkit connection test not yet implemented.',
        ]);
    }
}
