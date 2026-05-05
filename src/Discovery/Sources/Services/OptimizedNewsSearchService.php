<?php

namespace hexa_app_publish\Discovery\Sources\Services;

use hexa_app_publish\Discovery\Links\Health\Services\LinkHealthService;
use hexa_app_publish\Discovery\Sources\Health\Services\SourceAccessStrategyService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class OptimizedNewsSearchService
{
    public function __construct(
        protected SourceDiscoveryService $sourceDiscovery,
        protected LinkHealthService $linkHealth,
        protected SourceAccessStrategyService $sourceAccessStrategy,
    ) {
    }

    /**
     * @param array<string, mixed> $options
     * @return array{success: bool, message: string, data: array<string, mixed>|null}
     */
    public function search(string $topic, int $count, string $provider, string $model, array $options = []): array
    {
        $count = max(2, min(10, $count));
        $backendLabel = trim((string) ($options['backend_label'] ?? (ucfirst($provider) . ' Optimized Search')));
        $searchBackend = Str::slug($provider . '-optimized-search', '_');
        $queryPlan = $this->normalizeQueryPlan($options['query_plan'] ?? null, $topic);
        $sources = $this->normalizeSources((array) ($options['sources'] ?? ['gnews', 'newsdata', 'google-news-rss']));
        $queries = array_slice($queryPlan['queries'], 0, 3);
        $seedArticles = $this->normalizeSeedArticles((array) ($options['seed_articles'] ?? []), $topic);
        $perQuery = max($count + 2, 6);
        $providerTotals = [];
        $providerErrors = [];
        $queryDiagnostics = [];
        $candidates = [];

        foreach ($queries as $query) {
            $result = $this->sourceDiscovery->searchArticles($query, [
                'per_page' => $perQuery,
                'sources' => $sources,
            ]);

            $queryDiagnostics[] = [
                'query' => $query,
                'success' => (bool) ($result['success'] ?? false),
                'count' => count((array) data_get($result, 'data.articles', [])),
            ];

            foreach ((array) data_get($result, 'data.totals', []) as $source => $total) {
                $providerTotals[$source] = ($providerTotals[$source] ?? 0) + (int) $total;
            }

            foreach ((array) data_get($result, 'data.errors', []) as $error) {
                $providerErrors[] = (string) $error;
            }

            foreach ((array) data_get($result, 'data.articles', []) as $article) {
                $normalized = $this->normalizeRawCandidate($article, $query, false);
                if ($normalized !== null) {
                    $candidates[] = $normalized;
                }
            }
        }

        foreach ($seedArticles as $seedArticle) {
            $candidates[] = $seedArticle;
        }

        $ranked = $this->rankCandidates($candidates, $topic, $queryPlan);
        if ($ranked === []) {
            return [
                'success' => false,
                'message' => 'Optimized search found no usable news candidates.',
                'data' => [
                    'articles' => [],
                    'model' => $model,
                    'search_backend' => $searchBackend,
                    'search_backend_label' => $backendLabel,
                    'query_plan' => $queryPlan,
                    'search_queries' => $queries,
                    'provider_totals' => $providerTotals,
                    'provider_errors' => array_values(array_unique(array_filter($providerErrors))),
                    'news_provider_label' => $this->formatSearchProviderLabels(array_keys(array_filter($providerTotals))),
                    'candidate_count' => 0,
                    'seed_count' => count($seedArticles),
                    'query_diagnostics' => $queryDiagnostics,
                    'optimized_verified' => true,
                    'verification' => ['checked' => 0, 'kept' => 0, 'discarded' => 0],
                ],
            ];
        }

        $lookup = [];
        foreach ($ranked as $candidate) {
            $lookup[$candidate['url']] = $candidate;
        }

        $verificationPool = max($count * 4, 12);
        $verificationLimit = max($count * 2, $count + 2);
        $verified = $this->linkHealth->verifyArticleCandidates(
            array_slice($ranked, 0, $verificationPool),
            $verificationLimit,
            [],
            fn (array $candidate): bool => $this->matchesTopic($candidate, $topic, $queryPlan)
                && !$this->sourceAccessStrategy->shouldBlockDiscoveryCandidate((string) ($candidate['url'] ?? '')),
            'optimized-search:' . $provider
        );

        $articles = [];
        foreach ((array) ($verified['articles'] ?? []) as $article) {
            $meta = $lookup[$article['url']] ?? null;
            $articles[] = array_filter(array_merge($meta ?? [], $article), static fn ($value) => $value !== null && $value !== '');
        }

        usort($articles, static fn (array $left, array $right): int => (($right['score'] ?? 0) <=> ($left['score'] ?? 0)));
        $coherent = $this->selectCoherentArticles($articles, $count);
        $articles = $coherent['articles'];

        if ($articles === []) {
            return [
                'success' => false,
                'message' => 'Optimized search found candidates, but none verified as live article URLs.',
                'data' => [
                    'articles' => [],
                    'model' => $model,
                    'search_backend' => $searchBackend,
                    'search_backend_label' => $backendLabel,
                    'query_plan' => $queryPlan,
                    'search_queries' => $queries,
                    'provider_totals' => $providerTotals,
                    'provider_errors' => array_values(array_unique(array_filter($providerErrors))),
                    'news_provider_label' => $this->formatSearchProviderLabels(array_keys(array_filter($providerTotals))),
                    'candidate_count' => count($ranked),
                    'seed_count' => count($seedArticles),
                    'query_diagnostics' => $queryDiagnostics,
                    'optimized_verified' => true,
                    'verification' => (array) ($verified['stats'] ?? ['checked' => 0, 'kept' => 0, 'discarded' => 0]),
                ],
            ];
        }

        return [
            'success' => true,
            'message' => count($articles) . ' live article(s) verified via ' . $backendLabel . '.',
            'data' => [
                'articles' => $articles,
                'model' => $model,
                'search_backend' => $searchBackend,
                'search_backend_label' => $backendLabel,
                'query_plan' => $queryPlan,
                'search_queries' => $queries,
                'provider_totals' => $providerTotals,
                'provider_errors' => array_values(array_unique(array_filter($providerErrors))),
                'news_providers' => array_values(array_keys(array_filter($providerTotals))),
                'news_provider_label' => $this->formatSearchProviderLabels(array_keys(array_filter($providerTotals))),
                'candidate_count' => count($ranked),
                'seed_count' => count($seedArticles),
                'query_diagnostics' => $queryDiagnostics,
                'coherence' => $coherent['meta'],
                'optimized_verified' => true,
                'verification' => (array) ($verified['stats'] ?? ['checked' => 0, 'kept' => count($articles), 'discarded' => 0]),
            ],
        ];
    }

    /**
     * @param array<int, mixed> $seedArticles
     * @return array<int, array<string, mixed>>
     */
    protected function normalizeSeedArticles(array $seedArticles, string $topic): array
    {
        $normalized = [];

        foreach ($seedArticles as $seedArticle) {
            $candidate = $this->normalizeRawCandidate($seedArticle, $topic, true);
            if ($candidate !== null) {
                $normalized[] = $candidate;
            }
        }

        return $normalized;
    }

    /**
     * @param mixed $article
     * @return array<string, mixed>|null
     */
    protected function normalizeRawCandidate(mixed $article, string $query, bool $isSeed): ?array
    {
        if (!is_array($article)) {
            return null;
        }

        $url = $this->linkHealth->normalizeUrl((string) ($article['url'] ?? ''));
        if (!$url || !$this->linkHealth->looksLikeCanonicalArticleUrl($url)) {
            return null;
        }

        return [
            'url' => $url,
            'title' => trim((string) ($article['title'] ?? $url)),
            'description' => trim((string) ($article['description'] ?? '')),
            'published_at' => $this->normalizePublishedAt($article['published_at'] ?? $article['publishedAt'] ?? null),
            'source_api' => trim((string) ($article['source_api'] ?? ($isSeed ? 'ai_seed' : ''))),
            'source_name' => trim((string) ($article['source_name'] ?? '')),
            'search_query' => $query,
            'is_seed' => $isSeed,
        ];
    }

    protected function normalizePublishedAt(mixed $value): ?string
    {
        if (!is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->toIso8601String();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param array<int, array<string, mixed>> $candidates
     * @return array<int, array<string, mixed>>
     */
    protected function rankCandidates(array $candidates, string $topic, array $queryPlan): array
    {
        $ranked = [];

        foreach ($candidates as $candidate) {
            if ($this->isLowValueCandidate($candidate)) {
                continue;
            }

            $candidate['score'] = $this->scoreCandidate($candidate, $topic, $queryPlan);
            if (($candidate['score'] ?? 0) < 6) {
                continue;
            }

            $existing = $ranked[$candidate['url']] ?? null;

            if ($existing === null || ($candidate['score'] ?? 0) > ($existing['score'] ?? 0)) {
                $ranked[$candidate['url']] = $candidate;
            }
        }

        uasort($ranked, static fn (array $left, array $right): int => (($right['score'] ?? 0) <=> ($left['score'] ?? 0)));

        return array_values($ranked);
    }

    /**
     * @param array<string, mixed> $candidate
     * @param array<string, mixed> $queryPlan
     */
    protected function scoreCandidate(array $candidate, string $topic, array $queryPlan): float
    {
        $topicTokens = $this->tokens($topic);
        $requiredTerms = $this->tokens(implode(' ', (array) ($queryPlan['required_terms'] ?? [])));
        $avoidTerms = $this->tokens(implode(' ', (array) ($queryPlan['avoid_terms'] ?? [])));
        $queryTokens = $this->tokens(implode(' ', (array) ($queryPlan['queries'] ?? [])));
        $titleTokens = $this->tokens((string) ($candidate['title'] ?? ''));
        $descriptionTokens = $this->tokens((string) ($candidate['description'] ?? ''));
        $allTokens = array_values(array_unique(array_merge($titleTokens, $descriptionTokens)));

        $titleHits = count(array_intersect($titleTokens, $topicTokens));
        $descriptionHits = count(array_intersect($descriptionTokens, $topicTokens));
        $requiredHits = count(array_intersect($allTokens, $requiredTerms));
        $queryHits = count(array_intersect($allTokens, $queryTokens));
        $avoidHits = count(array_intersect($allTokens, $avoidTerms));

        $score = 0.0;
        $score += $titleHits * 8.0;
        $score += $descriptionHits * 4.0;
        $score += $requiredHits * 5.0;
        $score += $queryHits * 1.5;
        $score -= $avoidHits * 6.0;

        if (!empty($candidate['is_seed'])) {
            $score += 10.0;
        }

        $score += $this->urlShapeScore((string) ($candidate['url'] ?? ''));
        $score += $this->recencyScore($candidate['published_at'] ?? null);

        if ($this->isLowValueCandidate($candidate)) {
            $score -= 12.0;
        }

        $sourceApi = (string) ($candidate['source_api'] ?? '');
        if ($sourceApi !== '' && $sourceApi !== 'google-news-rss') {
            $score += 1.5;
        }

        return round($score, 3);
    }

    protected function urlShapeScore(string $url): float
    {
        $path = (string) parse_url($url, PHP_URL_PATH);
        $segments = array_values(array_filter(explode('/', trim($path, '/'))));
        $lastSegment = end($segments) ?: '';
        $score = 0.0;

        if (count($segments) >= 2) {
            $score += 1.5;
        }

        if (str_contains($lastSegment, '-')) {
            $score += 1.5;
        }

        if (preg_match_all('/(20\d{2})/', $path, $matches)) {
            $currentYear = (int) now()->format('Y');
            $years = array_map('intval', $matches[1] ?? []);
            $staleYear = !empty($years) ? min($years) : null;

            if ($staleYear !== null) {
                if ($staleYear >= ($currentYear - 1)) {
                    $score += 1.0;
                } else {
                    $score -= 3.0;
                }
            }
        }

        return $score;
    }

    protected function recencyScore(?string $publishedAt): float
    {
        if (!$publishedAt) {
            return 0.0;
        }

        try {
            $days = Carbon::parse($publishedAt)->diffInDays(now());
        } catch (\Throwable) {
            return 0.0;
        }

        return match (true) {
            $days <= 3 => 6.0,
            $days <= 7 => 4.0,
            $days <= 30 => 2.0,
            $days <= 90 => 0.5,
            default => -1.0,
        };
    }

    /**
     * @param array<string, mixed> $candidate
     * @param array<string, mixed> $queryPlan
     */
    protected function matchesTopic(array $candidate, string $topic, array $queryPlan): bool
    {
        $topicTokens = $this->tokens($topic);
        $requiredTerms = $this->tokens(implode(' ', (array) ($queryPlan['required_terms'] ?? [])));
        $textTokens = $this->tokens(trim((string) (($candidate['title'] ?? '') . ' ' . ($candidate['description'] ?? ''))));

        $topicHits = count(array_intersect($textTokens, $topicTokens));
        $requiredHits = count(array_intersect($textTokens, $requiredTerms));

        if ($this->isLowValueCandidate($candidate)) {
            return false;
        }

        $minimumRequiredHits = count($requiredTerms) >= 3 ? 2 : 1;
        if (!empty($requiredTerms) && $requiredHits < $minimumRequiredHits) {
            return false;
        }

        return $topicHits >= $minimumRequiredHits || $requiredHits >= $minimumRequiredHits;
    }

    /**
     * @param mixed $queryPlan
     * @return array{queries: array<int, string>, required_terms: array<int, string>, avoid_terms: array<int, string>, angle: string}
     */
    protected function normalizeQueryPlan(mixed $queryPlan, string $topic): array
    {
        $queries = [];
        $requiredTerms = [];
        $avoidTerms = [];
        $angle = '';

        if (is_array($queryPlan)) {
            $queries = array_values(array_filter(array_map(static fn ($query) => trim((string) $query), (array) ($queryPlan['queries'] ?? []))));
            $requiredTerms = array_values(array_filter(array_map(static fn ($term) => trim((string) $term), (array) ($queryPlan['required_terms'] ?? []))));
            $avoidTerms = array_values(array_filter(array_map(static fn ($term) => trim((string) $term), (array) ($queryPlan['avoid_terms'] ?? []))));
            $angle = trim((string) ($queryPlan['angle'] ?? ''));
        }

        $requiredTerms = array_values(array_unique(array_merge($this->defaultRequiredTerms($topic), $requiredTerms)));

        $queries = array_values(array_filter(array_map(fn (string $query): string => $this->sanitizeQuery($query), array_merge($queries, $this->fallbackQueries($topic, $requiredTerms)))));
        $queries = array_values(array_unique(array_filter($queries)));
        $queries = array_slice($queries, 0, 5);

        return [
            'queries' => $queries,
            'required_terms' => array_slice(array_values(array_unique($requiredTerms)), 0, 6),
            'avoid_terms' => array_slice(array_values(array_unique($avoidTerms)), 0, 6),
            'angle' => $angle,
        ];
    }

    /**
     * @param array<int, string> $requiredTerms
     * @return array<int, string>
     */
    protected function fallbackQueries(string $topic, array $requiredTerms): array
    {
        $variants = [$topic];
        $requiredPhrase = trim(implode(' ', array_slice($requiredTerms, 0, 3)));
        $topicTokens = $this->tokens($topic);

        if ($requiredPhrase !== '' && !Str::contains(Str::lower($topic), Str::lower($requiredPhrase))) {
            $variants[] = trim($topic . ' ' . $requiredPhrase);
        }

        $variants[] = trim($topic . ' latest news');
        $variants[] = trim($topic . ' recent news article');

        if (count($topicTokens) <= 3) {
            foreach (['business news', 'company leadership', 'executive appointments', 'industry developments'] as $suffix) {
                $variants[] = trim($topic . ' ' . $suffix);
            }
        }

        if (in_array('female', $topicTokens, true) && in_array('ceo', $topicTokens, true)) {
            $variants[] = 'women CEOs business news';
            $variants[] = 'female chief executive appointments';
            $variants[] = 'women company leadership latest news';
        }

        return array_values(array_unique(array_filter(array_map('trim', $variants))));
    }

    /**
     * @return array<int, string>
     */
    protected function defaultRequiredTerms(string $topic): array
    {
        return array_slice($this->tokens($topic), 0, 3);
    }

    /**
     * @param array<int, string> $sources
     * @return array<int, string>
     */
    protected function normalizeSources(array $sources): array
    {
        $allowed = ['google-news-rss', 'gnews', 'newsdata', 'currents_news'];

        $normalized = array_values(array_filter(array_unique(array_map(static function ($source): string {
            $source = trim((string) $source);
            return $source === 'currents' ? 'currents_news' : $source;
        }, $sources))));

        $normalized = array_values(array_filter($normalized, static fn (string $source): bool => in_array($source, $allowed, true)));

        return $normalized !== [] ? $normalized : $allowed;
    }



    protected function sanitizeQuery(string $query): string
    {
        $query = trim(preg_replace_callback('/\b(20\d{2})\b/', static function (array $matches): string {
            $year = (int) ($matches[1] ?? 0);
            return $year < ((int) now()->format('Y') - 1) ? '' : (string) $year;
        }, $query) ?? $query);
        $query = preg_replace('/\s+/', ' ', $query) ?: $query;

        return trim($query);
    }

    /**
     * @param array<string, mixed> $candidate
     */
    protected function isLowValueCandidate(array $candidate): bool
    {
        $text = Str::lower(trim((string) (($candidate['title'] ?? '') . ' ' . ($candidate['description'] ?? '') . ' ' . ($candidate['url'] ?? ''))));

        return (bool) preg_match('/(\btop\s+\d+\b|\bpower\s+list\b|\bblog\s+posts\b|\bhow\s+to\b|\bguide\b|\btips\b|\broundup\b|\bsponsored\b|\badvertorial\b|\baward\b|\bawards\b|\/awards?\/|\/lists?\/)/', $text);
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
                ],
            ];
        }

        if (count($articles) <= $limit) {
            return [
                'articles' => array_slice($articles, 0, $limit),
                'meta' => [
                    'anchor_title' => $articles[0]['title'] ?? null,
                    'mode' => 'within_limit',
                    'kept' => count($articles),
                ],
            ];
        }

        $pairScores = [];
        $anchorIndex = 0;
        $anchorScore = -1.0;

        foreach ($articles as $leftIndex => $leftArticle) {
            $scoreSum = 0.0;
            foreach ($articles as $rightIndex => $rightArticle) {
                if ($leftIndex == $rightIndex) {
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
        foreach ($articles as $index => $article) {
            if ($index === $anchorIndex) {
                continue;
            }

            $score = (float) ($pairScores[$anchorIndex][$index] ?? $this->articleSimilarity($anchor, $article));
            if ($score >= 0.18) {
                $coherent[] = $article;
            }
        }

        $minimumUseful = min($limit, 3);
        if (count($coherent) < $minimumUseful && count($articles) >= $minimumUseful) {
            return [
                'articles' => array_slice($articles, 0, $limit),
                'meta' => [
                    'anchor_title' => (string) ($anchor['title'] ?? ''),
                    'mode' => 'ranked_fallback',
                    'kept' => min(count($articles), $limit),
                ],
            ];
        }

        return [
            'articles' => array_slice(array_values($coherent), 0, $limit),
            'meta' => [
                'anchor_title' => (string) ($anchor['title'] ?? ''),
                'mode' => count($coherent) >= 2 ? 'clustered' : 'collapsed_to_anchor',
                'kept' => count($coherent),
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
        return $this->tokens(trim((string) (($article['title'] ?? '') . ' ' . ($article['description'] ?? ''))));
    }

    /**
     * @return array<int, string>
     */
    protected function tokens(string $text): array
    {
        $text = Str::lower($text);
        $text = preg_replace('/[^a-z0-9\s]+/', ' ', $text);
        $parts = preg_split('/\s+/', (string) $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $stopWords = $this->stopWords();

        return array_values(array_unique(array_filter($parts, static function (string $part) use ($stopWords): bool {
            return strlen($part) >= 3 && !in_array($part, $stopWords, true);
        })));
    }

    /**
     * @return array<int, string>
     */
    protected function stopWords(): array
    {
        return [
            'about', 'after', 'amid', 'analysis', 'article', 'articles', 'because', 'before', 'between', 'breaking',
            'commentary', 'could', 'daily', 'editorial', 'feature', 'from', 'have', 'into', 'latest', 'more', 'most',
            'news', 'over', 'reuters', 'says', 'show', 'story', 'than', 'that', 'their', 'them', 'these', 'they',
            'this', 'those', 'today', 'under', 'update', 'updates', 'what', 'when', 'where', 'which', 'while', 'with',
        ];
    }

    /**
     * @param array<int, string> $providers
     */
    protected function formatSearchProviderLabels(array $providers): string
    {
        $labels = [
            'google-news-rss' => 'Google News RSS',
            'gnews' => 'GNews',
            'newsdata' => 'NewsData',
            'currents_news' => 'Currents',
        ];

        $mapped = array_values(array_filter(array_map(static fn (string $provider): ?string => $labels[$provider] ?? null, $providers)));

        return $mapped === [] ? 'News providers' : implode(', ', array_values(array_unique($mapped)));
    }
}
