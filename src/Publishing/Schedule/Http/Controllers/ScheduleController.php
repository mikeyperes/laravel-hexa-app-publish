<?php

namespace hexa_app_publish\Publishing\Schedule\Http\Controllers;

use hexa_core\Http\Controllers\Controller;
use hexa_app_publish\Models\PublishSite;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\View\View;

/**
 * PublishScheduleController — calendar view and scheduled post fetching.
 */
class ScheduleController extends Controller
{
    /**
     * Show the schedule page with calendar and list view.
     *
     * @return View
     */
    public function index(): View
    {
        $sites = PublishSite::orderBy('name')->get();

        return view('app-publish::publishing.schedule.index', [
            'sites' => $sites,
        ]);
    }

    /**
     * Fetch scheduled posts from WordPress REST API for connected sites.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function fetchScheduled(Request $request): JsonResponse
    {
        $sites = PublishSite::whereNotNull('url')->get();
        $scheduled = [];

        foreach ($sites as $site) {
            try {
                $apiUrl = rtrim($site->url, '/') . '/wp-json/wp/v2/posts?status=future&per_page=50';

                $response = Http::withHeaders([
                    'Authorization' => 'Basic ' . base64_encode($site->username . ':' . $site->app_password),
                ])->timeout(10)->get($apiUrl);

                if ($response->successful()) {
                    $posts = $response->json();
                    foreach ($posts as $post) {
                        $scheduled[] = [
                            'site_name'  => $site->name,
                            'site_id'    => $site->id,
                            'title'      => $post['title']['rendered'] ?? 'Untitled',
                            'date'       => $post['date'] ?? null,
                            'status'     => $post['status'] ?? 'future',
                            'link'       => $post['link'] ?? '#',
                            'wp_post_id' => $post['id'] ?? null,
                        ];
                    }
                }
            } catch (\Exception $e) {
                // Skip sites that fail to connect
                continue;
            }
        }

        return response()->json([
            'success'   => true,
            'scheduled' => $scheduled,
            'count'     => count($scheduled),
        ]);
    }
}
