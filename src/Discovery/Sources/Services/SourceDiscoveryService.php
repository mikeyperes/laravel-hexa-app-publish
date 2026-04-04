<?php

namespace hexa_app_publish\Discovery\Sources\Services;

use Illuminate\Support\Facades\Log;

/**
 * SourceDiscoveryService — single source of truth for article/news discovery.
 *
 * Supports: Google News RSS, GNews, NewsData, Currents News.
 * Replaces duplicated search logic from PublishSearchController,
 * PublishArticleController::searchSources(), and CampaignRunService::findSources().
 */
class SourceDiscoveryService
{
    /**
     * All supported article source providers.
     */
    private const PROVIDERS = ['google-news-rss', 'gnews', 'newsdata', 'currents_news'];

    /**
     * Search for articles across configured news providers.
     *
     * @param string $query Search query
     * @param array $options {
     *     @type array  $sources   Provider names to search (default: all available)
     *     @type int    $perPage   Results per provider (default: 10)
     *     @type string $mode      Search mode: keyword|local|trending|genre (default: keyword)
     *     @type string $category  Category filter for trending/genre modes
     *     @type string $country   Country code (default: us)
     *     @type string $language  Language code (default: en)
     *     @type string $genre     Genre filter (used by Currents)
     * }
     * @return array{success: bool, message: string, data: array{articles: array, totals: array, errors: array, urls: array}}
     */
    public function searchArticles(string $query, array $options = []): array
    {
        $sources  = $options['sources'] ?? self::PROVIDERS;
        $perPage  = $options['per_page'] ?? 10;
        $mode     = $options['mode'] ?? 'keyword';
        $category = $options['category'] ?? null;
        $country  = $options['country'] ?? 'us';
        $language = $options['language'] ?? 'en';
        $genre    = $options['genre'] ?? null;

        // Mode-specific query adjustments
        $query = $this->adjustQueryForMode($query, $mode, $category);

        $allArticles = [];
        $errors = [];
        $totals = [];
        $urls = [];

        foreach ($sources as $source) {
            $result = $this->searchProvider($source, $query, $perPage, $language, $genre);
            if ($result['success'] && !empty($result['articles'])) {
                $allArticles = array_merge($allArticles, $result['articles']);
                $totals[$source] = $result['total'] ?? count($result['articles']);
                foreach ($result['articles'] as $article) {
                    if (!empty($article['url'])) {
                        $urls[] = $article['url'];
                    }
                }
            } elseif (!$result['success'] && !empty($result['error'])) {
                $errors[] = $result['error'];
            }
        }

        // Sort by published date descending
        usort($allArticles, function ($a, $b) {
            return strtotime($b['published_at'] ?? '0') - strtotime($a['published_at'] ?? '0');
        });

        return [
            'success'  => count($allArticles) > 0,
            'message'  => count($allArticles) . ' articles found across ' . count($totals) . ' source(s).',
            'data'     => [
                'articles' => $allArticles,
                'totals'   => $totals,
                'errors'   => $errors,
                'urls'     => $urls,
            ],
        ];
    }

    /**
     * Get only article URLs (used by campaign automation).
     *
     * @param string $query
     * @param array $options
     * @param int $limit Max URLs to return
     * @return array
     */
    public function discoverUrls(string $query, array $options = [], int $limit = 3): array
    {
        $result = $this->searchArticles($query, $options);
        return array_slice($result['data']['urls'] ?? [], 0, $limit);
    }

    /**
     * Check which providers are configured (have API keys).
     *
     * @return array{provider: bool}
     */
    public function availableProviders(): array
    {
        return [
            'google-news-rss' => true, // Free, no key needed
            'gnews'           => !empty(\hexa_core\Models\Setting::getValue('gnews_api_key', '')),
            'newsdata'        => !empty(\hexa_core\Models\Setting::getValue('newsdata_api_key', '')),
            'currents_news'   => !empty(\hexa_core\Models\Setting::getValue('currents_news_api_key', '')),
        ];
    }

    /**
     * Adjust query based on search mode.
     *
     * @param string $query
     * @param string $mode
     * @param string|null $category
     * @return string
     */
    private function adjustQueryForMode(string $query, string $mode, ?string $category): string
    {
        if ($mode === 'trending' && empty($query)) {
            return $category ?: 'breaking news';
        }
        if ($mode === 'genre' && $category) {
            return $query ? "{$query} {$category}" : $category;
        }
        return $query ?: 'latest news';
    }

    /**
     * Search a single provider. Returns normalized result.
     *
     * @param string $provider
     * @param string $query
     * @param int $perPage
     * @param string $language
     * @param string|null $genre
     * @return array{success: bool, articles: array, total: int, error: string|null}
     */
    private function searchProvider(string $provider, string $query, int $perPage, string $language, ?string $genre): array
    {
        try {
            return match ($provider) {
                'google-news-rss' => $this->searchGoogleRss($query, $perPage),
                'gnews'           => $this->searchGNews($query, $perPage),
                'newsdata'        => $this->searchNewsData($query, $perPage),
                'currents_news'   => $this->searchCurrents($query, $language, $genre),
                default           => ['success' => false, 'articles' => [], 'total' => 0, 'error' => "Unknown provider: {$provider}"],
            };
        } catch (\Exception $e) {
            Log::warning("[SourceDiscovery] {$provider} failed: " . $e->getMessage());
            return ['success' => false, 'articles' => [], 'total' => 0, 'error' => "{$provider}: " . $e->getMessage()];
        }
    }

    /**
     * @param string $query
     * @param int $perPage
     * @return array
     */
    private function searchGoogleRss(string $query, int $perPage): array
    {
        $rssUrl = 'https://news.google.com/rss/search?q=' . urlencode($query) . '&hl=en-US&gl=US&ceid=US:en';
        $xml = @simplexml_load_string(@file_get_contents($rssUrl));
        if (!$xml || !isset($xml->channel->item)) {
            return ['success' => false, 'articles' => [], 'total' => 0, 'error' => 'Google RSS: No results'];
        }

        $articles = [];
        $count = 0;
        foreach ($xml->channel->item as $item) {
            if ($count >= $perPage) break;
            $articles[] = [
                'source_api'    => 'google-news-rss',
                'title'         => (string) $item->title,
                'description'   => strip_tags((string) $item->description),
                'content'       => '',
                'url'           => (string) $item->link,
                'image'         => null,
                'published_at'  => (string) $item->pubDate,
                'source_name'   => (string) ($item->source ?? 'Google News'),
                'source_url'    => '',
            ];
            $count++;
        }

        return ['success' => true, 'articles' => $articles, 'total' => $count, 'error' => null];
    }

    /**
     * @param string $query
     * @param int $perPage
     * @return array
     */
    private function searchGNews(string $query, int $perPage): array
    {
        if (!class_exists(\hexa_package_gnews\Services\GNewsService::class)) {
            return ['success' => false, 'articles' => [], 'total' => 0, 'error' => 'GNews: Package not installed'];
        }

        $result = app(\hexa_package_gnews\Services\GNewsService::class)->searchArticles($query, min($perPage, 10));
        if ($result['success'] && !empty($result['data']['articles'])) {
            return ['success' => true, 'articles' => $result['data']['articles'], 'total' => $result['data']['total'] ?? 0, 'error' => null];
        }

        return ['success' => false, 'articles' => [], 'total' => 0, 'error' => 'GNews: ' . ($result['message'] ?? 'Failed')];
    }

    /**
     * @param string $query
     * @param int $perPage
     * @return array
     */
    private function searchNewsData(string $query, int $perPage): array
    {
        if (!class_exists(\hexa_package_newsdata\Services\NewsDataService::class)) {
            return ['success' => false, 'articles' => [], 'total' => 0, 'error' => 'NewsData: Package not installed'];
        }

        $result = app(\hexa_package_newsdata\Services\NewsDataService::class)->searchArticles($query, $perPage);
        if ($result['success'] && !empty($result['data']['articles'])) {
            return ['success' => true, 'articles' => $result['data']['articles'], 'total' => $result['data']['total'] ?? 0, 'error' => null];
        }

        return ['success' => false, 'articles' => [], 'total' => 0, 'error' => 'NewsData: ' . ($result['message'] ?? 'Failed')];
    }

    /**
     * @param string $query
     * @param string $language
     * @param string|null $genre
     * @return array
     */
    private function searchCurrents(string $query, string $language, ?string $genre): array
    {
        if (!class_exists(\hexa_package_currents_news\Services\CurrentsNewsService::class)) {
            return ['success' => false, 'articles' => [], 'total' => 0, 'error' => 'Currents: Package not installed'];
        }

        $result = app(\hexa_package_currents_news\Services\CurrentsNewsService::class)->searchArticles($query, $language, null, $genre);
        if ($result['success'] && !empty($result['data'])) {
            // Currents returns data directly as array of articles, not data.articles
            $articles = [];
            foreach ($result['data'] as $item) {
                $articles[] = [
                    'source_api'    => 'currents_news',
                    'title'         => $item['title'] ?? '',
                    'description'   => $item['description'] ?? '',
                    'content'       => '',
                    'url'           => $item['url'] ?? '',
                    'image'         => $item['image'] ?? null,
                    'published_at'  => $item['published'] ?? '',
                    'source_name'   => $item['author'] ?? 'Currents News',
                    'source_url'    => '',
                ];
            }
            return ['success' => true, 'articles' => $articles, 'total' => count($articles), 'error' => null];
        }

        return ['success' => false, 'articles' => [], 'total' => 0, 'error' => 'Currents: ' . ($result['message'] ?? 'No API key configured')];
    }
}
