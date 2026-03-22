<?php

namespace hexa_app_publish\Http\Controllers;

use hexa_core\Http\Controllers\Controller;
use hexa_core\Models\User;
use hexa_package_whm\Models\HostingAccount;
use hexa_package_whm\Models\WhmServer;
use hexa_app_publish\Models\PublishSite;
use hexa_app_publish\Models\PublishCampaign;
use hexa_app_publish\Models\PublishArticle;
use hexa_app_publish\Models\PublishTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * PublishAccountController — user publishing profiles.
 * Users are core entities. This controller manages their publishing data:
 * attached cPanel accounts, WordPress sites (via WP Toolkit), campaigns, articles.
 */
class PublishAccountController extends Controller
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
            $user->cpanel_count = DB::table('user_hosting_accounts')->where('user_id', $user->id)->count();
            return $user;
        });

        return view('app-publish::accounts.index', [
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

        // Get attached cPanel account IDs
        $attachedIds = DB::table('user_hosting_accounts')
            ->where('user_id', $user->id)
            ->pluck('hosting_account_id')
            ->toArray();

        // Get attached cPanel accounts with server info + reseller detection
        $attachedAccounts = HostingAccount::with('whmServer')
            ->whereIn('id', $attachedIds)
            ->get()
            ->map(function ($acct) {
                $acct->is_reseller = $acct->isReseller();
                $acct->child_count = $acct->is_reseller ? $acct->getChildAccountCount() : 0;
                return $acct;
            });

        // Get all available cPanel accounts (not already attached), with reseller info
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

        $articleStats = [
            'total' => PublishArticle::where('user_id', $user->id)->count(),
            'published' => PublishArticle::where('user_id', $user->id)->where('status', 'published')->count(),
            'completed' => PublishArticle::where('user_id', $user->id)->where('status', 'completed')->count(),
            'review' => PublishArticle::where('user_id', $user->id)->where('status', 'review')->count(),
            'drafting' => PublishArticle::where('user_id', $user->id)->whereIn('status', ['sourcing', 'drafting', 'spinning'])->count(),
        ];

        return view('app-publish::accounts.show', [
            'user' => $user,
            'attachedAccounts' => $attachedAccounts,
            'availableAccounts' => $availableAccounts,
            'sites' => $sites,
            'campaigns' => $campaigns,
            'templates' => $templates,
            'articleStats' => $articleStats,
        ]);
    }

    /**
     * AJAX: Attach a cPanel account to a user.
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
        $requestedIds = $request->input('hosting_account_ids');
        $includeChildren = $request->boolean('include_children', true);

        // Expand reseller accounts to include child accounts
        $allIds = collect($requestedIds);
        if ($includeChildren) {
            foreach ($requestedIds as $accountId) {
                $account = HostingAccount::find($accountId);
                if ($account && $account->isReseller()) {
                    $childIds = $account->getChildAccounts()->pluck('id');
                    $allIds = $allIds->merge($childIds);
                }
            }
        }
        $allIds = $allIds->unique()->values();

        // Filter out already attached
        $alreadyAttached = DB::table('user_hosting_accounts')
            ->where('user_id', $user->id)
            ->whereIn('hosting_account_id', $allIds)
            ->pluck('hosting_account_id');

        $newIds = $allIds->diff($alreadyAttached);

        if ($newIds->isEmpty()) {
            return response()->json(['success' => false, 'message' => 'All selected accounts are already attached.']);
        }

        // Insert all new ones
        $inserts = $newIds->map(fn($aid) => [
            'user_id' => $user->id,
            'hosting_account_id' => $aid,
            'created_at' => now(),
            'updated_at' => now(),
        ])->toArray();

        DB::table('user_hosting_accounts')->insert($inserts);

        return response()->json([
            'success' => true,
            'message' => count($inserts) . ' cPanel account(s) attached.',
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
        DB::table('user_hosting_accounts')
            ->where('user_id', $id)
            ->where('hosting_account_id', $accountId)
            ->delete();

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

        // Check not already added
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
        ]);
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

        $attachedIds = DB::table('user_hosting_accounts')
            ->where('user_id', $user->id)
            ->pluck('hosting_account_id')
            ->toArray();

        if (empty($attachedIds)) {
            return response()->json(['success' => false, 'message' => 'No cPanel accounts attached.', 'installs' => []]);
        }

        $accounts = HostingAccount::with('whmServer')->whereIn('id', $attachedIds)->get();
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
