<?php

namespace hexa_app_publish\Publishing\Campaigns\Services;

use hexa_app_publish\Discovery\Sources\Services\SourceDiscoveryService;
use hexa_app_publish\Publishing\Campaigns\Models\PublishCampaign;

class CampaignDiscoveryService
{
    public function __construct(protected SourceDiscoveryService $sourceDiscovery)
    {
    }

    /**
     * @param array<string, mixed> $resolved
     * @return array{query: string, selected_term: ?string, category: ?string, source_mode: string}
     */
    public function buildSearchContext(PublishCampaign $campaign, array $resolved): array
    {
        $terms = array_values((array) ($resolved['search_terms'] ?? []));
        $selectedTerm = !empty($terms) ? $terms[array_rand($terms)] : null;
        $sourceMode = (string) ($resolved['source_method'] ?? 'keyword');
        $category = null;
        $query = trim((string) ($selectedTerm ?: ($resolved['topic'] ?? '')));

        if ($sourceMode === 'local') {
            $localPreference = trim((string) ($resolved['local_preference'] ?? ''));
            $query = trim($query ?: $campaign->topic ?: 'local news');
            if ($localPreference !== '' && stripos($query, $localPreference) === false) {
                $query = trim($query . ' ' . $localPreference);
            }
        }

        if ($sourceMode === 'trending') {
            $categories = array_values((array) ($resolved['trending_categories'] ?? []));
            $category = !empty($categories) ? $categories[array_rand($categories)] : null;
        }

        if ($sourceMode === 'genre') {
            $category = trim((string) ($resolved['genre'] ?? '')) ?: null;
        }

        if ($sourceMode === 'keyword' && $query === '') {
            $query = trim((string) ($campaign->topic ?: 'latest news'));
        }

        return [
            'query' => $query,
            'selected_term' => $selectedTerm ? (string) $selectedTerm : null,
            'category' => $category,
            'source_mode' => $sourceMode,
        ];
    }

    /**
     * @param array<string, mixed> $resolved
     * @return array{urls: array<int, string>, context: array<string, mixed>}
     */
    public function discoverUrls(PublishCampaign $campaign, array $resolved, int $limit = 3): array
    {
        $context = $this->buildSearchContext($campaign, $resolved);

        $urls = $this->sourceDiscovery->discoverUrls($context['query'], [
            'mode' => $context['source_mode'],
            'category' => $context['category'],
            'genre' => $resolved['genre'] ?? null,
            'sources' => $resolved['article_sources'] ?? [],
        ], $limit);

        return [
            'urls' => $urls,
            'context' => $context,
        ];
    }
}
