<?php

namespace hexa_app_publish\Publishing\Schedule\Feeds;

use hexa_app_publish\Publishing\Sites\Models\PublishSite;
use hexa_package_calendar\Calendar\Feeds\Contracts\CalendarFeedProviderContract;
use hexa_package_calendar\Calendar\Feeds\Data\CalendarFeedRequest;
use Illuminate\Support\Facades\Http;

class PublishScheduleCalendarFeed implements CalendarFeedProviderContract
{
    /**
     * @return array{events: array<int, array<string, mixed>>, meta: array<string, mixed>}
     */
    public function fetch(CalendarFeedRequest $request): array
    {
        $sites = PublishSite::query()
            ->whereNotNull('url')
            ->orderBy('name')
            ->get();

        $filteredSiteId = (int) $request->filter('site_id', 0);
        if ($filteredSiteId > 0) {
            $sites = $sites->where('id', $filteredSiteId)->values();
        }

        $events = [];
        $errors = [];
        $start = $request->startAt();
        $end = $request->endAt();

        foreach ($sites as $site) {
            try {
                if (!$site->username || !$site->app_password) {
                    $errors[] = 'Skipping ' . $site->name . ': missing WordPress credentials.';
                    continue;
                }

                $response = Http::withHeaders([
                    'Authorization' => 'Basic ' . base64_encode($site->username . ':' . $site->app_password),
                ])->timeout(15)->get(rtrim($site->url, '/') . '/wp-json/wp/v2/posts', array_filter([
                    'status' => 'future',
                    'per_page' => 100,
                    'orderby' => 'date',
                    'order' => 'asc',
                    '_fields' => 'id,date,status,link,title',
                    'after' => $start?->toIso8601String(),
                    'before' => $end?->toIso8601String(),
                ], static fn ($value) => $value !== null && $value !== ''));

                if (!$response->successful()) {
                    $errors[] = 'Failed ' . $site->name . ': HTTP ' . $response->status();
                    continue;
                }

                foreach ((array) $response->json() as $post) {
                    $postId = (int) ($post['id'] ?? 0);
                    $scheduledAt = isset($post['date']) ? (string) $post['date'] : null;
                    if ($postId === 0 || $scheduledAt === null) {
                        continue;
                    }

                    $events[] = [
                        'id' => $site->id . '-' . $postId,
                        'title' => html_entity_decode(strip_tags((string) ($post['title']['rendered'] ?? 'Untitled'))),
                        'start' => $scheduledAt,
                        'url' => isset($post['link']) ? (string) $post['link'] : null,
                        'status' => isset($post['status']) ? (string) $post['status'] : 'future',
                        'allDay' => false,
                        'color' => '#3b82f6',
                        'source' => $site->name,
                        'meta' => [
                            'site_name' => $site->name,
                            'site_id' => $site->id,
                            'wp_post_id' => $postId,
                        ],
                    ];
                }
            } catch (\Throwable $e) {
                $errors[] = 'Failed ' . $site->name . ': ' . $e->getMessage();
            }
        }

        return [
            'events' => $events,
            'meta' => [
                'site_count' => $sites->count(),
                'error_count' => count($errors),
                'errors' => $errors,
            ],
        ];
    }
}
