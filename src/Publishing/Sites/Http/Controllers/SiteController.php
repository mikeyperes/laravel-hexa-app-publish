<?php

namespace hexa_app_publish\Publishing\Sites\Http\Controllers;

use hexa_core\Http\Controllers\Controller;
use hexa_core\Services\GenericService;
use hexa_app_publish\Publishing\Access\Services\PublishAccessService;
use hexa_app_publish\Publishing\Accounts\Models\PublishAccount;
use hexa_app_publish\Publishing\Sites\Models\PublishSite;
use hexa_app_publish\Services\PublishService;
use hexa_package_whm\Models\HostingAccount;
use hexa_package_whm\Models\WhmServer;
use hexa_package_wordpress\Services\WordPressService;
use hexa_package_wptoolkit\Services\WpToolkitService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class SiteController extends Controller
{
    protected GenericService $generic;
    protected PublishService $publishService;
    protected WordPressService $wp;
    protected WpToolkitService $wptoolkit;
    protected PublishAccessService $access;

    /**
     * @param GenericService   $generic
     * @param PublishService   $publishService
     * @param WordPressService $wp
     * @param WpToolkitService $wptoolkit
     */
    public function __construct(GenericService $generic, PublishService $publishService, WordPressService $wp, WpToolkitService $wptoolkit, PublishAccessService $access)
    {
        $this->generic = $generic;
        $this->publishService = $publishService;
        $this->wp = $wp;
        $this->wptoolkit = $wptoolkit;
        $this->access = $access;
    }

    /**
     * List all sites, optionally filtered by account.
     *
     * @param Request $request
     * @return View
     */
    public function index(Request $request): View
    {
        $user = $request->user();
        $canManage = $this->access->isAdmin($user);

        $query = $this->access->siteQuery($user)->with(['account']);

        if ($canManage) {
            $query->with(['campaigns', 'articles']);
        }

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
        $accounts = $this->access->accountQuery($user)->orderBy('name')->get();

        return view('app-publish::publishing.sites.index', [
            'sites' => $sites,
            'accounts' => $accounts,
            'canManage' => $canManage,
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
        $hostingAccounts = HostingAccount::orderBy('domain')->get(['id', 'username', 'domain', 'whm_server_id']);

        return view('app-publish::publishing.sites.create', [
            'accounts' => $accounts,
            'hostingAccounts' => $hostingAccounts,
            'preselected_account_id' => $request->input('account_id'),
        ]);
    }

    /**
     * AJAX: Scan a cPanel account for WordPress installs via WP Toolkit.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function scanInstalls(Request $request): JsonResponse
    {
        $request->validate(['hosting_account_id' => 'required|integer']);

        $account = HostingAccount::with('whmServer')->findOrFail($request->input('hosting_account_id'));

        if (!$account->whmServer) {
            return response()->json(['success' => false, 'message' => 'No WHM server linked to this account.', 'installs' => []]);
        }

        try {
            $result = $this->wptoolkit->getInstallsForAccount($account->whmServer, $account->username);

            if ($result['success'] && !empty($result['installs'])) {
                $installs = array_map(function ($install) use ($account) {
                    $install['hosting_account_id'] = $account->id;
                    $install['cpanel_user'] = $account->username;
                    $install['cpanel_domain'] = $account->domain;
                    return $install;
                }, $result['installs']);

                return response()->json(['success' => true, 'message' => count($installs) . ' install(s) found.', 'installs' => $installs]);
            }

            return response()->json(['success' => true, 'message' => 'No WordPress installs found.', 'installs' => []]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage(), 'installs' => []]);
        }
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
        $user = auth()->user();
        $canManage = $this->access->isAdmin($user);

        $query = $this->access->siteQuery($user)->with(['account']);

        if ($canManage) {
            $query->with(['campaigns', 'articles']);
        }

        $site = $query->findOrFail($id);

        return view('app-publish::publishing.sites.show', [
            'site' => $site,
            'canManage' => $canManage,
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

        return view('app-publish::publishing.sites.edit', [
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
            return response()->json(['success' => false, 'message' => 'Server or install ID not configured.', 'authors' => []]);
        }

        // Single SSH session: test write + get authors
        $result = $this->wptoolkit->wpCliTestWriteAccess($resolved['server'], $site->wordpress_install_id);

        $authors = [];
        if ($result['success']) {
            $authorsResult = $this->wptoolkit->wpCliListAdminUsers($resolved['server'], (int) $site->wordpress_install_id);
            $authors = $authorsResult['success'] ? ($authorsResult['authors'] ?? []) : [];
        }

        $site->update([
            'status' => $result['success'] ? 'connected' : 'error',
            'last_connected_at' => $result['success'] ? now() : $site->last_connected_at,
            'last_error' => $result['success'] ? null : $result['message'],
        ]);

        hexaLog('publish', 'site_test_write', ($result['success'] ? 'Write test passed' : 'Write test failed') . " for \"{$site->name}\" ({$site->url})", [
            'site_id' => $site->id,
            'site_name' => $site->name,
            'site_url' => $site->url,
            'success' => $result['success'],
            'authors_count' => count($authors),
        ]);

        $result['authors'] = $authors;
        $result['default_author'] = $site->default_author;
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

        $result = $this->wptoolkit->wpCliListAdminUsers($resolved['server'], (int) $site->wordpress_install_id);
        $result['default_author'] = $site->default_author;

        return response()->json($result);
    }

    /**
     * Get WordPress categories for a site via wp-cli.
     */
    public function getCategories(Request $request, int $id): JsonResponse
    {
        $site = PublishSite::findOrFail($id);
        $resolved = $this->resolveServer($site);
        if (!$resolved['server'] || !$site->wordpress_install_id) {
            return response()->json(['success' => false, 'categories' => [], 'message' => 'Server not configured.']);
        }

        $taxonomy = trim((string) $request->query('taxonomy', 'category')) ?: 'category';
        $force = $request->boolean('force');

        if ($taxonomy !== 'publication') {
            return response()->json(
                $this->wptoolkit->wpCliListCategories($resolved['server'], (int) $site->wordpress_install_id)
            );
        }

        $cacheKey = sprintf('publish:site:%d:taxonomy:%s:v1', $site->id, $taxonomy);
        if (!$force && ($cached = Cache::get($cacheKey))) {
            return response()->json([
                ...$cached,
                'cache' => [
                    'cached' => true,
                    'cached_at' => $cached['fetched_at'] ?? null,
                    'age_seconds' => $this->cacheAgeSeconds($cached['fetched_at'] ?? null),
                    'age_human' => $this->cacheAgeHuman($cached['fetched_at'] ?? null),
                ],
            ]);
        }

        $result = $this->wptoolkit->wpCliListTaxonomyTerms($resolved['server'], (int) $site->wordpress_install_id, $taxonomy);
        if (!($result['success'] ?? false)) {
            return response()->json($result);
        }

        $payload = [
            'success' => true,
            'categories' => $this->flattenPublicationTerms($result['terms'] ?? []),
            'taxonomy' => $taxonomy,
            'taxonomy_label' => 'Publications',
            'hierarchical' => true,
            'message' => $result['message'] ?? 'Publication terms loaded.',
            'fetched_at' => now()->toIso8601String(),
        ];

        Cache::put($cacheKey, $payload, now()->addHours(6));

        return response()->json([
            ...$payload,
            'cache' => [
                'cached' => false,
                'cached_at' => $payload['fetched_at'],
                'age_seconds' => 0,
                'age_human' => 'just now',
            ],
        ]);
    }

    /**
     * @param array<int, array<string, mixed>> $terms
     * @return array<int, array<string, mixed>>
     */
    private function flattenPublicationTerms(array $terms): array
    {
        $rows = [];
        $children = [];

        foreach ($terms as $term) {
            $parent = (int) ($term['parent'] ?? 0);
            $children[$parent][] = [
                'id' => (int) ($term['id'] ?? $term['term_id'] ?? 0),
                'parent' => $parent,
                'name' => (string) ($term['name'] ?? ''),
                'slug' => (string) ($term['slug'] ?? ''),
                'count' => (int) ($term['count'] ?? 0),
            ];
        }

        $walk = function (int $parentId = 0, int $depth = 0) use (&$walk, &$rows, $children): void {
            foreach ($children[$parentId] ?? [] as $term) {
                $rows[] = [
                    ...$term,
                    'depth' => $depth,
                    'label' => str_repeat('— ', $depth) . $term['name'],
                    'is_parent' => !empty($children[$term['id']] ?? []),
                ];
                $walk($term['id'], $depth + 1);
            }
        };

        $walk();

        return $rows;
    }

    private function cacheAgeSeconds(?string $fetchedAt): ?int
    {
        if (!$fetchedAt) {
            return null;
        }

        return max(0, now()->diffInSeconds($fetchedAt));
    }

    private function cacheAgeHuman(?string $fetchedAt): string
    {
        if (!$fetchedAt) {
            return 'unknown';
        }

        return now()->diffForHumans($fetchedAt, ['parts' => 2, 'short' => true]);
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
