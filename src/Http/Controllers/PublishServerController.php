<?php

namespace hexa_app_publish\Http\Controllers;

use hexa_core\Http\Controllers\Controller;
use hexa_package_whm\Models\WhmServer;
use hexa_package_whm\Services\WhmService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
        $result = $this->whm->refreshServerStats($server);

        return response()->json($result);
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
