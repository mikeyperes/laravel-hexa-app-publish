<?php

namespace hexa_app_publish\Publishing\Campaigns\Services;

use hexa_app_publish\Discovery\Sources\Services\SourceDiscoveryService;
use hexa_app_publish\Publishing\Campaigns\Models\PublishCampaign;

class CampaignDiscoveryService
{
    public function __construct(
        protected SourceDiscoveryService $sourceDiscovery,
        protected CampaignArticleSearchService $articleSearch
    )
    {
    }

    /**
     * @param array<string, mixed> $resolved
     * @return array{query: string, selected_term: ?string, category: ?string, genre: ?string, source_mode: string}
     */
    public function buildSearchContext(PublishCampaign $campaign, array $resolved): array
    {
        $terms = array_values((array) ($resolved['search_terms'] ?? []));
        $selectedTerm = !empty($terms) ? $terms[array_rand($terms)] : null;
        $sourceMode = (string) ($resolved['campaign_source_method'] ?? 'keyword');
        $category = null;
        $genre = null;
        $localPreference = trim((string) ($resolved['campaign_local_preference'] ?? ''));
        $query = trim((string) ($selectedTerm ?: ($resolved['topic'] ?? '')));

        $allSingleWordTerms = !empty($terms) && collect($terms)->every(function ($term) {
            return str_word_count(trim((string) $term)) <= 1;
        });

        if ($allSingleWordTerms && count($terms) > 1) {
            $query = trim(implode(' ', array_slice($terms, 0, 3)));
        } elseif ($query !== '' && str_word_count($query) <= 1 && count($terms) > 1) {
            $companions = array_values(array_filter($terms, fn ($term) => trim((string) $term) !== trim((string) $selectedTerm)));
            if (!empty($companions)) {
                $query = trim($query . ' ' . $companions[0]);
            }
        }

        if ($query === '') {
            $query = trim((string) ($campaign->topic ?: 'latest news'));
        }

        if ($sourceMode === 'local' && $localPreference !== '') {
            $query = trim($query . ' ' . $localPreference);
        }

        if ($sourceMode === 'trending') {
            $categories = array_values(array_filter((array) ($resolved['campaign_trending_categories'] ?? [])));
            if (!empty($categories)) {
                $category = (string) $categories[array_rand($categories)];
                $query = trim($query !== '' ? ($query . ' ' . $category) : $category);
            }
        }

        if ($sourceMode === 'genre') {
            $genre = trim((string) ($resolved['campaign_genre'] ?? ''));
            $category = $genre !== '' ? $genre : $category;
            if ($query === '' && $genre !== '') {
                $query = $genre;
            }
        }

        return [
            'query' => $query,
            'selected_term' => $selectedTerm ? (string) $selectedTerm : null,
            'category' => $category,
            'genre' => $genre !== '' ? $genre : null,
            'source_mode' => $sourceMode,
        ];
    }

    /**
     * @param array<string, mixed> $resolved
     * @return array{urls: array<int, string>, context: array<string, mixed>, details: array<string, mixed>}
     */
    public function discoverUrls(PublishCampaign $campaign, array $resolved, int $limit = 3, ?int $articleId = null): array
    {
        $context = $this->buildSearchContext($campaign, $resolved);
        $details = [
            'search_backend' => 'php_discovery',
            'search_backend_label' => 'PHP discovery',
            'attempts' => [],
        ];

        if (($resolved['search_online_for_additional_context'] ?? true) === true) {
            $search = $this->articleSearch->search($context['query'], $limit, [
                $resolved['online_search_model_primary'] ?? null,
                $resolved['online_search_model_fallback'] ?? null,
            ], $articleId);

            $details = array_merge($details, (array) ($search['details'] ?? []));
            if ($search['success'] && !empty($search['urls'])) {
                return [
                    'urls' => $search['urls'],
                    'context' => $context,
                    'details' => $details,
                ];
            }
        }

        $urls = $this->sourceDiscovery->discoverUrls($context['query'], [
            'mode' => $context['source_mode'] ?: 'keyword',
            'category' => $context['category'],
            'genre' => $context['genre'],
            'sources' => $resolved['article_sources'] ?? [],
        ], $limit);

        return [
            'urls' => $urls,
            'context' => $context,
            'details' => $details,
        ];
    }
}
