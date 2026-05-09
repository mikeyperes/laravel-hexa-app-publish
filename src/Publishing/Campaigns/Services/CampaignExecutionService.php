<?php

namespace hexa_app_publish\Publishing\Campaigns\Services;

use Carbon\Carbon;
use hexa_app_publish\Discovery\Sources\Services\SourceExtractionService;
use hexa_app_publish\Publishing\Articles\Models\PublishArticle;
use hexa_app_publish\Publishing\Articles\Services\ArticleActivityService;
use hexa_app_publish\Publishing\Articles\Services\ArticleGenerationService;
use hexa_app_publish\Publishing\Articles\Services\ArticlePersistenceService;
use hexa_app_publish\Publishing\Campaigns\Models\PublishCampaign;
use hexa_app_publish\Publishing\Delivery\Services\WordPressDeliveryService;
use hexa_app_publish\Publishing\Delivery\Services\WordPressPreparationService;
use hexa_app_publish\Publishing\Sites\Models\PublishSite;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

/**
 * CampaignRunService — executes the real end-to-end campaign lifecycle.
 *
 * Flow: resolve settings → discover URLs → extract → create article →
 * generate article → auto-select photos → prepare for WordPress →
 * publish/draft → persist result → refresh schedule.
 */
class CampaignExecutionService
{
    public function __construct(
        protected WordPressDeliveryService $delivery,
        protected WordPressPreparationService $preparation,
        protected SourceExtractionService $sourceExtraction,
        protected ArticleGenerationService $articleGeneration,
        protected ArticlePersistenceService $persistence,
        protected CampaignSettingsResolver $settingsResolver,
        protected CampaignIntegrityReportService $integrityReportService,
        protected CampaignDiscoveryService $discoveryService,
        protected CampaignScheduleService $scheduleService,
        protected CampaignModeResolver $modeResolver,
        protected CampaignPhotoAutomationService $photoAutomation,
        protected ArticleActivityService $activities,
    ) {
    }

    /**
     * @return array{success: bool, message?: string, failed_stage?: string, log: array<int, array<string, mixed>>, article: ?PublishArticle}
     */
    public function run(PublishCampaign $campaign, string $mode = 'draft', ?Carbon $scheduledFor = null): array
    {
        return $this->execute($campaign, null, $mode, $scheduledFor, null, null);
    }

    /**
     * @return array{success: bool, message?: string, failed_stage?: string, log: array<int, array<string, mixed>>, article: ?PublishArticle}
     */
    public function runWithArticle(
        PublishArticle $article,
        int $campaignId,
        string $mode = 'draft',
        ?Carbon $scheduledFor = null,
        ?callable $onProgress = null,
        ?callable $shouldCancel = null
    ): array {
        $campaign = PublishCampaign::find($campaignId);
        if (!$campaign) {
            return [
                'success' => false,
                'message' => 'Campaign not found.',
                'failed_stage' => 'settings',
                'log' => [],
                'article' => $article,
            ];
        }

        return $this->execute($campaign, $article, $mode, $scheduledFor, $onProgress, $shouldCancel);
    }

    /**
     * @return array{success: bool, message?: string, failed_stage?: string, log: array<int, array<string, mixed>>, article: ?PublishArticle}
     */
    private function execute(
        PublishCampaign $campaign,
        ?PublishArticle $article,
        string $mode,
        ?Carbon $scheduledFor,
        ?callable $onProgress,
        ?callable $shouldCancel
    ): array {
        $log = [];
        $trackedArticle = $article;
        $emit = function (string $type, string $message, array $extra = []) use (&$log, $onProgress, &$trackedArticle): void {
            $entry = array_merge($this->entry($type, $message), $extra);
            $log[] = $entry;
            if ($trackedArticle) {
                $this->activities->record($trackedArticle, [
                    'activity_group' => 'campaign-run:' . ($trackedArticle->article_id ?: $trackedArticle->id),
                    'activity_type' => 'operation',
                    'stage' => $extra['stage'] ?? 'campaign',
                    'substage' => $extra['substage'] ?? null,
                    'status' => $type,
                    'success' => !in_array($type, ['error'], true),
                    'title' => $extra['title'] ?? null,
                    'url' => $extra['url'] ?? null,
                    'message' => $message,
                    'request_payload' => Arr::only($extra, ['title', 'description', 'url', 'status_code', 'checked_via', 'provider', 'model', 'method_used', 'fallback_used', 'search_backend_label', 'selected_anchor_title']),
                    'response_payload' => Arr::except($entry, ['time']),
                    'meta' => Arr::except($extra, ['title', 'description', 'url']),
                ]);
            }
            if ($onProgress) {
                $onProgress($type, $message, $extra);
            }
        };
        $cancelledFailure = function (string $stage) use (&$log, &$article, $emit): array {
            $emit('warning', 'Run stopped by user.', [
                'stage' => $stage,
                'substage' => 'cancelled',
            ]);
            if ($article) {
                $this->persistence->markFailed($article);
            }

            return $this->failure($log, $article, $stage, 'Run stopped by user.');
        };
        $shouldAbort = function () use ($shouldCancel): bool {
            return $shouldCancel ? (bool) $shouldCancel() : false;
        };

        $resolvedMode = $this->modeResolver->normalizeDeliveryMode($mode);
        $executionMode = $this->modeResolver->toExecutionMode($resolvedMode);
        $emit('info', "Starting campaign: {$campaign->name} (mode: {$resolvedMode})", [
            'stage' => 'settings',
            'substage' => 'start',
        ]);

        $integrity = $this->integrityReportService->build($campaign);
        if ((int) data_get($integrity, 'summary.blocking_errors', 0) > 0) {
            $blockingTitles = collect((array) data_get($integrity, 'issues', []))
                ->filter(fn ($issue) => (bool) ($issue['blocking'] ?? false))
                ->map(fn ($issue) => trim((string) ($issue['title'] ?? '')))
                ->filter()
                ->unique()
                ->values()
                ->all();
            $blockingMessage = !empty($blockingTitles)
                ? implode(' | ', $blockingTitles)
                : 'Campaign integrity check failed.';

            $emit('error', 'Campaign integrity check failed.', [
                'stage' => 'integrity',
                'substage' => 'preflight',
                'details' => $blockingMessage,
            ]);

            if ($article) {
                $this->persistence->markFailed($article);
            }

            return $this->failure($log, $article, 'integrity', $blockingMessage);
        }

        try {
            $resolved = $this->settingsResolver->resolve($campaign);
            $resolved['delivery_mode'] = $resolvedMode;
            $resolved['execution_mode'] = $executionMode;
            $resolved['post_status'] = $this->resolveRequestedPostStatus(
                $resolvedMode,
                $resolved['post_status'] ?? null
            );
        } catch (\Throwable $e) {
            $emit('error', $e->getMessage(), [
                'stage' => 'settings',
                'substage' => 'resolve_failed',
            ]);

            return $this->failure($log, $article, 'settings', $e->getMessage());
        }

        $emit('success', 'Campaign settings resolved.', [
            'stage' => 'settings',
            'substage' => 'resolved',
            'details' => implode(' | ', array_filter([
                'campaign_preset=' . ($resolved['campaign_preset']?->name ?? '—'),
                'article_preset=' . ($resolved['article_preset']?->name ?? '—'),
                'article_type=' . ($resolved['article_type'] ?? '—'),
                'delivery_mode=' . $resolvedMode,
                'spin=' . (($resolved['spin_model_primary'] ?? '—') . ' -> ' . ($resolved['spin_model_fallback'] ?? '—')),
                'search=' . (($resolved['online_search_model_primary'] ?? '—') . ' -> ' . ($resolved['online_search_model_fallback'] ?? '—')),
                'scrape=' . (($resolved['scrape_ai_model_primary'] ?? '—') . ' -> ' . ($resolved['scrape_ai_model_fallback'] ?? '—')),
            ])),
        ]);

        $site = PublishSite::find($campaign->publish_site_id);
        if (!$site) {
            $emit('error', 'Site not found.', [
                'stage' => 'site',
                'substage' => 'missing',
            ]);

            return $this->failure($log, $article, 'site', 'Site not found.');
        }

        $emit('success', "Site ready: {$site->name} ({$site->url})", [
            'stage' => 'site',
            'substage' => 'resolved',
            'details' => implode(' | ', array_filter([
                'connection=' . ($site->connection_type ?: 'wptoolkit'),
                'author=' . ($resolved['author'] ?: ($site->default_author ?: 'default')),
            ])),
        ]);

        $article = $article ?: $this->persistence->create([
            'title' => 'Campaign run starting...',
            'status' => 'sourcing',
            'delivery_mode' => $resolvedMode,
            'publish_site_id' => $site->id,
            'publish_account_id' => $site->publish_account_id ?: null,
            'publish_campaign_id' => $campaign->id,
            'publish_template_id' => $resolved['publish_template_id'],
            'preset_id' => null,
            'user_id' => $campaign->user_id,
            'created_by' => $campaign->created_by,
            'author' => $resolved['author'],
            'article_type' => $resolved['article_type'],
            'ai_engine_used' => $resolved['ai_engine'],
            'user_ip' => '0.0.0.0',
            'scheduled_for' => $scheduledFor,
        ]);

        $article->update([
            'status' => 'sourcing',
            'delivery_mode' => $resolvedMode,
            'publish_site_id' => $site->id,
            'publish_account_id' => $site->publish_account_id ?: null,
            'publish_campaign_id' => $campaign->id,
            'publish_template_id' => $resolved['publish_template_id'],
            'preset_id' => null,
            'user_id' => $campaign->user_id,
            'created_by' => $campaign->created_by,
            'author' => $resolved['author'],
            'article_type' => $resolved['article_type'],
            'ai_engine_used' => $resolved['ai_engine'],
            'scheduled_for' => $scheduledFor,
        ]);
        $trackedArticle = $article;
        $this->activities->record($article, [
            'activity_group' => 'campaign-run:' . ($article->article_id ?: $article->id),
            'activity_type' => 'lifecycle',
            'stage' => 'settings',
            'substage' => 'resolved_settings',
            'status' => 'success',
            'success' => true,
            'message' => 'Campaign settings resolved and article bound to campaign run.',
            'meta' => [
                'campaign_id' => $campaign->id,
                'delivery_mode' => $resolvedMode,
                'resolved' => Arr::except($resolved, ['campaign_preset', 'article_preset', 'template']),
                'campaign_preset' => $resolved['campaign_preset']?->only(['id', 'name']),
                'article_preset' => $resolved['article_preset']?->only(['id', 'name']),
            ],
        ]);

        $emit('success', "Article record ready: {$article->article_id}", [
            'stage' => 'article',
            'substage' => 'created',
            'details' => 'draft_id=' . $article->id,
        ]);

        if ($shouldAbort()) {
            return $cancelledFailure('article');
        }

        $sourceUrls = collect((array) $campaign->link_list)
            ->map(fn ($url) => trim((string) $url))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if (!empty($sourceUrls)) {
            $emit('success', 'Using explicit campaign link list.', [
                'stage' => 'discovery',
                'substage' => 'manual_links',
                'details' => count($sourceUrls) . ' URL(s)',
            ]);
        } else {
            $emit('step', 'Finding source articles...', [
                'stage' => 'discovery',
                'substage' => 'search',
            ]);
            $discovery = $this->discoveryService->discoverUrls($campaign, $resolved, 3, $article->id);
            $sourceUrls = $discovery['urls'];
            $emit('info', 'Discovery query: ' . ($discovery['context']['query'] ?: 'latest news'), [
                'stage' => 'discovery',
                'substage' => 'query',
            ]);
            if (!empty($discovery['context']['selected_term'])) {
                $emit('info', 'Selected search term: ' . $discovery['context']['selected_term'], [
                    'stage' => 'discovery',
                    'substage' => 'query',
                ]);
            }
            if (!empty($discovery['context']['category'])) {
                $emit('info', 'Discovery category: ' . $discovery['context']['category'], [
                    'stage' => 'discovery',
                    'substage' => 'query',
                ]);
            }
            if (!empty($discovery['details']['search_backend_label'])) {
                $emit('info', 'Discovery backend: ' . $discovery['details']['search_backend_label'], [
                    'stage' => 'discovery',
                    'substage' => 'backend',
                    'search_backend_label' => $discovery['details']['search_backend_label'],
                ]);
            }
            foreach ((array) ($discovery['details']['attempts'] ?? []) as $attempt) {
                $emit(
                    !empty($attempt['success']) && (($attempt['kept'] ?? 0) > 0) ? 'success' : 'warning',
                    'Discovery model attempt: ' . ($attempt['model'] ?? 'unknown'),
                    [
                        'stage' => 'discovery',
                        'substage' => 'attempt',
                        'details' => implode(' | ', array_filter([
                            'provider=' . ($attempt['provider'] ?? '—'),
                            'kept=' . (($attempt['kept'] ?? 0) . '/' . ($attempt['checked'] ?? 0)),
                            !empty($attempt['coherent_kept']) ? ('coherent=' . $attempt['coherent_kept']) : null,
                            !empty($attempt['coherence_mode']) ? ('coherence=' . $attempt['coherence_mode']) : null,
                            !empty($attempt['message']) ? ('note=' . $attempt['message']) : null,
                        ])),
                    ]
                );
            }

            foreach ((array) ($discovery['details']['articles'] ?? []) as $index => $candidate) {
                $emit('info', 'Selected source candidate ' . ($index + 1) . ': ' . ($candidate['title'] ?? $candidate['url'] ?? 'Untitled'), [
                    'stage' => 'discovery',
                    'substage' => 'candidate',
                    'title' => $candidate['title'] ?? null,
                    'description' => $candidate['description'] ?? null,
                    'url' => $candidate['url'] ?? null,
                    'status_code' => $candidate['status_code'] ?? null,
                    'checked_via' => $candidate['checked_via'] ?? null,
                ]);
            }

            if (!empty($discovery['details']['coherence'])) {
                $coherence = (array) $discovery['details']['coherence'];
                $emit('info', 'Source coherence resolved.', [
                    'stage' => 'discovery',
                    'substage' => 'coherence',
                    'selected_anchor_title' => $coherence['anchor_title'] ?? null,
                    'details' => implode(' | ', array_filter([
                        'mode=' . ($coherence['mode'] ?? '—'),
                        isset($coherence['kept']) ? ('kept=' . $coherence['kept']) : null,
                        isset($coherence['top_similarity']) ? ('top_similarity=' . $coherence['top_similarity']) : null,
                        !empty($coherence['dropped_titles']) ? ('dropped=' . implode('; ', (array) $coherence['dropped_titles'])) : null,
                    ])),
                ]);
            }
        }

        if (empty($sourceUrls)) {
            $emit('error', 'No source articles found for campaign keywords/settings.', [
                'stage' => 'discovery',
                'substage' => 'empty',
            ]);
            $this->persistence->markFailed($article);

            return $this->failure($log, $article, 'discovery', 'No source articles found.');
        }

        $emit('success', 'Found ' . count($sourceUrls) . ' source URL(s)', [
            'stage' => 'discovery',
            'substage' => 'complete',
        ]);
        foreach ($sourceUrls as $index => $sourceUrl) {
            $emit('info', 'Source URL ' . ($index + 1) . ': ' . $sourceUrl, [
                'stage' => 'discovery',
                'substage' => 'url',
                'url' => $sourceUrl,
            ]);
        }

        $article->update(['status' => 'sourcing']);
        $emit('step', 'Extracting article content...', [
            'stage' => 'extraction',
            'substage' => 'start',
            'details' => implode(' | ', array_filter([
                'method=auto',
                'user_agent=strategy-driven local fallback ladder',
                'ai=' . (($resolved['scrape_ai_model_primary'] ?? '—') . ' -> ' . ($resolved['scrape_ai_model_fallback'] ?? '—')),
            ])),
        ]);
        $sourceTexts = [];
        foreach ($sourceUrls as $index => $sourceUrl) {
            if ($shouldAbort()) {
                return $cancelledFailure('extraction');
            }

            $emit('info', 'Extraction attempt ' . ($index + 1) . ': ' . $sourceUrl, [
                'stage' => 'extraction',
                'substage' => 'attempt',
                'url' => $sourceUrl,
            ]);

            $result = $this->sourceExtraction->extract($sourceUrl, [
                'method' => 'auto',
                'source' => 'campaign',
                'draft_id' => $article->id,
                'ai_fallback_models' => array_values(array_filter([
                    $resolved['scrape_ai_model_primary'] ?? null,
                    $resolved['scrape_ai_model_fallback'] ?? null,
                ])),
            ]);

            if (!empty($result['success']) && !empty($result['text'])) {
                $sourceTexts[] = [
                    'url' => $result['url'],
                    'title' => $result['title'],
                    'text' => $result['text'],
                    'word_count' => $result['word_count'] ?? 0,
                    'method_used' => $result['method_used'] ?? null,
                    'fetch_info' => $result['fetch_info'] ?? null,
                ];

                $emit('success', 'Source extracted: ' . Str::limit($result['title'] ?: $result['url'], 80), [
                    'stage' => 'extraction',
                    'substage' => 'source_ok',
                    'title' => $result['title'] ?? null,
                    'url' => $result['url'] ?? null,
                    'method_used' => $result['method_used'] ?? null,
                    'word_count' => $result['word_count'] ?? null,
                    'status_code' => $result['http_status'] ?? null,
                    'fallback_used' => $result['fallback_tried'] ?? null,
                    'provider' => $result['fetch_info']['provider'] ?? null,
                    'model' => $result['fetch_info']['model'] ?? null,
                    'details' => implode(' | ', array_filter([
                        'method=' . ($result['method_used'] ?? 'auto'),
                        isset($result['word_count']) ? ('words=' . $result['word_count']) : null,
                        isset($result['http_status']) ? ('status=' . $result['http_status']) : null,
                        !empty($result['fetch_info']['provider']) ? ('provider=' . $result['fetch_info']['provider']) : null,
                        !empty($result['fetch_info']['model']) ? ('model=' . $result['fetch_info']['model']) : null,
                        !empty($result['fallback_tried']) ? ('fallback=' . $result['fallback_tried']) : null,
                    ])),
                ]);
            } else {
                $emit('warning', 'Extraction failed: ' . Str::limit($sourceUrl, 100), [
                    'stage' => 'extraction',
                    'substage' => 'source_failed',
                    'url' => $sourceUrl,
                    'method_used' => $result['method_used'] ?? null,
                    'status_code' => $result['http_status'] ?? null,
                    'fallback_used' => $result['fallback_tried'] ?? null,
                    'provider' => $result['fetch_info']['provider'] ?? null,
                    'model' => $result['fetch_info']['model'] ?? null,
                    'details' => implode(' | ', array_filter([
                        !empty($result['message']) ? ('reason=' . $result['message']) : null,
                        !empty($result['method_used']) ? ('method=' . $result['method_used']) : null,
                        isset($result['http_status']) ? ('status=' . $result['http_status']) : null,
                        !empty($result['fallback_tried']) ? ('fallback=' . $result['fallback_tried']) : null,
                        !empty($result['fetch_info']['provider']) ? ('provider=' . $result['fetch_info']['provider']) : null,
                        !empty($result['fetch_info']['model']) ? ('model=' . $result['fetch_info']['model']) : null,
                    ])),
                ]);
            }
        }

        $failedCount = count($sourceUrls) - count($sourceTexts);
        if ($failedCount > 0) {
            $emit('warning', "{$failedCount} source(s) failed extraction.", [
                'stage' => 'extraction',
                'substage' => 'partial',
            ]);
        }

        if (empty($sourceTexts)) {
            $emit('error', 'No source content extracted.', [
                'stage' => 'extraction',
                'substage' => 'empty',
            ]);
            $this->persistence->markFailed($article);

            return $this->failure($log, $article, 'extraction', 'No source content extracted.');
        }

        $emit('success', 'Source extraction complete.', [
            'stage' => 'extraction',
            'substage' => 'complete',
            'details' => implode(' | ', array_filter([
                'kept=' . count($sourceTexts),
                $failedCount > 0 ? ('failed=' . $failedCount) : null,
            ])),
        ]);

        $article->update([
            'source_articles' => $sourceTexts,
            'status' => 'spinning',
        ]);

        if ($shouldAbort()) {
            return $cancelledFailure('generation');
        }

        $emit('step', 'Generating article with AI...', [
            'stage' => 'generation',
            'substage' => 'start',
        ]);

        try {
            $spinResult = $this->spinArticle($resolved, $sourceTexts, $article->id);
            if (!$spinResult['success']) {
                $emit('error', 'Spin failed: ' . ($spinResult['message'] ?? 'unknown'), [
                    'stage' => 'generation',
                    'substage' => 'failed',
                ]);
                $this->persistence->markFailed($article);

                return $this->failure($log, $article, 'generation', $spinResult['message'] ?? 'Spin failed.');
            }
        } catch (\Throwable $e) {
            $emit('error', 'Spin error: ' . $e->getMessage(), [
                'stage' => 'generation',
                'substage' => 'exception',
            ]);
            $this->persistence->markFailed($article);

            return $this->failure($log, $article, 'generation', $e->getMessage());
        }

        $modelUsed = $spinResult['model'] ?? $resolved['ai_engine'];
        $providerUsed = app(\hexa_app_publish\Support\AiModelCatalog::class)->providerForModel($modelUsed);

        $article->update([
            'title' => $spinResult['title'] ?? 'Untitled',
            'body' => $spinResult['html'],
            'word_count' => $spinResult['word_count'],
            'ai_engine_used' => $modelUsed,
            'ai_cost' => $spinResult['cost'] ?? 0,
            'ai_tokens_input' => $spinResult['usage']['input_tokens'] ?? 0,
            'ai_tokens_output' => $spinResult['usage']['output_tokens'] ?? 0,
            'ai_provider' => $providerUsed,
            'categories' => $spinResult['categories'] ?? [],
            'tags' => $spinResult['tags'] ?? [],
            'excerpt' => $spinResult['description'] ?? '',
            'seo_data' => $spinResult['metadata'] ?? [],
            'resolved_prompt' => $spinResult['resolved_prompt'] ?? null,
            'photo_suggestions' => $spinResult['photo_suggestions'] ?? [],
            'featured_image_search' => $spinResult['featured_image'] ?? null,
            'status' => 'review',
        ]);

        $emit('success', "Generated {$spinResult['word_count']} words.", [
            'stage' => 'generation',
            'substage' => 'complete',
            'details' => implode(' | ', array_filter([
                'title=' . ($spinResult['title'] ?? 'Untitled'),
                'provider=' . ($providerUsed ?? $resolved['ai_engine'] ?? 'ai'),
                'models=' . implode(' -> ', (array) ($spinResult['attempted_models'] ?? [])),
                'cost=$' . ($spinResult['cost'] ?? 0),
            ])),
        ]);

        $emit('info', ucfirst(str_replace('-', ' ', (string) ($resolved['article_type'] ?? 'article'))) . ' title: ' . ($spinResult['title'] ?? 'Untitled'), [
            'stage' => 'generation',
            'substage' => 'title',
        ]);
        if (!empty($spinResult['metadata']['titles'])) {
            $emit('info', 'Title options confirmed.', [
                'stage' => 'generation',
                'substage' => 'title_options',
                'details' => implode(' | ', array_slice((array) $spinResult['metadata']['titles'], 0, 5)),
            ]);
        }
        if (!empty($spinResult['categories'])) {
            $emit('info', 'Categories chosen.', [
                'stage' => 'generation',
                'substage' => 'categories',
                'details' => implode(', ', array_slice((array) $spinResult['categories'], 0, 10)),
            ]);
        }
        if (!empty($spinResult['tags'])) {
            $emit('info', 'Tags chosen.', [
                'stage' => 'generation',
                'substage' => 'tags',
                'details' => implode(', ', array_slice((array) $spinResult['tags'], 0, 12)),
            ]);
        }
        if (!empty($spinResult['description'])) {
            $emit('info', 'Meta description confirmed.', [
                'stage' => 'generation',
                'substage' => 'description',
                'details' => (string) $spinResult['description'],
            ]);
        }

        $emit('step', 'Selecting featured and inline photos...', [
            'stage' => 'photos',
            'substage' => 'start',
            'details' => implode(' | ', array_filter([
                'featured=google_first',
                'inline=' . (($resolved['inline_photo_min'] ?? 2) . '-' . ($resolved['inline_photo_max'] ?? 3)),
                'landscape=' . (($resolved['featured_image_must_be_landscape'] ?? true) ? 'required' : 'optional'),
                'blacklist=on',
            ])),
        ]);
        $media = $this->photoAutomation->hydrate(
            (string) ($article->body ?? ''),
            (array) ($spinResult['photo_suggestions'] ?? []),
            $spinResult['featured_image'] ?? null,
            $spinResult['featured_meta'] ?? null,
            (array) ($resolved['photo_sources'] ?? []),
            (string) ($article->title ?? ''),
            (string) ($article->body ?? ''),
            $article->id,
            function (string $type, string $message, array $extra = []) use ($emit): void {
                $emit($type, $message, $extra);
            }
        );

        $article->update([
            'body' => $media['html'],
            'photo_suggestions' => $media['photo_suggestions'],
            'photos' => [
                'featured' => $media['featured_photo'],
                'inline_count' => $media['inline_count'],
            ],
            'featured_image_search' => $spinResult['featured_image'] ?? null,
        ]);

        $inlineMinimum = max(0, (int) ($resolved['inline_photo_min'] ?? 0));
        if (($resolved['featured_image_required'] ?? true) && empty($media['featured_photo'])) {
            $emit('error', 'Featured image required but none passed automation.', [
                'stage' => 'photos',
                'substage' => 'featured_required_failed',
            ]);
            $this->persistence->markFailed($article);

            return $this->failure($log, $article, 'photos', 'Featured image required but none passed automation.');
        }

        if (($media['inline_count'] ?? 0) < $inlineMinimum) {
            $emit('error', 'Inline photo minimum not met.', [
                'stage' => 'photos',
                'substage' => 'inline_min_failed',
                'details' => 'required=' . $inlineMinimum . ' | actual=' . ($media['inline_count'] ?? 0),
            ]);
            $this->persistence->markFailed($article);

            return $this->failure($log, $article, 'photos', 'Inline photo minimum not met.');
        }

        $emit('success', 'Photo automation complete.', [
            'stage' => 'html_media',
            'substage' => 'complete',
            'details' => implode(' | ', array_filter([
                'inline=' . ($media['inline_count'] ?? 0),
                'featured=' . ($media['featured_count'] ?? 0),
            ])),
        ]);

        if ($shouldAbort()) {
            return $cancelledFailure('photos');
        }

        $isWpAction = in_array($executionMode, ['publish', 'wp-draft'], true);
        if ($isWpAction) {
            $article->update(['status' => 'drafting']);
            $postStatus = $executionMode === 'wp-draft' ? 'draft' : ($resolved['post_status'] ?? 'draft');
            $deliveryOptions = [];

            if ($scheduledFor && $postStatus === 'publish') {
                $postStatus = 'future';
                $deliveryOptions['date'] = $scheduledFor->format('Y-m-d\TH:i:s');
                $emit('info', "Scheduled for: {$scheduledFor->format('Y-m-d H:i')}", [
                    'stage' => 'delivery',
                    'substage' => 'scheduled',
                ]);
            }

            try {
                $prepared = $this->preparation->prepare($site, (string) ($media['html'] ?? $article->body ?? ''), [
                    'title' => $article->title ?? 'Untitled',
                    'categories' => $article->categories ?? [],
                    'tags' => $article->tags ?? [],
                    'photo_suggestions' => $media['photo_suggestions'] ?? [],
                    'featured_meta' => $media['featured_meta'] ?? null,
                    'featured_url' => $media['featured_url'] ?? null,
                    'draft_id' => $article->id,
                    'should_cancel' => $shouldCancel,
                ], function (string $type, string $message, array $extra = []) use ($emit): void {
                    $emit($type, $message, $extra);
                });
            } catch (\Throwable $e) {
                $emit('error', 'Prepare error: ' . $e->getMessage(), [
                    'stage' => 'integrity',
                    'substage' => 'exception',
                ]);
                $this->persistence->markFailed($article);

                return $this->failure($log, $article, 'integrity', $e->getMessage());
            }

            if (!$prepared['success']) {
                $emit('error', $prepared['message'] ?? 'WordPress prepare failed.', [
                    'stage' => 'integrity',
                    'substage' => 'failed',
                ]);
                $this->persistence->markFailed($article);

                return $this->failure($log, $article, 'integrity', $prepared['message'] ?? 'WordPress prepare failed.');
            }

            $article->update([
                'body' => $prepared['html'],
                'wp_images' => $prepared['wp_images'] ?? [],
                'status' => 'drafting',
            ]);

            if ($shouldAbort()) {
                return $cancelledFailure('integrity');
            }

            $emit('step', $postStatus === 'publish' ? 'Publishing to WordPress...' : 'Creating WordPress draft...', [
                'stage' => 'delivery',
                'substage' => 'start',
            ]);

            try {
                $postResult = $this->delivery->createPost(
                    $site,
                    $article->title ?? 'Untitled',
                    $prepared['html'] ?? '',
                    $postStatus,
                    array_merge($deliveryOptions, [
                        'category_ids' => $prepared['category_ids'] ?? [],
                        'tag_ids' => $prepared['tag_ids'] ?? [],
                        'featured_media_id' => $prepared['featured_media_id'] ?? null,
                        'author' => $resolved['author'] ?? null,
                    ])
                );
            } catch (\Throwable $e) {
                $emit('error', 'Publish error: ' . $e->getMessage(), [
                    'stage' => 'delivery',
                    'substage' => 'exception',
                ]);
                $this->persistence->markFailed($article);

                return $this->failure($log, $article, 'delivery', $e->getMessage());
            }

            if (!$postResult['success']) {
                $emit('error', 'WP publish failed: ' . $postResult['message'], [
                    'stage' => 'delivery',
                    'substage' => 'failed',
                ]);
                $this->persistence->markFailed($article);

                return $this->failure($log, $article, 'delivery', $postResult['message']);
            }

            $this->persistence->updateDeliveryResult($article, $postResult, $postStatus);
            $article->update([
                'body' => $prepared['html'],
                'wp_images' => $prepared['wp_images'] ?? [],
                'wp_status' => $postStatus,
            ]);

            $label = ($postStatus === 'publish') ? 'Published' : 'WP Draft';
            $emit('success', "{$label} via {$postResult['mode']}: #{$postResult['post_id']} — {$postResult['post_url']}", [
                'stage' => 'delivery',
                'substage' => 'complete',
                'url' => $postResult['post_url'] ?? null,
            ]);
        } else {
            $this->persistence->markLocalDraft($article);
            $article->update([
                'body' => $media['html'],
                'status' => 'drafting',
            ]);
            $emit('success', 'Saved as local draft.', [
                'stage' => 'delivery',
                'substage' => 'local_draft',
            ]);
        }

        $emit('success', 'Article state persisted.', [
            'stage' => 'persistence',
            'substage' => 'complete',
        ]);

        $campaign->update([
            'last_run_at' => now(),
            'next_run_at' => $this->scheduleService->nextRunAt($campaign),
        ]);

        $emit('success', "Campaign run complete. Article: {$article->article_id}", [
            'stage' => 'schedule',
            'substage' => 'complete',
            'details' => $campaign->next_run_at ? ('next_run_at=' . $campaign->next_run_at->toIso8601String()) : null,
        ]);

        hexaLog('campaigns', 'campaign_run', "Campaign '{$campaign->name}' ran (mode: {$resolvedMode}), article: {$article->article_id}", [
            'campaign_id' => $campaign->id,
            'article_id' => $article->id,
            'mode' => $resolvedMode,
        ]);

        return [
            'success' => true,
            'message' => 'Campaign run complete.',
            'log' => $log,
            'article_id' => $article->id,
            'public_article_id' => $article->article_id,
            'article' => $article->fresh(),
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $sourceTexts
     * @return array<string, mixed>
     */
    private function spinArticle(array $resolved, array $sourceTexts, ?int $articleId = null): array
    {
        $model = $resolved['ai_engine'] ?? 'claude-sonnet-4-6';

        $result = $this->articleGeneration->generate($sourceTexts, [
            'article_id' => $articleId,
            'model' => $resolved['spin_model_primary'] ?? $model,
            'fallback_models' => array_values(array_filter([
                $resolved['spin_model_fallback'] ?? null,
            ])),
            'template_id' => $resolved['publish_template_id'] ?? null,
            'template_values' => $resolved['article_preset_values'] ?? [],
            'preset_id' => null,
            'article_type' => $resolved['article_type'] ?? null,
            'custom_prompt' => $resolved['ai_instructions'] ?? null,
            'web_research' => $resolved['search_online_for_additional_context'] ?? true,
            'agent' => 'campaign-spin',
        ]);

        if (!$result['success']) {
            return ['success' => false, 'message' => $result['message'] ?? 'AI call failed'];
        }

        return [
            'success' => true,
            'html' => $result['html'],
            'word_count' => $result['word_count'],
            'title' => $result['metadata']['titles'][0] ?? 'Untitled',
            'categories' => $result['metadata']['categories'] ?? [],
            'tags' => $result['metadata']['tags'] ?? [],
            'description' => $result['metadata']['description'] ?? '',
            'usage' => $result['usage'],
            'cost' => $result['cost'],
            'provider' => $result['provider'] ?? null,
            'metadata' => $result['metadata'] ?? [],
            'resolved_prompt' => $result['resolved_prompt'] ?? null,
            'photo_suggestions' => $result['photo_suggestions'] ?? [],
            'featured_image' => $result['featured_image'] ?? null,
            'featured_meta' => $result['featured_meta'] ?? null,
            'attempted_models' => $result['attempted_models'] ?? [$resolved['spin_model_primary'] ?? $model],
        ];
    }

    private function resolveRequestedPostStatus(string $deliveryMode, ?string $postStatus): string
    {
        return match ($deliveryMode) {
            'auto-publish' => 'publish',
            'draft-wordpress' => 'draft',
            default => $postStatus ?: 'draft',
        };
    }

    /**
     * @param array<int, array<string, mixed>> $log
     * @return array{success: false, message: string, failed_stage: string, log: array<int, array<string, mixed>>, article: ?PublishArticle}
     */
    private function failure(array $log, ?PublishArticle $article, string $stage, string $message): array
    {
        return [
            'success' => false,
            'message' => $message,
            'failed_stage' => $stage,
            'log' => $log,
            'article' => $article?->fresh(),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function entry(string $type, string $message): array
    {
        return [
            'type' => $type,
            'message' => $message,
            'time' => now()->format('H:i:s'),
        ];
    }
}
