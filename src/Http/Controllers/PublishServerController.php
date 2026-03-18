<?php

namespace hexa_app_publish\Http\Controllers;

use hexa_core\Http\Controllers\Controller;
use hexa_package_whm\Models\WhmServer;
use hexa_package_whm\Services\WhmService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class PublishServerController extends Controller
{
    protected WhmService $whm;

    /**
     * @param WhmService $whm
     */
    public function __construct(WhmService $whm)
    {
        $this->whm = $whm;
    }

    /**
     * List all WHM servers.
     *
     * @return View
     */
    public function index(): View
    {
        $servers = WhmServer::orderBy('name')->get();

        return view('app-publish::servers.index', [
            'servers' => $servers,
        ]);
    }

    /**
     * Test WHM API connection for a server.
     *
     * @param WhmServer $server
     * @return JsonResponse
     */
    public function test(WhmServer $server): JsonResponse
    {
        $result = $this->whm->testConnection($server);

        activity_log('publish', 'server_test', "Server test: {$server->name} ({$server->hostname}) — " . ($result['success'] ? 'success' : 'failed'));

        return response()->json($result);
    }

    /**
     * Refresh server stats (CPU, RAM, disk, load, accounts).
     *
     * @param WhmServer $server
     * @return JsonResponse
     */
    public function refreshStats(WhmServer $server): JsonResponse
    {
        try {
            $this->whm->refreshServerInfo($server);
            $server->refresh();

            return response()->json([
                'success' => true,
                'message' => 'Server stats refreshed.',
                'stats' => [
                    'whm_account_count' => $server->whm_account_count,
                    'license_max_accounts' => $server->license_max_accounts,
                    'ram_total_kb' => $server->ram_total_kb,
                    'ram_available_kb' => $server->ram_available_kb,
                    'disk_partitions' => $server->disk_partitions,
                    'load_1' => $server->load_1,
                    'load_5' => $server->load_5,
                    'load_15' => $server->load_15,
                    'server_info_updated_at' => $server->server_info_updated_at?->toIso8601String(),
                    'refreshed_ago' => $server->server_info_updated_at?->diffForHumans() ?? 'just now',
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to refresh: ' . $e->getMessage(),
            ]);
        }
    }

    /**
     * List all cPanel accounts across all servers.
     *
     * @param Request $request
     * @return View
     */
    public function accounts(Request $request): View
    {
        $servers = WhmServer::where('is_active', true)->orderBy('name')->get();

        $query = DB::table('hosting_accounts')->orderBy('domain');

        if ($request->filled('server')) {
            $query->where('whm_server_id', $request->input('server'));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('domain', 'like', "%{$search}%")
                  ->orWhere('username', 'like', "%{$search}%")
                  ->orWhere('owner', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $accounts = $query->get();
        $accountsByServer = $accounts->groupBy('whm_server_id');

        return view('app-publish::servers.accounts', [
            'servers' => $servers,
            'accounts' => $accounts,
            'accountsByServer' => $accountsByServer,
        ]);
    }

    /**
     * Sync accounts from a WHM server using WHM API directly.
     *
     * @param WhmServer $server
     * @return JsonResponse
     */
    public function syncAccounts(WhmServer $server): JsonResponse
    {
        try {
            $result = $this->whm->listAccounts($server);

            if (!$result['success']) {
                return response()->json($result);
            }

            $apiAccounts = $result['data'] ?? [];
            $now = now();
            $synced = 0;

            foreach ($apiAccounts as $acct) {
                DB::table('hosting_accounts')->updateOrInsert(
                    ['whm_server_id' => $server->id, 'username' => $acct['user'] ?? $acct['username'] ?? ''],
                    [
                        'domain' => $acct['domain'] ?? '',
                        'owner' => $acct['owner'] ?? 'root',
                        'email' => $acct['email'] ?? null,
                        'package' => $acct['plan'] ?? $acct['package'] ?? null,
                        'status' => ($acct['suspended'] ?? false) ? 'suspended' : 'active',
                        'suspend_reason' => $acct['suspendreason'] ?? null,
                        'ip_address' => $acct['ip'] ?? null,
                        'disk_used_mb' => (int) ($acct['diskused'] ?? 0),
                        'disk_limit_mb' => ($acct['disklimit'] ?? 'unlimited') === 'unlimited' ? 0 : (int) ($acct['disklimit'] ?? 0),
                        'shell_access' => (bool) ($acct['shell'] ?? false),
                        'theme' => $acct['theme'] ?? null,
                        'updated_at' => $now,
                    ]
                );
                $synced++;
            }

            $server->update([
                'last_synced_at' => $now,
                'account_count' => $synced,
            ]);

            activity_log('publish', 'server_sync', "Server sync: {$server->name} — {$synced} accounts");

            return response()->json([
                'success' => true,
                'message' => "{$synced} accounts synced from {$server->name}.",
                'last_synced_at' => $now->toIso8601String(),
                'last_synced_ago' => 'just now',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Sync failed: ' . $e->getMessage(),
            ]);
        }
    }

    /**
     * Show server info page.
     *
     * @param WhmServer $server
     * @return View
     */
    public function info(WhmServer $server): View
    {
        return view('app-publish::servers.info', [
            'server' => $server,
        ]);
    }
}
