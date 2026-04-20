<?php

namespace hexa_app_publish\Publishing\Campaigns\Services;

use Carbon\Carbon;
use hexa_app_publish\Discovery\Sources\Services\SourceExtractionService;
use hexa_app_publish\Publishing\Articles\Models\PublishArticle;
use hexa_app_publish\Publishing\Articles\Services\ArticleGenerationService;
use hexa_app_publish\Publishing\Articles\Services\ArticlePersistenceService;
use hexa_app_publish\Publishing\Campaigns\Models\PublishCampaign;
use hexa_app_publish\Publishing\Delivery\Services\WordPressDeliveryService;
use hexa_app_publish\Publishing\Delivery\Services\WordPressPreparationService;
use hexa_app_publish\Publishing\Sites\Models\PublishSite;
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
        protected CampaignDiscoveryService $discoveryService,
        protected CampaignScheduleService $scheduleService,
        protected CampaignModeResolver $modeResolver,
        protected CampaignPhotoAutomationService $photoAutomation
    ) {
    }

    /**
     * @return array{success: bool, message?: string, failed_stage?: string, log: array<int, array<string, mixed>>, article: ?PublishArticle}
     */
    public function run(PublishCampaign $campaign, string $mode = 'draft', ?Carbon $scheduledFor = null): array
    {
        return $this->execute($campaign, null, $mode, $scheduledFor, null);
    }

    /**
     * @return array{success: bool, message?: string, failed_stage?: string, log: array<int, array<string, mixed>>, article: ?PublishArticle}
     */
    public function runWithArticle(
        PublishArticle $article,
        int $campaignId,
        string $mode = 'draft',
        ?Carbon $scheduledFor = null,
        ?callable $onProgress = null
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

        return $this->execute($campaign, $article, $mode, $scheduledFor, $onProgress);
    }

    /**
     * @return array{success: bool, message?: string, failed_stage?: string, log: array<int, array<string, mixed>>, article: ?PublishArticle}
     */
    private function execute(
        PublishCampaign $campaign,
        ?PublishArticle $article,
        string $mode,
        ?Carbon $scheduledFor,
        ?callable $onProgress
    ): array {
        $log = [];
        $emit = function (string $type, string $message, array $extra = []) use (&$log, $onProgress): void {
            $entry = array_merge($this->entry($type, $message), $extra);
            $log[] = $entry;
            if ($onProgress) {
                $onProgress($type, $message, $extra);
            }
        };

        $resolvedMode = $this->modeResolver->normalizeDeliveryMode($mode);
        $executionMode = $this->modeResolver->toExecutionMode($resolvedMode);
        $emit('info', "Starting campaign: {$campaign->name} (mode: {$resolvedMode})", [
            'stage' => 'settings',
            'substage' => 'start',
        ]);

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
                'article_type=' . ($resolved['article_type'] ?? '—'),
                'delivery_mode=' . $resolvedMode,
                'ai_engine=' . ($resolved['ai_engine'] ?? '—'),
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
            'preset_id' => $resolved['preset_id'],
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
            'preset_id' => $resolved['preset_id'],
            'user_id' => $campaign->user_id,
            'created_by' => $campaign->created_by,
            'author' => $resolved['author'],
            'article_type' => $resolved['article_type'],
            'ai_engine_used' => $resolved['ai_engine'],
            'scheduled_for' => $scheduledFor,
        ]);

        $emit('success', "Article record ready: {$article->article_id}", [
            'stage' => 'article',
            'substage' => 'created',
            'details' => 'draft_id=' . $article->id,
        ]);

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
            $discovery = $this->discoveryService->discoverUrls($campaign, $resolved);
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
        ]);
        $sourceTexts = $this->sourceExtraction->extractTexts($sourceUrls, [
            'method' => 'auto',
            'source' => 'campaign',
            'draft_id' => $article->id,
        ]);
        foreach ($sourceTexts as $src) {
            $emit('success', 'Source extracted: ' . Str::limit($src['title'] ?: $src['url'], 60), [
                'stage' => 'extraction',
                'substage' => 'source_ok',
                'url' => $src['url'] ?? null,
            ]);
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

        $article->update([
            'source_articles' => $sourceTexts,
            'status' => 'spinning',
        ]);

        $emit('step', 'Generating article with AI...', [
            'stage' => 'generation',
            'substage' => 'start',
        ]);

        try {
            $spinResult = $this->spinArticle($resolved, $sourceTexts);
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

        $article->update([
            'title' => $spinResult['title'] ?? 'Untitled',
            'body' => $spinResult['html'],
            'word_count' => $spinResult['word_count'],
            'ai_cost' => $spinResult['cost'] ?? 0,
            'ai_tokens_input' => $spinResult['usage']['input_tokens'] ?? 0,
            'ai_tokens_output' => $spinResult['usage']['output_tokens'] ?? 0,
            'ai_provider' => $spinResult['provider'] ?? 'anthropic',
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
                'provider=' . ($spinResult['provider'] ?? $resolved['ai_engine'] ?? 'ai'),
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
        ]);
        $media = $this->photoAutomation->hydrate(
            (string) ($article->body ?? ''),
            (array) ($spinResult['photo_suggestions'] ?? []),
            $spinResult['featured_image'] ?? null,
            $spinResult['featured_meta'] ?? null,
            (array) ($resolved['photo_sources'] ?? []),
            (string) ($article->title ?? ''),
            (string) ($article->body ?? ''),
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

        $emit('success', 'Photo automation complete.', [
            'stage' => 'html_media',
            'substage' => 'complete',
            'details' => implode(' | ', array_filter([
                'inline=' . ($media['inline_count'] ?? 0),
                'featured=' . ($media['featured_count'] ?? 0),
            ])),
        ]);

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
            'article' => $article->fresh(),
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $sourceTexts
     * @return array<string, mixed>
     */
    private function spinArticle(array $resolved, array $sourceTexts): array
    {
        $model = $resolved['ai_engine'] ?? 'claude-sonnet-4-6';

        $result = $this->articleGeneration->generate($sourceTexts, [
            'model' => $model,
            'template_id' => $resolved['publish_template_id'] ?? null,
            'preset_id' => $resolved['preset_id'] ?? null,
            'article_type' => $resolved['article_type'] ?? null,
            'custom_prompt' => $resolved['ai_instructions'] ?? null,
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
