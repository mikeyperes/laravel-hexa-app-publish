<?php

namespace hexa_app_publish\Publishing\Campaigns\Services;

use hexa_app_publish\Discovery\Sources\Services\SourceDiscoveryService;
use hexa_app_publish\Discovery\Sources\Health\Services\SourceAccessStrategyService;
use hexa_app_publish\Discovery\Links\Health\Services\LinkHealthService;
use hexa_app_publish\Publishing\Articles\Services\ArticleActivityService;
use hexa_app_publish\Support\AiModelCatalog;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class CampaignArticleSearchService
{
    public function __construct(
        protected SourceDiscoveryService $sourceDiscovery,
        protected AiModelCatalog $catalog,
        protected ArticleActivityService $activities,
        protected SourceAccessStrategyService $sourceAccessStrategy,
    ) {
    }

    /**
     * @param array<int, string> $models
     * @return array{success: bool, message: string, urls: array<int, string>, details: array<string, mixed>}
     */
    public function search(string $topic, int $count = 3, array $models = [], ?int $articleId = null): array
    {
        $count = max(1, min(6, $count));
        $models = collect($models)->map(fn ($model) => trim((string) $model))->filter()->unique()->values()->all();

        $attempts = [];
        $selectedArticles = [];
        $selectedModel = null;
        $usage = [];
        $cost = 0.0;
        $verification = ['checked' => 0, 'kept' => 0, 'discarded' => 0];
        $coherence = [
            'anchor_title' => null,
            'mode' => 'unscored',
            'kept' => 0,
            'dropped_titles' => [],
            'top_similarity' => null,
        ];

        foreach ($models as $model) {
            $rawResult = $this->searchWithModel($topic, $count, $model);
            $verified = $this->verifyArticleCandidates((array) data_get($rawResult, 'data.articles', []), $count, [], $topic);
            $verification = $verified['stats'];
            $coherent = $this->selectCoherentArticles($verified['articles'], $count);
            $coherence = $coherent['meta'];
            $attemptNo = count($attempts) + 1;

            $attempts[] = [
                'model' => $model,
                'provider' => $this->catalog->providerForModel($model),
                'success' => (bool) ($rawResult['success'] ?? false),
                'message' => (string) ($rawResult['message'] ?? ''),
                'checked' => $verification['checked'],
                'kept' => $verification['kept'],
                'discarded' => $verification['discarded'],
                'coherent_kept' => count($coherent['articles']),
                'coherence_mode' => $coherent['meta']['mode'] ?? null,
            ];

            $this->activities->record($articleId, [
                'activity_group' => 'campaign-search:' . md5($topic),
                'activity_type' => 'search',
                'stage' => 'discovery',
                'substage' => 'ai_search_attempt',
                'status' => !empty($coherent['articles']) ? 'success' : (!empty($rawResult['success']) ? 'empty' : 'failed'),
                'provider' => $this->catalog->providerForModel($model),
                'model' => $model,
                'agent' => 'campaign-search',
                'method' => 'google_via_ai',
                'attempt_no' => $attemptNo,
                'success' => !empty($coherent['articles']),
                'message' => (string) ($rawResult['message'] ?? ''),
                'request_payload' => [
                    'topic' => $topic,
                    'count' => $count,
                    'prompt' => data_get($rawResult, 'data.request_prompt'),
                ],
                'response_payload' => [
                    'usage' => (array) data_get($rawResult, 'data.usage', []),
                    'raw_articles' => array_slice((array) data_get($rawResult, 'data.articles', []), 0, 10),
                    'verified_articles' => array_slice((array) $verified['articles'], 0, 10),
                    'selected_articles' => array_slice((array) $coherent['articles'], 0, 10),
                ],
                'meta' => [
                    'verification' => $verification,
                    'coherence' => $coherence,
                ],
            ]);

            if (!empty($coherent['articles'])) {
                $selectedArticles = $coherent['articles'];
                $selectedModel = $model;
                $usage = (array) data_get($rawResult, 'data.usage', []);
                $cost = $this->catalog->calculateCost($model, $usage);
                break;
            }
        }

        if (!empty($selectedArticles)) {
            return [
                'success' => true,
                'message' => count($selectedArticles) . ' live article(s) verified via AI search.',
                'urls' => array_values(array_unique(array_column($selectedArticles, 'url'))),
                'details' => [
                    'search_backend' => 'google_via_ai',
                    'search_backend_label' => 'Google via AI',
                    'model' => $selectedModel,
                    'usage' => $usage,
                    'cost' => round($cost, 6),
                    'articles' => $selectedArticles,
                    'attempts' => $attempts,
                    'verification' => $verification,
                    'coherence' => $coherence,
                ],
            ];
        }

        $fallback = $this->fallbackArticleSearch($topic, $count, $attempts);
        $this->activities->record($articleId, [
            'activity_group' => 'campaign-search:' . md5($topic),
            'activity_type' => 'search',
            'stage' => 'discovery',
            'substage' => 'php_fallback',
            'status' => !empty($fallback['success']) ? 'success' : 'failed',
            'provider' => 'php',
            'agent' => 'campaign-search',
            'method' => 'php_fallback',
            'attempt_no' => count($attempts) + 1,
            'is_retry' => !empty($attempts),
            'success' => (bool) ($fallback['success'] ?? false),
            'message' => (string) ($fallback['message'] ?? ''),
            'request_payload' => [
                'topic' => $topic,
                'count' => $count,
            ],
            'response_payload' => [
                'urls' => (array) ($fallback['urls'] ?? []),
                'articles' => (array) data_get($fallback, 'details.articles', []),
            ],
            'meta' => [
                'verification' => (array) data_get($fallback, 'details.verification', []),
                'coherence' => (array) data_get($fallback, 'details.coherence', []),
                'attempts' => $attempts,
            ],
        ]);
        if ($fallback['success']) {
            return $fallback;
        }

        return [
            'success' => false,
            'message' => 'AI search and PHP fallback both failed to find live article URLs.',
            'urls' => [],
            'details' => [
                'search_backend' => 'failed',
                'search_backend_label' => 'Search failed',
                'attempts' => $attempts,
                'verification' => $verification,
            ],
        ];
    }

    /**
     * @return array{success: bool, message: string, data: array<string, mixed>|null}
     */
    protected function searchWithModel(string $topic, int $count, string $model): array
    {
        try {
            return app(\hexa_app_publish\Discovery\Sources\Services\AiOptimizedArticleSearchService::class)->search($topic, $count, $model);
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage(), 'data' => null];
        }
    }

    /**
     * @param array{success: bool, message: string, data: array<string, mixed>|null} $result
     * @return array{success: bool, message: string, data: array<string, mixed>|null}
     */
    protected function withPrompt(array $result, string $prompt): array
    {
        if (!is_array($result['data'] ?? null)) {
            return $result;
        }

        $result['data']['request_prompt'] = $prompt;

        return $result;
    }

    /**
     * @return array{success: bool, message: string, urls: array<int, string>, details: array<string, mixed>}
     */
    protected function fallbackArticleSearch(string $topic, int $count, array $attempts): array
    {
        $fallback = $this->sourceDiscovery->searchArticles($topic, [
            'per_page' => max(2, min(10, $count)),
            'sources' => ['google-news-rss', 'gnews', 'newsdata', 'currents_news'],
        ]);

        if (!(bool) data_get($fallback, 'success', false) || empty(data_get($fallback, 'data.articles', []))) {
            return [
                'success' => false,
                'message' => 'PHP fallback search did not return live articles.',
                'urls' => [],
                'details' => [
                    'search_backend' => 'php_fallback_failed',
                    'search_backend_label' => 'PHP fallback failed',
                    'attempts' => $attempts,
                ],
            ];
        }

        $verified = $this->verifyArticleCandidates((array) ($fallback['data']['articles'] ?? []), $count, [], $topic);
        $coherent = $this->selectCoherentArticles($verified['articles'], $count);
        $urls = array_values(array_unique(array_column($coherent['articles'], 'url')));

        return [
            'success' => !empty($urls),
            'message' => !empty($urls)
                ? count($urls) . ' live article(s) verified via PHP fallback.'
                : 'PHP fallback search returned no live article URLs.',
            'urls' => $urls,
            'details' => [
                'search_backend' => 'php_fallback',
                'search_backend_label' => 'PHP fallback',
                'attempts' => $attempts,
                'verification' => $verified['stats'],
                'articles' => $coherent['articles'],
                'coherence' => $coherent['meta'],
            ],
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $articles
     * @return array{articles: array<int, array<string, mixed>>, meta: array<string, mixed>}
     */
    protected function selectCoherentArticles(array $articles, int $limit): array
    {
        $articles = array_values($articles);
        if (count($articles) <= 1) {
            return [
                'articles' => array_slice($articles, 0, $limit),
                'meta' => [
                    'anchor_title' => $articles[0]['title'] ?? null,
                    'mode' => count($articles) === 1 ? 'single_article' : 'empty',
                    'kept' => count($articles),
                    'dropped_titles' => [],
                    'top_similarity' => null,
                ],
            ];
        }

        $pairScores = [];
        $anchorIndex = 0;
        $anchorScore = -1.0;

        foreach ($articles as $leftIndex => $leftArticle) {
            $scoreSum = 0.0;
            foreach ($articles as $rightIndex => $rightArticle) {
                if ($leftIndex === $rightIndex) {
                    continue;
                }

                $score = $this->articleSimilarity($leftArticle, $rightArticle);
                $pairScores[$leftIndex][$rightIndex] = $score;
                $scoreSum += $score;
            }

            if ($scoreSum > $anchorScore) {
                $anchorScore = $scoreSum;
                $anchorIndex = $leftIndex;
            }
        }

        $anchor = $articles[$anchorIndex];
        $coherent = [$anchor];
        $droppedTitles = [];
        $topSimilarity = 0.0;

        foreach ($articles as $index => $article) {
            if ($index === $anchorIndex) {
                continue;
            }

            $score = (float) ($pairScores[$anchorIndex][$index] ?? $this->articleSimilarity($anchor, $article));
            $topSimilarity = max($topSimilarity, $score);

            if ($score >= 0.18) {
                $coherent[] = $article;
                continue;
            }

            $droppedTitles[] = (string) ($article['title'] ?? $article['url'] ?? 'Untitled article');
        }

        $coherent = array_slice(array_values($coherent), 0, $limit);
        $mode = count($coherent) >= 2 ? 'clustered' : 'collapsed_to_anchor';

        return [
            'articles' => $coherent,
            'meta' => [
                'anchor_title' => (string) ($anchor['title'] ?? ''),
                'mode' => $mode,
                'kept' => count($coherent),
                'dropped_titles' => array_slice($droppedTitles, 0, 5),
                'top_similarity' => round($topSimilarity, 3),
            ],
        ];
    }

    /**
     * @param array<string, mixed> $leftArticle
     * @param array<string, mixed> $rightArticle
     */
    protected function articleSimilarity(array $leftArticle, array $rightArticle): float
    {
        $leftTokens = $this->articleTokens($leftArticle);
        $rightTokens = $this->articleTokens($rightArticle);

        if ($leftTokens === [] || $rightTokens === []) {
            return 0.0;
        }

        $intersection = count(array_intersect($leftTokens, $rightTokens));
        $union = count(array_unique(array_merge($leftTokens, $rightTokens)));

        return $union > 0 ? ($intersection / $union) : 0.0;
    }

    /**
     * @param array<string, mixed> $article
     * @return array<int, string>
     */
    protected function articleTokens(array $article): array
    {
        $text = trim((string) (($article['title'] ?? '') . ' ' . ($article['description'] ?? '')));
        $text = Str::lower($text);
        $text = preg_replace('/[^a-z0-9\s]+/', ' ', $text);
        $parts = preg_split('/\s+/', (string) $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $stopWords = $this->stopWords();

        return array_values(array_unique(array_filter($parts, static function ($part) use ($stopWords) {
            return strlen($part) >= 3 && !in_array($part, $stopWords, true);
        })));
    }

    /**
     * @return array<int, string>
     */
    protected function stopWords(): array
    {
        return [
            'about', 'after', 'amid', 'analysis', 'article', 'because', 'breaking', 'commentary', 'could',
            'daily', 'editorial', 'feature', 'first', 'from', 'have', 'into', 'latest', 'more', 'most',
            'news', 'over', 'says', 'show', 'story', 'than', 'that', 'their', 'them', 'these', 'they',
            'this', 'those', 'today', 'under', 'update', 'updates', 'what', 'when', 'where', 'which',
            'while', 'with', 'women', 'womens', 'woman', 'female',
        ];
    }

    /**
     * @param array<int, mixed> $articles
     * @param array<int, string> $excludeUrls
     * @return array{articles: array<int, array<string, mixed>>, stats: array{checked: int, kept: int, discarded: int}}
     */
    protected function verifyArticleCandidates(array $articles, int $limit, array $excludeUrls = [], ?string $topic = null): array
    {
        return app(LinkHealthService::class)->verifyArticleCandidates(
            $articles,
            $limit,
            $excludeUrls,
            fn (array $candidate): bool => $this->matchesTopic($candidate, $topic)
                && !$this->sourceAccessStrategy->shouldBlockDiscoveryCandidate((string) ($candidate['url'] ?? '')),
            'campaign'
        );
    }

    /**
     * @param mixed $article
     * @return array<string, mixed>|null
     */
    protected function normalizeArticleCandidate(mixed $article): ?array
    {
        if (!is_array($article)) {
            return null;
        }

        $url = $this->normalizeArticleUrlCandidate((string) ($article['url'] ?? ''));
        if (!$url || !$this->looksLikeCanonicalArticleUrl($url)) {
            return null;
        }

        return [
            'url' => $url,
            'title' => trim((string) ($article['title'] ?? $url)),
            'description' => trim((string) ($article['description'] ?? '')),
        ];
    }

    /**
     * @return array{url: string, status_code: int|null, status_text: string, checked_via: string, final_url: string, is_broken: bool, probe_failed: bool}
     */
    protected function probeArticleUrl(string $url): array
    {
        $normalized = $this->normalizeArticleUrlCandidate($url);
        if (!$normalized) {
            return [
                'url' => $url,
                'status_code' => null,
                'status_text' => 'Invalid URL',
                'checked_via' => 'validation',
                'final_url' => '',
                'is_broken' => true,
                'probe_failed' => true,
            ];
        }

        $cacheKey = 'campaign:link-status:' . md5($normalized);
        $cached = Cache::get($cacheKey);
        if (is_array($cached)) {
            return $cached;
        }

        try {
            $response = Http::withHeaders([
                'User-Agent' => 'Hexa Publish Link Checker/1.0',
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            ])
                ->connectTimeout(5)
                ->timeout(10)
                ->withOptions([
                    'allow_redirects' => ['max' => 5, 'track_redirects' => true],
                    'http_errors' => false,
                ])
                ->head($url);

            $checkedVia = 'HEAD';
            $statusCode = $response->status();

            if (in_array((int) $statusCode, [0, 403, 405, 406], true)) {
                $response = Http::withHeaders([
                    'User-Agent' => 'Hexa Publish Link Checker/1.0',
                    'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                ])
                    ->connectTimeout(5)
                    ->timeout(10)
                    ->withOptions([
                        'allow_redirects' => ['max' => 5, 'track_redirects' => true],
                        'http_errors' => false,
                    ])
                    ->get($url);

                $checkedVia = 'GET';
                $statusCode = $response->status();
            }

            $finalUrl = $this->resolveFinalUrlFromResponse($url, $response);
            $isBroken = !($statusCode >= 200 && $statusCode < 400);
            $statusText = $isBroken ? ($statusCode ? ($statusCode . ' Response') : 'No response') : ($statusCode . ' OK');

            if (!$isBroken && !$this->looksLikeCanonicalArticleUrl($finalUrl)) {
                $isBroken = true;
                $statusText = 'Redirected to non-article page';
            }

            $result = [
                'url' => $url,
                'status_code' => $statusCode,
                'status_text' => $statusText,
                'checked_via' => $checkedVia,
                'final_url' => $finalUrl,
                'is_broken' => $isBroken,
                'probe_failed' => false,
            ];

            Cache::put($cacheKey, $result, now()->addMinutes(15));

            return $result;
        } catch (\Throwable $e) {
            return [
                'url' => $url,
                'status_code' => null,
                'status_text' => 'Check failed: ' . Str::limit($e->getMessage(), 120, ''),
                'checked_via' => 'error',
                'final_url' => $url,
                'is_broken' => false,
                'probe_failed' => true,
            ];
        }
    }

    protected function resolveFinalUrlFromResponse(string $url, $response): string
    {
        $history = $response->header('X-Guzzle-Redirect-History');

        if (is_array($history) && !empty($history)) {
            $last = end($history);

            return is_string($last) && $last !== '' ? $last : $url;
        }

        if (is_string($history) && trim($history) !== '') {
            $parts = array_values(array_filter(array_map('trim', explode(',', $history))));
            if (!empty($parts)) {
                return $parts[count($parts) - 1];
            }
        }

        return $url;
    }

    protected function normalizeArticleUrlCandidate(string $url): ?string
    {
        $url = html_entity_decode(trim($url), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $url = trim($url, " \t\n\r\0\x0B<>\"'");
        $url = rtrim($url, '.,;)]}');

        if (preg_match('/^www\./i', $url)) {
            $url = 'https://' . $url;
        }

        if ($url === '' || !filter_var($url, FILTER_VALIDATE_URL)) {
            return null;
        }

        $parts = parse_url($url);
        if (!is_array($parts)) {
            return null;
        }

        $scheme = Str::lower((string) ($parts['scheme'] ?? ''));
        if (!in_array($scheme, ['http', 'https'], true)) {
            return null;
        }

        return $url;
    }

    protected function looksLikeCanonicalArticleUrl(string $url): bool
    {
        $parts = parse_url($url);
        if (!is_array($parts)) {
            return false;
        }

        $host = Str::lower((string) ($parts['host'] ?? ''));
        $path = Str::lower(trim((string) ($parts['path'] ?? '/')));
        $query = Str::lower((string) ($parts['query'] ?? ''));

        if ($host === '' || $path === '' || $path === '/') {
            return false;
        }

        $blockedFragments = [
            '/search',
            '/tag/',
            '/tags/',
            '/category/',
            '/categories/',
            '/topic/',
            '/topics/',
            '/author/',
            '/authors/',
            '/archive',
            '/archives/',
            '/newsletter',
            '/video/',
        ];

        foreach ($blockedFragments as $fragment) {
            if (str_contains($path, $fragment)) {
                return false;
            }
        }

        if (str_contains($host, 'news.google.com')) {
            return false;
        }

        foreach ($this->blockedHosts() as $blockedHost) {
            if ($host === $blockedHost || str_ends_with($host, '.' . $blockedHost)) {
                return false;
            }
        }

        if ($query !== '' && preg_match('/(^|&)(q|query|search|s|output)=/', $query)) {
            return false;
        }

        return true;
    }

    /**
     * @return array{success: bool, message: string, data: array<string, mixed>|null}
     */
    protected function parseSearchResult(array $raw, string $model): array
    {
        if (!$raw['success']) {
            return ['success' => false, 'message' => $raw['message'] ?? 'AI call failed', 'data' => null];
        }

        $content = $raw['data']['content'] ?? '';
        $content = preg_replace('/^```(?:json)?\s*/i', '', trim($content));
        $content = preg_replace('/\s*```$/', '', $content);

        $articles = json_decode($content, true);
        if (!is_array($articles)) {
            return ['success' => false, 'message' => 'Could not parse article results from AI response.', 'data' => null];
        }

        return [
            'success' => true,
            'message' => count($articles) . ' article candidates returned.',
            'data' => [
                'articles' => $articles,
                'model' => $raw['data']['model'] ?? $model,
                'usage' => $raw['data']['usage'] ?? [],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $candidate
     */
    protected function matchesTopic(array $candidate, ?string $topic): bool
    {
        $topicTokens = $this->queryTokens((string) $topic);
        if ($topicTokens === []) {
            return true;
        }

        $articleTokens = $this->articleTokens($candidate);
        if ($articleTokens === []) {
            return false;
        }

        $overlap = count(array_intersect($topicTokens, $articleTokens));
        if ($overlap > 0) {
            return true;
        }

        if (count($topicTokens) >= 2) {
            $topicPhrase = implode(' ', $topicTokens);
            $haystack = Str::lower((string) (($candidate['title'] ?? '') . ' ' . ($candidate['description'] ?? '')));
            return str_contains($haystack, $topicPhrase);
        }

        return false;
    }

    /**
     * @return array<int, string>
     */
    protected function queryTokens(string $topic): array
    {
        $topic = Str::lower(trim($topic));
        $topic = preg_replace('/[^a-z0-9\s]+/', ' ', $topic);
        $parts = preg_split('/\s+/', (string) $topic, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $stopWords = $this->stopWords();

        return array_values(array_unique(array_filter($parts, static function ($part) use ($stopWords) {
            return strlen($part) >= 3 && !in_array($part, $stopWords, true);
        })));
    }

    /**
     * @return array<int, string>
     */
    protected function blockedHosts(): array
    {
        return [
            'headtopics.com',
        ];
    }
};
