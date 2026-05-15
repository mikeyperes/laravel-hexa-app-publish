<?php

namespace hexa_app_publish\Publishing\Campaigns\Services;

use hexa_app_publish\Publishing\Articles\Models\PublishArticle;
use hexa_app_publish\Publishing\Articles\Models\PublishArticleActivity;
use hexa_app_publish\Publishing\Campaigns\Models\PublishCampaign;
use hexa_app_publish\Publishing\Sites\Models\PublishSite;
use hexa_package_whm\Models\HostingAccount;
use hexa_package_whm\Models\WhmServer;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class CampaignIntegrityReportService
{
    public function __construct(
        private CampaignSettingsResolver $settingsResolver,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function build(PublishCampaign $campaign, bool $forceAuthorRefresh = false): array
    {
        $campaign->loadMissing([
            'site',
            'template',
            'campaignPreset',
            'articles.site',
        ]);

        $report = [
            'generated_at' => now()->toIso8601String(),
            'summary' => [
                'status' => 'pass',
                'errors' => 0,
                'warnings' => 0,
                'blocking_errors' => 0,
                'articles_scanned' => (int) $campaign->articles->count(),
            ],
            'site' => [
                'name' => $campaign->site?->name,
                'url' => $campaign->site?->url,
                'default_author' => $campaign->site?->default_author,
                'author_cast_count' => count(array_values(array_filter((array) ($campaign->site?->author_cast ?? []), fn ($value) => filled($value)))),
                'last_connected_at' => $campaign->site?->last_connected_at?->toIso8601String(),
                'last_connected_display' => $campaign->site?->last_connected_at?->setTimezone($campaign->timezone ?: config('app.timezone', 'America/New_York'))->format('M j, Y g:i A T'),
                'last_connected_relative' => $campaign->site?->last_connected_at?->diffForHumans(),
                'author_count' => 0,
                'author_cache_hit' => null,
                'authors_cached_at' => null,
                'authors_expires_at' => null,
            ],
            'issues' => [],
            'article_flags' => [],
        ];

        try {
            $resolved = $this->settingsResolver->resolve($campaign);
        } catch (\Throwable $e) {
            $this->pushIssue($report, 'error', 'settings-unresolvable', 'Campaign settings could not be resolved.', $e->getMessage(), true);
            return $report;
        }

        $site = $campaign->site;
        if (!$site) {
            $this->pushIssue($report, 'error', 'site-missing', 'No publish site is attached.', 'Select a connected WordPress site before running this campaign.', true);
            return $report;
        }

        $selectedAuthor = trim((string) ($resolved['author'] ?? ''));
        $authorCast = collect((array) ($resolved['author_cast'] ?? $site->author_cast ?? []))
            ->map(fn ($author) => trim((string) $author))
            ->filter()
            ->unique(fn ($author) => mb_strtolower($author))
            ->values()
            ->all();
        $authorResult = $this->loadSiteAuthors($site, $forceAuthorRefresh);
        $authors = $authorResult['authors'];
        $report['site']['author_count'] = count($authors);
        $report['site']['author_cache_hit'] = $authorResult['cache_hit'];
        $report['site']['authors_cached_at'] = $authorResult['cached_at'];
        $report['site']['authors_expires_at'] = $authorResult['expires_at'];

        if (!$authorResult['success']) {
            $this->pushIssue(
                $report,
                'error',
                'author-connection-failed',
                'WordPress author lookup failed.',
                $authorResult['message'] ?: 'The campaign cannot verify publish-capable authors for the target site.',
                true
            );
        }

        if ($site->last_connected_at && $site->last_connected_at->lt(now()->subDays(14))) {
            $this->pushIssue(
                $report,
                'warning',
                'site-connection-stale',
                'The target site connection is stale.',
                'Last verified ' . $site->last_connected_at->diffForHumans() . '. Retest the site or refresh authors from source before relying on this campaign.'
            );
        }

        if ($selectedAuthor !== '' && $authorResult['success'] && !empty($authors)) {
            $matchedAuthor = $this->findAuthor($authors, $selectedAuthor);
            if (!$matchedAuthor) {
                $this->pushIssue(
                    $report,
                    'error',
                    'author-missing',
                    'The selected WordPress author could not be verified.',
                    'Campaign author "' . $selectedAuthor . '" is not present in the cached publish-capable author list for ' . ($site->name ?: 'the target site') . '.',
                    true
                );
            }
        } elseif ($selectedAuthor !== '' && !$site->isWpToolkit()) {
            $this->pushIssue(
                $report,
                'warning',
                'author-unverified',
                'The selected author could not be verified against a WordPress author directory.',
                'This site is not using the WP Toolkit author directory path, so author verification is advisory only.'
            );
        } elseif (!empty($authorCast) && $authorResult['success'] && !empty($authors)) {
            $missingPoolAuthors = collect($authorCast)
                ->reject(fn ($author) => $this->findAuthor($authors, $author))
                ->values()
                ->all();

            if (!empty($missingPoolAuthors)) {
                $this->pushIssue(
                    $report,
                    'warning',
                    'author-pool-partial',
                    'Some pooled authors could not be verified on WordPress.',
                    'These saved pool authors were not found on the target site: ' . implode(', ', array_slice($missingPoolAuthors, 0, 6))
                );
            }
        } elseif (!empty($authorCast) && !$authorResult['success']) {
            $this->pushIssue(
                $report,
                'warning',
                'author-pool-unverified',
                'The site author pool is configured, but WordPress author verification is currently unavailable.',
                'The campaign can still randomize from the saved pool once the connection is healthy again.'
            );
        } elseif (!$site->default_author) {
            $this->pushIssue(
                $report,
                'error',
                'author-empty',
                'No WordPress author is selected.',
                'Set a campaign author, a default site author, or a site author pool before running the campaign.',
                true
            );
        }

        $deliveryMode = (string) ($resolved['delivery_mode'] ?? $campaign->delivery_mode ?? '');
        $postStatus = (string) ($campaign->post_status ?: ($resolved['post_status'] ?? ''));
        if ($deliveryMode === 'draft-local' && $postStatus !== 'draft') {
            $this->pushIssue(
                $report,
                'warning',
                'local-status-ignored',
                'Local draft mode ignores the selected WordPress post status.',
                'This campaign is configured for a local-only draft run, so the post status "' . $postStatus . '" will not be used until you switch delivery back to WordPress.',
                false
            );
        }
        if (in_array($deliveryMode, ['draft-wordpress', 'auto-publish'], true) && !in_array($postStatus, ['draft', 'pending', 'publish', 'future'], true)) {
            $this->pushIssue(
                $report,
                'error',
                'wordpress-status-missing',
                'WordPress delivery is missing a valid post status.',
                'Choose whether WordPress should save this campaign as draft, pending, or publish.',
                true
            );
        }

        $searchTerms = array_values(array_filter((array) ($resolved['search_terms'] ?? []), fn ($value) => trim((string) $value) !== ''));
        $linkList = array_values(array_filter((array) ($campaign->link_list ?? []), fn ($value) => trim((string) $value) !== ''));
        if (empty($searchTerms) && empty($linkList)) {
            $this->pushIssue(
                $report,
                'error',
                'sources-empty',
                'The campaign has no search terms or source links.',
                'Add search terms, a topic, or a manual source list before running the campaign.',
                true
            );
        }

        if ($campaign->template && $campaign->template->article_type && $campaign->template->article_type !== 'editorial') {
            $this->pushIssue(
                $report,
                'error',
                'template-type-unsupported',
                'The selected article preset is not editorial-safe for campaigns.',
                'Template "' . $campaign->template->name . '" is typed as "' . $campaign->template->article_type . '", which is incompatible with campaign automation.',
                true
            );
        }

        $this->scanArticleStates($report, $campaign->articles);
        $this->scanArticleDrift($report, $campaign, $resolved);
        $this->scanDuplicateAngles($report, $campaign->articles);

        $report['summary']['status'] = $report['summary']['errors'] > 0
            ? 'error'
            : ($report['summary']['warnings'] > 0 ? 'warning' : 'pass');

        return $report;
    }

    /**
     * @return array{success: bool, authors: array<int, array<string, mixed>>, cache_hit: ?bool, cached_at: ?string, expires_at: ?string, message: ?string}
     */
    private function loadSiteAuthors(PublishSite $site, bool $forceRefresh = false): array
    {
        if (!$site->isWpToolkit() || !$site->hosting_account_id || !$site->wordpress_install_id) {
            return [
                'success' => $site->isRestApi(),
                'authors' => [],
                'cache_hit' => null,
                'cached_at' => null,
                'expires_at' => null,
                'message' => $site->isRestApi()
                    ? 'This site is using the REST API connection path, so author directory verification is limited.'
                    : 'The selected site is not a WP Toolkit-backed install.',
            ];
        }

        $account = HostingAccount::find($site->hosting_account_id);
        $server = $account ? WhmServer::find($account->whm_server_id) : null;
        if (!$server) {
            return [
                'success' => false,
                'authors' => [],
                'cache_hit' => null,
                'cached_at' => null,
                'expires_at' => null,
                'message' => 'No WHM/WP Toolkit server is linked to this site.',
            ];
        }

        $target = app(\hexa_app_publish\Publishing\Sites\Services\PublishSiteWordPressTargetFactory::class)->fromSite($site);
        $result = app(\hexa_package_wordpress\Services\WordPressManagerService::class)->listAuthors($target, $forceRefresh);

        return [
            'success' => (bool) ($result['success'] ?? false),
            'authors' => array_values(array_filter((array) ($result['authors'] ?? []), fn ($author) => is_array($author))),
            'cache_hit' => isset($result['cache_hit']) ? (bool) $result['cache_hit'] : null,
            'cached_at' => $result['cached_at'] ?? null,
            'expires_at' => $result['expires_at'] ?? null,
            'message' => $result['message'] ?? null,
        ];
    }

    /**
     * @param array<string, mixed> $report
     * @param array<string, mixed> $resolved
     */
    private function scanArticleDrift(array &$report, PublishCampaign $campaign, array $resolved): void
    {
        $articles = $campaign->articles
            ->filter(fn (PublishArticle $article) => trim((string) ($article->title ?? '')) !== 'Campaign run starting...')
            ->values();

        if ($articles->isEmpty()) {
            return;
        }

        $settingsSnapshots = PublishArticleActivity::query()
            ->whereIn('publish_article_id', $articles->pluck('id'))
            ->where('stage', 'settings')
            ->where('substage', 'resolved_settings')
            ->orderBy('id')
            ->get()
            ->groupBy('publish_article_id')
            ->map(function (Collection $rows) {
                $first = $rows->first();
                return is_array($first?->meta) ? Arr::get($first->meta, 'resolved', []) : [];
            });

        $currentCampaignType = trim((string) ($resolved['article_type'] ?? $campaign->article_type ?? ''));
        $currentCampaignAuthor = trim((string) ($resolved['author'] ?? $campaign->author ?? ''));
        $currentCampaignSiteId = (int) ($campaign->publish_site_id ?: 0);

        $typeDrift = [];
        $authorDrift = [];
        $siteDrift = [];

        foreach ($articles as $article) {
            $snapshot = (array) ($settingsSnapshots->get($article->id) ?: []);
            $expectedType = trim((string) ($snapshot['article_type'] ?? $currentCampaignType));
            $expectedAuthor = trim((string) ($snapshot['author'] ?? $currentCampaignAuthor));
            $currentType = trim((string) ($article->article_type ?? ''));
            $currentAuthor = trim((string) ($article->author ?? ''));
            $currentSiteId = (int) ($article->publish_site_id ?: 0);

            if ($expectedType !== '' && $currentType !== '' && $expectedType !== $currentType) {
                $typeDrift[] = '#' . $article->id . ' ' . ($article->title ?: 'Untitled') . ' (' . $currentType . ' vs ' . $expectedType . ')';
                $this->pushArticleFlag($report, $article->id, 'error', 'Type drift', 'This article no longer matches the campaign article type.');
            }

            if ($expectedAuthor !== '' && $currentAuthor !== '' && Str::lower($this->normalizeAuthorValue($expectedAuthor)) !== Str::lower($this->normalizeAuthorValue($currentAuthor))) {
                $authorDrift[] = '#' . $article->id . ' ' . ($article->title ?: 'Untitled') . ' (' . $currentAuthor . ' vs ' . $expectedAuthor . ')';
                $this->pushArticleFlag($report, $article->id, 'warning', 'Author drift', 'This article no longer uses the original campaign author.');
            }

            if ($currentCampaignSiteId > 0 && $currentSiteId > 0 && $currentSiteId !== $currentCampaignSiteId) {
                $siteDrift[] = '#' . $article->id . ' ' . ($article->title ?: 'Untitled') . ' (' . ($article->site?->name ?: 'site #' . $currentSiteId) . ')';
                $this->pushArticleFlag($report, $article->id, 'error', 'Site drift', 'This article now targets a different site than the campaign.');
            }
        }

        if (!empty($typeDrift)) {
            $this->pushIssue(
                $report,
                'error',
                'article-type-drift',
                'Some campaign articles have drifted into a different article type.',
                implode(' | ', array_slice($typeDrift, 0, 4)),
                false
            );
        }

        if (!empty($authorDrift)) {
            $this->pushIssue(
                $report,
                'warning',
                'article-author-drift',
                'Some campaign articles no longer use the original campaign author.',
                implode(' | ', array_slice($authorDrift, 0, 4))
            );
        }

        if (!empty($siteDrift)) {
            $this->pushIssue(
                $report,
                'error',
                'article-site-drift',
                'Some campaign articles have been moved to a different publish site.',
                implode(' | ', array_slice($siteDrift, 0, 4))
            );
        }
    }

    /**
     * @param Collection<int, PublishArticle> $articles
     * @param array<string, mixed> $report
     */
    private function scanArticleStates(array &$report, Collection $articles): void
    {
        $stale = [];
        $inProgressStates = ['drafting', 'sourcing', 'spinning', 'running', 'queued', 'pending', 'review'];

        foreach ($articles as $article) {
            if (!in_array((string) $article->status, $inProgressStates, true)) {
                continue;
            }

            if (!$article->created_at || $article->created_at->gt(now()->subHours(3))) {
                continue;
            }

            $stale[] = '#' . $article->id . ' ' . ($article->title ?: 'Untitled') . ' (' . $article->created_at->diffForHumans() . ')';
            $this->pushArticleFlag($report, $article->id, 'warning', 'Stale pipeline', 'This article has been stuck in an in-progress state for more than 3 hours.');
        }

        if (!empty($stale)) {
            $this->pushIssue(
                $report,
                'warning',
                'stale-pipeline-articles',
                'Some campaign article records are stuck in an in-progress state.',
                implode(' | ', array_slice($stale, 0, 4))
            );
        }
    }

    /**
     * @param Collection<int, PublishArticle> $articles
     * @param array<string, mixed> $report
     */
    private function scanDuplicateAngles(array &$report, Collection $articles): void
    {
        $eligible = $articles
            ->filter(fn (PublishArticle $article) => trim((string) ($article->title ?? '')) !== '')
            ->sortByDesc('created_at')
            ->take(24)
            ->values();

        $pairs = [];

        for ($i = 0; $i < $eligible->count(); $i++) {
            for ($j = $i + 1; $j < $eligible->count(); $j++) {
                $left = $eligible[$i];
                $right = $eligible[$j];

                $leftTokens = $this->meaningfulTokens((string) $left->title);
                $rightTokens = $this->meaningfulTokens((string) $right->title);
                if (count($leftTokens) < 3 || count($rightTokens) < 3) {
                    continue;
                }

                $overlap = count(array_intersect($leftTokens, $rightTokens));
                $union = count(array_unique(array_merge($leftTokens, $rightTokens)));
                $jaccard = $union > 0 ? ($overlap / $union) : 0;

                if ($overlap >= 3 || $jaccard >= 0.34) {
                    $pairs[] = '#' . $left->id . ' "' . $left->title . '" ↔ #' . $right->id . ' "' . $right->title . '"';
                    $this->pushArticleFlag($report, $left->id, 'warning', 'Similar angle', 'This article is very close to another recent campaign output.');
                    $this->pushArticleFlag($report, $right->id, 'warning', 'Similar angle', 'This article is very close to another recent campaign output.');
                }
            }
        }

        if (!empty($pairs)) {
            $this->pushIssue(
                $report,
                'warning',
                'duplicate-angles',
                'Recent campaign outputs are covering near-identical angles.',
                implode(' | ', array_slice(array_unique($pairs), 0, 4)) . ' — diversify search terms or tighten the editorial angle rules before running another article.'
            );
        }
    }

    /**
     * @param array<int, array<string, mixed>> $authors
     * @return array<string, mixed>|null
     */
    private function findAuthor(array $authors, string $needle): ?array
    {
        $needle = Str::lower(trim($needle));
        if ($needle === '') {
            return null;
        }

        foreach ($authors as $author) {
            $values = array_filter([
                $author['user_login'] ?? null,
                $author['display_name'] ?? null,
                $author['email'] ?? null,
            ]);

            foreach ($values as $value) {
                if (Str::lower(trim((string) $value)) === $needle) {
                    return $author;
                }
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $report
     */
    private function pushIssue(array &$report, string $severity, string $code, string $title, string $message, bool $blocking = false): void
    {
        $report['issues'][] = [
            'severity' => $severity,
            'code' => $code,
            'title' => $title,
            'message' => $message,
            'blocking' => $blocking,
        ];

        if ($severity === 'error') {
            $report['summary']['errors']++;
            if ($blocking) {
                $report['summary']['blocking_errors']++;
            }
        } elseif ($severity === 'warning') {
            $report['summary']['warnings']++;
        }
    }

    /**
     * @param array<string, mixed> $report
     */
    private function pushArticleFlag(array &$report, int $articleId, string $severity, string $label, string $message): void
    {
        $report['article_flags'][(string) $articleId] = $report['article_flags'][(string) $articleId] ?? [];
        foreach ($report['article_flags'][(string) $articleId] as $flag) {
            if (($flag['label'] ?? null) === $label) {
                return;
            }
        }

        $report['article_flags'][(string) $articleId][] = [
            'severity' => $severity,
            'label' => $label,
            'message' => $message,
        ];
    }

    /**
     * @return array<int, string>
     */
    private function meaningfulTokens(string $title): array
    {
        $stopWords = [
            'a', 'an', 'and', 'are', 'as', 'at', 'be', 'beyond', 'by', 'for', 'from', 'how', 'in', 'into', 'is',
            'its', 'it', 'of', 'on', 'or', 'that', 'the', 'their', 'this', 'to', 'what', 'when', 'why', 'with',
            'still', 'have', 'has', 'had', 'can', 'will', 'lead', 'leads',
        ];

        $tokens = preg_split('/[^a-z0-9]+/i', Str::lower($title)) ?: [];

        return array_values(array_unique(array_filter($tokens, function (string $token) use ($stopWords) {
            return $token !== ''
                && strlen($token) >= 3
                && !in_array($token, $stopWords, true);
        })));
    }

    private function normalizeAuthorValue(string $value): string
    {
        return trim(Str::lower(str_replace([' ', '_'], '-', $value)));
    }
}
