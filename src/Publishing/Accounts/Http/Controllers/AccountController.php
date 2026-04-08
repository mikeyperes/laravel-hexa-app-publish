<?php

namespace hexa_app_publish\Publishing\Accounts\Http\Controllers;

use hexa_core\Http\Controllers\Controller;
use hexa_core\Models\User;
use hexa_package_whm\Models\HostingAccount;
use hexa_app_publish\Publishing\Sites\Models\PublishSite;
use hexa_app_publish\Publishing\Campaigns\Models\PublishCampaign;
use hexa_app_publish\Publishing\Articles\Models\PublishArticle;
use hexa_app_publish\Publishing\Templates\Models\PublishTemplate;
use hexa_app_publish\Publishing\Presets\Models\PublishPreset;
use hexa_app_publish\Publishing\Articles\Models\PublishBookmark;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * PublishAccountController — user publishing profiles.
 * Uses WHM package's HostingAccount::users() relationship for cPanel account linking.
 */
class AccountController extends Controller
{
    /**
     * List all users with publishing stats.
     *
     * @param Request $request
     * @return View
     */
    public function index(Request $request): View
    {
        $query = User::query();

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $users = $query->orderBy('name')->get()->map(function ($user) {
            $user->sites_count = PublishSite::where('user_id', $user->id)->count();
            $user->campaigns_count = PublishCampaign::where('user_id', $user->id)->count();
            $user->articles_count = PublishArticle::where('user_id', $user->id)->count();
            $user->cpanel_count = HostingAccount::whereHas('users', fn($q) => $q->where('users.id', $user->id))->count();
            return $user;
        });

        return view('app-publish::publishing.accounts.index', [
            'users' => $users,
        ]);
    }

    /**
     * Show a user's publishing profile.
     *
     * @param int $id
     * @return View
     */
    public function show(int $id): View
    {
        $user = User::findOrFail($id);

        // Get attached cPanel accounts via WHM package relationship
        $attachedAccounts = HostingAccount::with('whmServer')
            ->whereHas('users', fn($q) => $q->where('users.id', $user->id))
            ->get()
            ->map(function ($acct) {
                $acct->is_reseller = $acct->isReseller();
                $acct->child_count = $acct->is_reseller ? $acct->getChildAccountCount() : 0;
                return $acct;
            });

        $attachedIds = $attachedAccounts->pluck('id')->toArray();

        // Available accounts (not attached, active)
        $availableAccounts = HostingAccount::with('whmServer')
            ->whereNotIn('id', $attachedIds)
            ->where('status', 'active')
            ->orderBy('domain')
            ->get()
            ->map(function ($acct) {
                $acct->is_reseller = $acct->isReseller();
                $acct->child_count = $acct->is_reseller ? $acct->getChildAccountCount() : 0;
                return $acct;
            });

        $sites = PublishSite::where('user_id', $user->id)->get();
        $campaigns = PublishCampaign::where('user_id', $user->id)->with('site')->get();
        $templates = PublishTemplate::where('user_id', $user->id)->get();
        $presets = PublishPreset::where('user_id', $user->id)->get();
        $drafts = PublishArticle::where('user_id', $user->id)->where('status', 'draft')->orderByDesc('updated_at')->limit(20)->get();
        $bookmarks = PublishBookmark::where('user_id', $user->id)->orderByDesc('created_at')->limit(20)->get();

        $articleStats = [
            'total' => PublishArticle::where('user_id', $user->id)->count(),
            'published' => PublishArticle::where('user_id', $user->id)->where('status', 'published')->count(),
            'completed' => PublishArticle::where('user_id', $user->id)->where('status', 'completed')->count(),
            'review' => PublishArticle::where('user_id', $user->id)->where('status', 'review')->count(),
            'drafting' => PublishArticle::where('user_id', $user->id)->whereIn('status', ['sourcing', 'drafting', 'spinning'])->count(),
        ];

        $defaultPreset = $presets->where('is_default', true)->first();
        $defaultSiteId = $defaultPreset ? $defaultPreset->default_site_id : null;

        // Pre-map for Alpine JS arrays (arrow functions in @json break Blade)
        $attachedAccountsJson = $attachedAccounts->map(function ($a) {
            return ['id' => $a->id, 'domain' => $a->domain, 'username' => $a->username, 'hostname' => $a->whmServer->hostname ?? ''];
        })->values();

        $availableAccountsJson = $availableAccounts->map(function ($a) {
            return ['id' => $a->id, 'domain' => $a->domain, 'username' => $a->username, 'hostname' => $a->whmServer->hostname ?? ''];
        })->values();

        $sitesJson = $sites->map(function ($s) {
            return ['id' => $s->id, 'name' => $s->name, 'url' => $s->url, 'default_author' => $s->default_author];
        })->values();

        return view('app-publish::publishing.accounts.show', [
            'user' => $user,
            'attachedAccounts' => $attachedAccounts,
            'availableAccounts' => $availableAccounts,
            'sites' => $sites,
            'campaigns' => $campaigns,
            'templates' => $templates,
            'presets' => $presets,
            'drafts' => $drafts,
            'bookmarks' => $bookmarks,
            'articleStats' => $articleStats,
            'defaultSiteId' => $defaultSiteId,
            'attachedAccountsJson' => $attachedAccountsJson,
            'availableAccountsJson' => $availableAccountsJson,
            'sitesJson' => $sitesJson,
        ]);
    }

    /**
     * AJAX: Update the user's default website by setting it on their default preset.
     *
     * @param Request $request
     * @param int $id User ID
     * @return JsonResponse
     */
    public function updateDefaultSite(Request $request, int $id): JsonResponse
    {
        $user = User::findOrFail($id);
        $siteId = $request->input('default_site_id') ?: null;

        $preset = PublishPreset::where('user_id', $user->id)->where('is_default', true)->first();

        if (!$preset) {
            $preset = PublishPreset::create([
                'user_id' => $user->id,
                'name' => $user->name . ' — Default',
                'status' => 'active',
                'is_default' => true,
                'default_site_id' => $siteId,
            ]);
        } else {
            $preset->update(['default_site_id' => $siteId]);
        }

        return response()->json(['success' => true, 'message' => 'Default website updated.']);
    }

    /**
     * AJAX: Attach cPanel accounts to a user.
     *
     * @param Request $request
     * @param int $id User ID
     * @return JsonResponse
     */
    public function attachAccount(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'hosting_account_ids'   => 'required|array',
            'hosting_account_ids.*' => 'integer',
            'include_children'      => 'nullable|boolean',
        ]);

        $user = User::findOrFail($id);
        $requestedIds = collect($request->input('hosting_account_ids'));
        $includeChildren = $request->boolean('include_children', true);

        // Expand reseller accounts
        $allIds = $requestedIds;
        if ($includeChildren) {
            foreach ($requestedIds as $accountId) {
                $account = HostingAccount::find($accountId);
                if ($account && $account->isReseller()) {
                    $allIds = $allIds->merge($account->getChildAccounts()->pluck('id'));
                }
            }
        }
        $allIds = $allIds->unique()->values();

        // Get currently attached
        $currentIds = HostingAccount::whereHas('users', fn($q) => $q->where('users.id', $user->id))
            ->pluck('id');

        $newIds = $allIds->diff($currentIds);

        if ($newIds->isEmpty()) {
            return response()->json(['success' => false, 'message' => 'All selected accounts are already attached.']);
        }

        // Attach using the relationship
        foreach ($newIds as $aid) {
            $acct = HostingAccount::find($aid);
            if ($acct) {
                $acct->users()->attach($user->id);
            }
        }

        $attached = HostingAccount::with('whmServer')->whereIn('id', $newIds)->get()->map(fn($a) => [
            'id' => $a->id,
            'domain' => $a->domain,
            'username' => $a->username,
            'hostname' => $a->whmServer->hostname ?? '',
        ]);

        return response()->json([
            'success' => true,
            'message' => $newIds->count() . ' cPanel account(s) attached.',
            'attached' => $attached,
        ]);
    }

    /**
     * AJAX: Detach a cPanel account from a user.
     *
     * @param int $id User ID
     * @param int $accountId Hosting account ID
     * @return JsonResponse
     */
    public function detachAccount(int $id, int $accountId): JsonResponse
    {
        $account = HostingAccount::find($accountId);
        if ($account) {
            $account->users()->detach($id);
        }

        return response()->json([
            'success' => true,
            'message' => 'cPanel account detached.',
        ]);
    }

    /**
     * AJAX: Add a WordPress install as a publish site for a user.
     *
     * @param Request $request
     * @param int $id User ID
     * @return JsonResponse
     */
    public function addSite(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'url'  => 'required|string|max:500',
            'name' => 'required|string|max:255',
            'hosting_account_id'    => 'nullable|integer',
            'wordpress_install_id'  => 'nullable|integer',
            'path' => 'nullable|string|max:500',
        ]);

        $user = User::findOrFail($id);

        $exists = PublishSite::where('user_id', $user->id)
            ->where('url', $request->input('url'))
            ->exists();

        if ($exists) {
            return response()->json(['success' => false, 'message' => 'This site is already added for this user.']);
        }

        $site = PublishSite::create([
            'user_id'              => $user->id,
            'name'                 => $request->input('name'),
            'url'                  => $request->input('url'),
            'connection_type'      => 'wptoolkit',
            'hosting_account_id'   => $request->input('hosting_account_id'),
            'wordpress_install_id' => $request->input('wordpress_install_id'),
            'status'               => 'connected',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Site "' . $site->name . '" added.',
            'site' => ['id' => $site->id, 'name' => $site->name, 'url' => $site->url],
        ]);
    }

    /**
     * AJAX: Remove a WordPress site from a user.
     *
     * @param int $id User ID
     * @param int $siteId Site ID
     * @return JsonResponse
     */
    public function removeSite(int $id, int $siteId): JsonResponse
    {
        $site = PublishSite::where('id', $siteId)->where('user_id', $id)->first();

        if (!$site) {
            return response()->json(['success' => false, 'message' => 'Site not found.']);
        }

        $name = $site->name;
        $url = $site->url;
        $site->delete();

        hexaLog('publish', 'site_removed', "Removed site \"{$name}\" ({$url}) from user #{$id}", [
            'user_id' => $id,
            'site_id' => $siteId,
            'site_name' => $name,
            'site_url' => $url,
        ]);

        return response()->json(['success' => true, 'message' => "Site \"{$name}\" removed."]);
    }

    /**
     * AJAX: Scan a single cPanel account for WordPress installs via WP Toolkit.
     * Used by the activity log to scan accounts one-by-one with live feedback.
     *
     * @param Request $request
     * @param int $id User ID
     * @return JsonResponse
     */
    public function scanWordPressSingle(Request $request, int $id): JsonResponse
    {
        $request->validate(['hosting_account_id' => 'required|integer']);

        $account = HostingAccount::with('whmServer')->findOrFail($request->input('hosting_account_id'));
        $wpToolkit = app(\hexa_package_wptoolkit\Services\WpToolkitService::class);

        if (!$account->whmServer) {
            return response()->json([
                'success' => false,
                'message' => $account->username . ': No WHM server linked.',
                'installs' => [],
            ]);
        }

        try {
            $result = $wpToolkit->getInstallsForAccount($account->whmServer, $account->username);

            if ($result['success'] && !empty($result['installs'])) {
                $installs = [];
                foreach ($result['installs'] as $install) {
                    $install['cpanel_user'] = $account->username;
                    $install['cpanel_domain'] = $account->domain;
                    $install['server_name'] = $account->whmServer->name ?? $account->whmServer->hostname;
                    $install['hosting_account_id'] = $account->id;
                    $installs[] = $install;
                }

                return response()->json([
                    'success' => true,
                    'message' => $account->username . ': ' . count($installs) . ' install(s) found.',
                    'installs' => $installs,
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => $account->username . ': No installs found.',
                'installs' => [],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $account->username . ': ' . $e->getMessage(),
                'installs' => [],
            ]);
        }
    }

    /**
     * AJAX: Scan WordPress installs for a user's attached cPanel accounts via WP Toolkit.
     *
     * @param int $id User ID
     * @return JsonResponse
     */
    public function scanWordPress(int $id): JsonResponse
    {
        $user = User::findOrFail($id);

        $accounts = HostingAccount::with('whmServer')
            ->whereHas('users', fn($q) => $q->where('users.id', $user->id))
            ->get();

        if ($accounts->isEmpty()) {
            return response()->json(['success' => false, 'message' => 'No cPanel accounts attached.', 'installs' => []]);
        }

        $allInstalls = [];
        $errors = [];

        $wpToolkit = app(\hexa_package_wptoolkit\Services\WpToolkitService::class);

        foreach ($accounts as $account) {
            if (!$account->whmServer) {
                $errors[] = $account->username . ': No WHM server linked.';
                continue;
            }

            try {
                $result = $wpToolkit->getInstallsForAccount($account->whmServer, $account->username);

                if ($result['success'] && !empty($result['installs'])) {
                    foreach ($result['installs'] as $install) {
                        $install['cpanel_user'] = $account->username;
                        $install['cpanel_domain'] = $account->domain;
                        $install['server_name'] = $account->whmServer->name ?? $account->whmServer->hostname;
                        $install['hosting_account_id'] = $account->id;
                        $allInstalls[] = $install;
                    }
                } elseif (!$result['success']) {
                    $errors[] = $account->username . ': ' . ($result['error'] ?? 'Failed');
                }
            } catch (\Exception $e) {
                $errors[] = $account->username . ': ' . $e->getMessage();
            }
        }

        return response()->json([
            'success' => true,
            'message' => count($allInstalls) . ' WordPress install(s) found across ' . count($accounts) . ' account(s).',
            'installs' => $allInstalls,
            'errors' => $errors,
        ]);
    }
}
