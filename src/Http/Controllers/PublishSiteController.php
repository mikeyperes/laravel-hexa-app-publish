<?php

namespace hexa_app_publish\Http\Controllers;

use hexa_core\Http\Controllers\Controller;
use hexa_core\Services\GenericService;
use hexa_app_publish\Models\PublishAccount;
use hexa_app_publish\Models\PublishSite;
use hexa_app_publish\Services\PublishService;
use hexa_package_whm\Models\HostingAccount;
use hexa_package_whm\Models\WhmServer;
use hexa_package_wordpress\Services\WordPressService;
use hexa_package_wptoolkit\Services\WpToolkitService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PublishSiteController extends Controller
{
    protected GenericService $generic;
    protected PublishService $publishService;
    protected WordPressService $wp;
    protected WpToolkitService $wptoolkit;

    /**
     * @param GenericService   $generic
     * @param PublishService   $publishService
     * @param WordPressService $wp
     * @param WpToolkitService $wptoolkit
     */
    public function __construct(GenericService $generic, PublishService $publishService, WordPressService $wp, WpToolkitService $wptoolkit)
    {
        $this->generic = $generic;
        $this->publishService = $publishService;
        $this->wp = $wp;
        $this->wptoolkit = $wptoolkit;
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

        hexaLog('publish', 'site_created', "Site created: {$site->name} ({$site->url})");

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

        hexaLog('publish', 'site_updated', "Site updated: {$site->name} ({$site->url})");

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

            hexaLog('publish', 'site_test', "Site connection tested: {$site->name} ({$site->url}) — " . ($result['success'] ? 'connected' : 'failed: ' . $result['message']));

            return response()->json($result);
        }

        // WP Toolkit connection — TODO: implement via wptoolkit package
        // WP Toolkit — use wpCliTestWriteAccess
        $resolved = $this->resolveServer($site);
        if (!$resolved['server']) {
            return response()->json(['success' => false, 'message' => 'Server not found for this site.']);
        }

        $result = $this->wptoolkit->wpCliTestWriteAccess($resolved['server'], $site->wordpress_install_id);

        $site->update([
            'status' => $result['success'] ? 'connected' : 'error',
            'last_connected_at' => $result['success'] ? now() : $site->last_connected_at,
            'last_error' => $result['success'] ? null : $result['message'],
        ]);

        hexaLog('publish', 'site_test', "WP Toolkit test: {$site->name} — " . ($result['success'] ? 'write access confirmed' : 'failed'));

        return response()->json($result);
    }

    /**
     * Test full write access for a WP Toolkit site via SSH.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function testWriteAccess(int $id): JsonResponse
    {
        $site = PublishSite::findOrFail($id);
        $resolved = $this->resolveServer($site);
        if (!$resolved['server'] || !$site->wordpress_install_id) {
            return response()->json(['success' => false, 'message' => 'Server or install ID not configured.']);
        }

        $result = $this->wptoolkit->wpCliTestWriteAccess($resolved['server'], $site->wordpress_install_id);

        $site->update([
            'status' => $result['success'] ? 'connected' : 'error',
            'last_connected_at' => $result['success'] ? now() : $site->last_connected_at,
            'last_error' => $result['success'] ? null : $result['message'],
        ]);

        return response()->json($result);
    }

    /**
     * Get WordPress admin users for author selection via wp-cli.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function getAuthors(int $id): JsonResponse
    {
        $site = PublishSite::findOrFail($id);
        $resolved = $this->resolveServer($site);
        if (!$resolved['server'] || !$site->wordpress_install_id) {
            return response()->json(['success' => false, 'authors' => [], 'message' => 'Server not configured.']);
        }

        $ssh = $this->wptoolkit->getConnection($resolved['server']);
        if (!$ssh['success']) {
            return response()->json(['success' => false, 'authors' => [], 'message' => $ssh['error'] ?? 'SSH failed']);
        }

        $escapedId = escapeshellarg((string) $site->wordpress_install_id);
        $cmd = "wp-toolkit --wp-cli -instance-id {$escapedId} -- user list --role=administrator,editor,author --fields=user_login,display_name,roles --format=json 2>/dev/null";
        $output = trim($ssh['connection']->exec($cmd));

        // Filter PHP warnings
        $lines = explode("\n", $output);
        $jsonLine = '';
        foreach ($lines as $line) {
            $line = trim($line);
            if (str_starts_with($line, '[') || str_starts_with($line, '{')) {
                $jsonLine = $line;
                break;
            }
        }

        $authors = json_decode($jsonLine, true);
        if (!is_array($authors)) {
            return response()->json(['success' => false, 'authors' => [], 'message' => 'Failed to parse WP users.']);
        }

        return response()->json([
            'success' => true,
            'authors' => $authors,
            'default_author' => $site->default_author,
        ]);
    }

    /**
     * Set the default publishing author for a site.
     *
     * @param Request $request
     * @param int     $id
     * @return JsonResponse
     */
    public function setDefaultAuthor(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'author' => 'required|string|max:255',
        ]);

        $site = PublishSite::findOrFail($id);
        $site->update(['default_author' => $validated['author']]);

        hexaLog('publish', 'site_author_set', "Default author set for {$site->name}: {$validated['author']}");

        return response()->json([
            'success' => true,
            'message' => "Default author set to '{$validated['author']}'.",
        ]);
    }

    /**
     * Resolve the WHM server for a WP Toolkit site.
     *
     * @param PublishSite $site
     * @return array{server: WhmServer|null, account: HostingAccount|null}
     */
    private function resolveServer(PublishSite $site): array
    {
        $account = HostingAccount::find($site->hosting_account_id);
        $server = $account ? WhmServer::find($account->whm_server_id) : null;
        return ['server' => $server, 'account' => $account];
    }
}
