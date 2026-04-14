<?php

namespace hexa_app_publish\Publishing\Campaigns\Services;

class NewsDiscoveryOptionsService
{
    /**
     * @return array<int, string>
     */
    public function discoveryModes(): array
    {
        return array_values((array) config('hws-publish.campaign_discovery_modes', [
            'keyword',
            'local',
            'trending',
            'genre',
        ]));
    }

    /**
     * @return array<int, string>
     */
    public function finalArticleMethods(): array
    {
        return array_values((array) config('hws-publish.campaign_final_article_methods', [
            'news-search',
        ]));
    }

    /**
     * @return array<int, string>
     */
    public function newsCategories(): array
    {
        return cache()->remember('publish_news_categories', 300, function () {
            return \DB::table('lists')
                ->where('list_key', 'news_categories')
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->pluck('list_value')
                ->map(fn ($value) => (string) $value)
                ->values()
                ->all();
        });
    }
}
