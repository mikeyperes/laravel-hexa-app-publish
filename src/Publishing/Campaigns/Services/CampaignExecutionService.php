<?php

namespace hexa_app_publish\Publishing\Campaigns\Services;

use hexa_app_publish\Publishing\Campaigns\Models\PublishCampaign;
use hexa_app_publish\Publishing\Sites\Models\PublishSite;
use hexa_app_publish\Publishing\Articles\Services\ArticleGenerationService;
use hexa_app_publish\Publishing\Articles\Services\ArticlePersistenceService;
use hexa_app_publish\Publishing\Delivery\Services\WordPressDeliveryService;
use hexa_app_publish\Discovery\Sources\Services\SourceExtractionService;
use Illuminate\Support\Str;

/**
 * CampaignRunService — executes a campaign's full pipeline programmatically.
 *
 * Flow: find sources → extract content → spin with AI → prepare for WP → publish/draft.
 * Each step logs progress. Returns a structured result with log entries.
 */
class CampaignExecutionService
{
    protected WordPressDeliveryService $delivery;
    protected SourceExtractionService $sourceExtraction;
    protected ArticleGenerationService $articleGeneration;
    protected ArticlePersistenceService $persistence;
    protected CampaignSettingsResolver $settingsResolver;
    protected CampaignDiscoveryService $discoveryService;
    protected CampaignScheduleService $scheduleService;
    protected CampaignModeResolver $modeResolver;

    /**
     * @param WordPressDeliveryService $delivery
     * @param SourceDiscoveryService $sourceDiscovery
     * @param SourceExtractionService $sourceExtraction
     * @param ArticleGenerationService $articleGeneration
     * @param ArticlePersistenceService $persistence
     */
    public function __construct(
        WordPressDeliveryService $delivery,
        SourceExtractionService $sourceExtraction,
        ArticleGenerationService $articleGeneration,
        ArticlePersistenceService $persistence,
        CampaignSettingsResolver $settingsResolver,
        CampaignDiscoveryService $discoveryService,
        CampaignScheduleService $scheduleService,
        CampaignModeResolver $modeResolver
    )
    {
        $this->delivery = $delivery;
        $this->sourceExtraction = $sourceExtraction;
        $this->articleGeneration = $articleGeneration;
        $this->persistence = $persistence;
        $this->settingsResolver = $settingsResolver;
        $this->discoveryService = $discoveryService;
        $this->scheduleService = $scheduleService;
        $this->modeResolver = $modeResolver;
    }

    /**
     * Run a campaign once. Creates one article through the full pipeline.
     *
     * @param PublishCampaign $campaign
     * @param string $mode 'draft' or 'publish'
     * @param \Carbon\Carbon|null $scheduledFor When to publish (drip scheduling)
     * @return array{success: bool, log: array, article: ?PublishArticle}
     */
    public function run(PublishCampaign $campaign, string $mode = 'draft', ?\Carbon\Carbon $scheduledFor = null): array
    {
        $log = [];
        $resolvedMode = $this->modeResolver->normalizeDeliveryMode($mode);
        $executionMode = $this->modeResolver->toExecutionMode($resolvedMode);
        $log[] = $this->entry('info', "Starting campaign: {$campaign->name} (mode: {$resolvedMode})");

        try {
            $resolved = $this->settingsResolver->resolve($campaign);
            $resolved['delivery_mode'] = $resolvedMode;
            $resolved['execution_mode'] = $executionMode;
            $resolved['post_status'] = $this->resolveRequestedPostStatus(
                $resolvedMode,
                $resolved['post_status'] ?? null
            );
        } catch (\Throwable $e) {
            $log[] = $this->entry('error', $e->getMessage());
            return ['success' => false, 'log' => $log, 'article' => null];
        }

        // 1. Resolve site + server
        $site = PublishSite::find($campaign->publish_site_id);
        if (!$site) {
            $log[] = $this->entry('error', 'Site not found.');
            return ['success' => false, 'log' => $log, 'article' => null];
        }
        $log[] = $this->entry('step', "Site: {$site->name} ({$site->url})");
        $log[] = $this->entry('info', 'Article type: ' . ($resolved['article_type'] ?? 'news-report'));

        // 2. Find source articles (from campaign preset keywords/genre)
        $log[] = $this->entry('step', 'Finding source articles...');
        $discovery = $this->discoveryService->discoverUrls($campaign, $resolved);
        $sourceUrls = $discovery['urls'];
        $log[] = $this->entry('info', 'Discovery query: ' . ($discovery['context']['query'] ?: 'latest news'));
        if (!empty($discovery['context']['selected_term'])) {
            $log[] = $this->entry('info', 'Selected search term: ' . $discovery['context']['selected_term']);
        }
        if (!empty($discovery['context']['category'])) {
            $log[] = $this->entry('info', 'Discovery category: ' . $discovery['context']['category']);
        }
        if (empty($sourceUrls)) {
            $log[] = $this->entry('error', 'No source articles found for campaign keywords/settings.');
            return ['success' => false, 'log' => $log, 'article' => null];
        }
        $log[] = $this->entry('success', 'Found ' . count($sourceUrls) . ' source URL(s)');

        // 3. Extract content from sources via shared SourceExtractionService
        $log[] = $this->entry('step', 'Extracting article content...');
        $sourceTexts = $this->sourceExtraction->extractTexts($sourceUrls);
        foreach ($sourceTexts as $src) {
            $log[] = $this->entry('success', 'Extracted: ' . Str::limit($src['title'] ?: $src['url'], 60));
        }
        $failedCount = count($sourceUrls) - count($sourceTexts);
        if ($failedCount > 0) {
            $log[] = $this->entry('warning', "{$failedCount} source(s) failed extraction.");
        }

        if (empty($sourceTexts)) {
            $log[] = $this->entry('error', 'No source content extracted.');
            return ['success' => false, 'log' => $log, 'article' => null];
        }

        // 4. Create article record
        $article = $this->persistence->create([
            'title' => 'Untitled',
            'status' => 'spinning',
            'publish_site_id' => $site->id,
            'publish_account_id' => $site->publish_account_id ?: null,
            'publish_campaign_id' => $campaign->id,
            'publish_template_id' => $resolved['publish_template_id'],
            'preset_id' => $resolved['preset_id'],
            'user_id' => $campaign->user_id,
            'created_by' => $campaign->created_by,
            'source_articles' => $sourceTexts,
            'ai_engine_used' => $resolved['ai_engine'],
            'author' => $resolved['author'],
            'article_type' => $resolved['article_type'],
            'user_ip' => '0.0.0.0',
            'scheduled_for' => $scheduledFor,
        ]);
        $log[] = $this->entry('info', "Article created: {$article->article_id}");

        // 5. Spin with AI
        $log[] = $this->entry('step', 'Spinning article with AI...');
        try {
            $spinResult = $this->spinArticle($resolved, $sourceTexts);
            if ($spinResult['success']) {
                $article->update([
                    'title' => $spinResult['title'] ?? 'Untitled',
                    'body' => $spinResult['html'],
                    'word_count' => $spinResult['word_count'],
                    'ai_cost' => $spinResult['cost'] ?? 0,
                    'ai_tokens_input' => $spinResult['usage']['input_tokens'] ?? 0,
                    'ai_tokens_output' => $spinResult['usage']['output_tokens'] ?? 0,
                    'ai_provider' => 'anthropic',
                    'categories' => $spinResult['categories'] ?? [],
                    'tags' => $spinResult['tags'] ?? [],
                    'excerpt' => $spinResult['description'] ?? '',
                    'status' => 'review',
                ]);
                $log[] = $this->entry('success', "Spun: {$spinResult['word_count']} words, cost: \${$spinResult['cost']}");
            } else {
                $log[] = $this->entry('error', 'Spin failed: ' . ($spinResult['message'] ?? 'unknown'));
                $this->persistence->markFailed($article);
                return ['success' => false, 'log' => $log, 'article' => $article];
            }
        } catch (\Exception $e) {
            $log[] = $this->entry('error', 'Spin error: ' . $e->getMessage());
            $this->persistence->markFailed($article);
            return ['success' => false, 'log' => $log, 'article' => $article];
        }

        // 6. Publish or save as draft via shared WordPressDeliveryService
        $isWpAction = in_array($executionMode, ['publish', 'wp-draft'], true);

        if ($isWpAction) {
            $log[] = $this->entry('step', 'Publishing to WordPress...');
            try {
                $postStatus = $executionMode === 'wp-draft' ? 'draft' : ($resolved['post_status'] ?? 'draft');
                $deliveryOptions = [];

                // Drip scheduling: use WordPress 'future' status with scheduled date
                if ($scheduledFor && $postStatus === 'publish') {
                    $postStatus = 'future';
                    $deliveryOptions['date'] = $scheduledFor->format('Y-m-d\TH:i:s');
                    $log[] = $this->entry('info', "Scheduled for: {$scheduledFor->format('Y-m-d H:i')}");
                }

                $postResult = $this->delivery->createPost($site, $article->title ?? 'Untitled', $article->body ?? '', $postStatus, $deliveryOptions);

                if ($postResult['success']) {
                    $this->persistence->updateDeliveryResult($article, $postResult, $postStatus);
                    $label = ($postStatus === 'publish') ? 'Published' : 'WP Draft';
                    $log[] = $this->entry('success', "{$label} via {$postResult['mode']}: #{$postResult['post_id']} — {$postResult['post_url']}");
                } else {
                    $log[] = $this->entry('error', 'WP publish failed: ' . $postResult['message']);
                    $this->persistence->markFailed($article);
                }
            } catch (\Exception $e) {
                $log[] = $this->entry('error', 'Publish error: ' . $e->getMessage());
            }
        } else {
            $this->persistence->markLocalDraft($article);
            $log[] = $this->entry('success', 'Saved as local draft.');
        }

        // Update campaign run times
        $campaign->update([
            'last_run_at' => now(),
            'next_run_at' => $this->scheduleService->nextRunAt($campaign),
        ]);

        $log[] = $this->entry('success', "Campaign run complete. Article: {$article->article_id}");

        hexaLog('campaigns', 'campaign_run', "Campaign '{$campaign->name}' ran (mode: {$resolvedMode}), article: {$article->article_id}", [
            'campaign_id' => $campaign->id,
            'article_id' => $article->id,
            'mode' => $resolvedMode,
        ]);

        return ['success' => true, 'log' => $log, 'article' => $article];
    }

    /**
     * Find source article URLs based on campaign preset settings.
     *
     * @param PublishCampaign $campaign
     * @return array
     */
    private function spinArticle(array $resolved, array $sourceTexts): array
    {
        $model = $resolved['ai_engine'] ?? 'claude-sonnet-4-6';

        $result = $this->articleGeneration->generate($sourceTexts, [
            'model'          => $model,
            'template_id'    => $resolved['publish_template_id'] ?? null,
            'preset_id'      => $resolved['preset_id'] ?? null,
            'custom_prompt'  => $resolved['ai_instructions'] ?? null,
            'agent'          => 'campaign-spin',
        ]);

        if (!$result['success']) {
            return ['success' => false, 'message' => $result['message'] ?? 'AI call failed'];
        }

        return [
            'success'     => true,
            'html'        => $result['html'],
            'word_count'  => $result['word_count'],
            'title'       => $result['metadata']['titles'][0] ?? 'Untitled',
            'categories'  => $result['metadata']['categories'] ?? [],
            'tags'        => $result['metadata']['tags'] ?? [],
            'description' => $result['metadata']['description'] ?? '',
            'usage'       => $result['usage'],
            'cost'        => $result['cost'],
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
     * @param string $type
     * @param string $message
     * @return array
     */
    private function entry(string $type, string $message): array
    {
        return ['type' => $type, 'message' => $message, 'time' => now()->format('H:i:s')];
    }
}
