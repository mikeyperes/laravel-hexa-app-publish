<?php

namespace hexa_app_publish\Publishing\Sites\Http\Controllers;

use hexa_core\Http\Controllers\Controller;
use hexa_core\Services\GenericService;
use hexa_app_publish\Publishing\Access\Services\PublishAccessService;
use hexa_app_publish\Publishing\Accounts\Models\PublishAccount;
use hexa_app_publish\Publishing\Sites\Models\PublishSite;
use hexa_app_publish\Services\PublishService;
use hexa_app_publish\Publishing\Sites\Services\PublishSiteWordPressTargetFactory;
use hexa_package_wordpress\Services\WordPressManagerService;
use hexa_package_whm\Models\HostingAccount;
use hexa_package_whm\Models\WhmServer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class SiteController extends Controller
{
    protected GenericService $generic;
    protected PublishService $publishService;
    protected WordPressManagerService $wordpress;
    protected PublishSiteWordPressTargetFactory $targetFactory;
    protected PublishAccessService $access;

    public function __construct(GenericService $generic, PublishService $publishService, WordPressManagerService $wordpress, PublishSiteWordPressTargetFactory $targetFactory, PublishAccessService $access)
    {
        $this->generic = $generic;
        $this->publishService = $publishService;
        $this->wordpress = $wordpress;
        $this->targetFactory = $targetFactory;
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
            $result = app(\hexa_package_wordpress\Services\WordPressManagerService::class)->discoverInstallsForAccount($account->whmServer, $account->username);

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

            $result = $this->wordpress->testConnection($this->targetFactory->fromSite($site));

            $site->update([
                'status' => $result['success'] ? 'connected' : 'error',
                'last_connected_at' => $result['success'] ? now() : $site->last_connected_at,
                'last_error' => $result['success'] ? null : $result['message'],
            ]);

            hexaLog('publish', 'site_test', "Site connection tested: {$site->name} ({$site->url}) — " . ($result['success'] ? 'connected' : 'failed: ' . $result['message']));

            return response()->json($result);
        }


        $target = $this->targetFactory->fromSite($site);
        $result = $this->wordpress->testConnection($target);

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
        $target = $this->targetFactory->fromSite($site);
        $result = $this->wordpress->testWriteAccess($target);

        $authors = [];
        $authorsResult = null;
        if ($result["success"] ?? false) {
            $authorsResult = $this->wordpress->listAuthors($target, true);
            $authors = ($authorsResult["success"] ?? false) ? ($authorsResult["authors"] ?? []) : [];
        }

        $site->update([
            "status" => ($result["success"] ?? false) ? "connected" : "error",
            "last_connected_at" => ($result["success"] ?? false) ? now() : $site->last_connected_at,
            "last_error" => ($result["success"] ?? false) ? null : ($result["message"] ?? null),
        ]);

        hexaLog("publish", "site_test_write", (($result["success"] ?? false) ? "Write test passed" : "Write test failed") . " for \"" . $site->name . "\" (" . $site->url . ")", [
            "site_id" => $site->id,
            "site_name" => $site->name,
            "site_url" => $site->url,
            "success" => (bool) ($result["success"] ?? false),
            "authors_count" => count($authors),
        ]);

        $result["authors"] = $authors;
        $result["default_author"] = $site->default_author;
        $result["author_cast"] = array_values(array_filter((array) ($site->author_cast ?? []), fn ($value) => filled($value)));
        $result["last_connected_at"] = $site->last_connected_at?->toIso8601String();
        $result["cache_hit"] = $authorsResult["cache_hit"] ?? null;
        $result["cached_at"] = $authorsResult["cached_at"] ?? null;
        $result["expires_at"] = $authorsResult["expires_at"] ?? null;
        return response()->json($result);
    }

    /**
     * Get WordPress admin users for author selection via wp-cli.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function getAuthors(Request $request, int $id): JsonResponse
    {
        $site = PublishSite::findOrFail($id);
        $target = app(\hexa_app_publish\Publishing\Sites\Services\PublishSiteWordPressTargetFactory::class)->fromSite($site);
        $result = app(\hexa_package_wordpress\Services\WordPressManagerService::class)->listAuthors($target, $request->boolean("force"));
        $result["default_author"] = $site->default_author;
        $result["author_cast"] = array_values(array_filter((array) ($site->author_cast ?? []), fn ($value) => filled($value)));
        $result["last_connected_at"] = $site->last_connected_at?->toIso8601String();

        return response()->json($result);
    }

    /**
     * Get WordPress categories for a site via wp-cli.
     */
    public function getCategories(Request $request, int $id): JsonResponse
    {
        $site = PublishSite::findOrFail($id);
        $target = app(\hexa_app_publish\Publishing\Sites\Services\PublishSiteWordPressTargetFactory::class)->fromSite($site);
        $taxonomy = trim((string) $request->query("taxonomy", "category")) ?: "category";
        $force = $request->boolean("force");

        if ($taxonomy === "publication") {
            $taxonomyInfo = ["success" => true, "taxonomy" => "publication", "label" => "Publications", "hierarchical" => true];
            $resolvedTaxonomy = "publication";
        } else {
            $taxonomyInfo = app(\hexa_package_wordpress\Services\WordPressManagerService::class)->resolvePreferredTaxonomy($target, [$taxonomy, "category"]);
            if (!($taxonomyInfo["success"] ?? false)) {
                return response()->json([
                    "success" => false,
                    "categories" => [],
                    "message" => $taxonomyInfo["message"] ?? "Failed to resolve syndication taxonomy.",
                ], 500);
            }
            $resolvedTaxonomy = (string) ($taxonomyInfo["taxonomy"] ?? $taxonomy);
        }

        $cacheKey = sprintf("publish:site:%d:taxonomy:%s:v3", $site->id, $resolvedTaxonomy);
        if (!$force && ($cached = Cache::get($cacheKey))) {
            return response()->json([
                ...$cached,
                "cache" => [
                    "cached" => true,
                    "cached_at" => $cached["fetched_at"] ?? null,
                    "age_seconds" => $this->cacheAgeSeconds($cached["fetched_at"] ?? null),
                    "age_human" => $this->cacheAgeHuman($cached["fetched_at"] ?? null),
                ],
            ]);
        }

        $result = app(\hexa_package_wordpress\Services\WordPressManagerService::class)->listTerms($target, $resolvedTaxonomy, $force);
        if (!($result["success"] ?? false)) {
            return response()->json([
                "success" => false,
                "categories" => [],
                "message" => $result["message"] ?? "Failed to load taxonomy terms.",
            ], 500);
        }

        $payload = [
            "success" => true,
            "categories" => $this->flattenPublicationTerms($result["terms"] ?? []),
            "taxonomy" => $resolvedTaxonomy,
            "taxonomy_requested" => $taxonomy,
            "taxonomy_label" => (string) ($taxonomyInfo["label"] ?? ucfirst(str_replace(["-", "_"], " ", $resolvedTaxonomy))),
            "hierarchical" => (bool) ($taxonomyInfo["hierarchical"] ?? true),
            "message" => $result["message"] ?? "Taxonomy loaded.",
            "fetched_at" => now()->toIso8601String(),
        ];

        Cache::put($cacheKey, $payload, now()->addHours(6));

        return response()->json([
            ...$payload,
            "cache" => [
                "cached" => false,
                "cached_at" => $payload["fetched_at"],
                "age_seconds" => 0,
                "age_human" => "just now",
            ],
        ]);
    }

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

        return \Illuminate\Support\Carbon::parse($fetchedAt)->diffForHumans(now(), ['parts' => 2, 'short' => true, 'syntax' => \Carbon\CarbonInterface::DIFF_RELATIVE_TO_NOW]);
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
     * Set the site author pool used for randomized campaign attribution.
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function setAuthorCast(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'authors' => 'nullable|array',
            'authors.*' => 'string|max:255',
        ]);

        $authors = collect((array) ($validated['authors'] ?? []))
            ->map(fn ($author) => trim((string) $author))
            ->filter()
            ->unique(fn ($author) => mb_strtolower($author))
            ->values()
            ->all();

        $site = PublishSite::findOrFail($id);
        $site->update(['author_cast' => $authors]);

        hexaLog('publish', 'site_author_cast_set', "Author pool saved for {$site->name}: " . count($authors) . ' author(s)', [
            'site_id' => $site->id,
            'authors' => $authors,
        ]);

        return response()->json([
            'success' => true,
            'message' => count($authors) > 0
                ? ('Saved ' . count($authors) . ' author' . (count($authors) === 1 ? '' : 's') . ' to the campaign author pool.')
                : 'Cleared the campaign author pool.',
            'author_cast' => $authors,
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

    /**
     * Direct DB recovery: pull publication taxonomy terms from
     * wp_term_taxonomy via wpCliEval. Bypasses the WP-CLI
     * taxonomy_exists/get_terms path which fails when the publication slug
     * collides with a custom post type that shadows the taxonomy in CLI
     * bootstrap.
     *
     * @return array<int, array<string, mixed>>
     */
    private function fetchPublicationTermsViaDb($server, int $installId): array
    {
        if (!$server || $installId <= 0) {
            return [];
        }

        $result = app(\hexa_package_wordpress\Services\WordPressManagerService::class)->listTerms([
            "mode" => "wptoolkit",
            "server" => $server,
            "install_id" => $installId,
        ], "publication");

        return ($result["success"] ?? false) ? (array) ($result["terms"] ?? []) : [];
    }

}
