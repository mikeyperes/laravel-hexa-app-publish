<?php

namespace hexa_app_publish\Publishing\Schedule\Http\Controllers;

use hexa_core\Http\Controllers\Controller;
use hexa_package_calendar\Calendar\Feeds\Data\CalendarFeedRequest;
use hexa_package_calendar\Calendar\Feeds\Services\CalendarFeedService;
use hexa_package_calendar\Calendar\UI\Services\CalendarPageBuilderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
    public function index(CalendarPageBuilderService $pages): View
    {
        return view('app-publish::publishing.schedule.index', $pages->build('publish.schedule', [
            'page_title' => 'Schedule',
            'page_header' => 'Schedule - All Scheduled Posts',
            'feed_url' => route('publish.schedule.fetch'),
            'feed_method' => 'POST',
            'fetch_label' => 'Fetch Scheduled Posts',
            'info_banner_html' => 'Connect WordPress sites in the <a href="' . route('publish.sites.index') . '" class="underline font-medium">Sites</a> section to view scheduled posts here.',
        ]));
    }

    /**
     * Fetch scheduled posts from WordPress REST API for connected sites.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function fetchScheduled(Request $request, CalendarFeedService $feeds): JsonResponse
    {
        $payload = $feeds->fetch('publish.schedule', CalendarFeedRequest::fromHttpRequest('publish.schedule', $request));

        return response()->json($payload, $payload['success'] ? 200 : 422);
    }
}
