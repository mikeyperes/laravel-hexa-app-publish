<?php

namespace hexa_app_publish\Publishing\Campaigns\Services;

use hexa_app_publish\Models\PublishCampaign;
use hexa_app_publish\Models\PublishSite;
use hexa_app_publish\Publishing\Articles\Services\ArticleGenerationService;
use hexa_app_publish\Publishing\Articles\Services\ArticlePersistenceService;
use hexa_app_publish\Publishing\Delivery\Services\WordPressDeliveryService;
use hexa_app_publish\Discovery\Sources\Services\SourceDiscoveryService;
use hexa_app_publish\Discovery\Sources\Services\SourceExtractionService;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

/**
 * CampaignRunService — executes a campaign's full pipeline programmatically.
 *
 * Flow: find sources → extract content → spin with AI → prepare for WP → publish/draft.
 * Each step logs progress. Returns a structured result with log entries.
 */
class CampaignExecutionService
{
    protected WordPressDeliveryService $delivery;
    protected SourceDiscoveryService $sourceDiscovery;
    protected SourceExtractionService $sourceExtraction;
    protected ArticleGenerationService $articleGeneration;
    protected ArticlePersistenceService $persistence;

    /**
     * @param WordPressDeliveryService $delivery
     * @param SourceDiscoveryService $sourceDiscovery
     * @param SourceExtractionService $sourceExtraction
     * @param ArticleGenerationService $articleGeneration
     * @param ArticlePersistenceService $persistence
     */
    public function __construct(WordPressDeliveryService $delivery, SourceDiscoveryService $sourceDiscovery, SourceExtractionService $sourceExtraction, ArticleGenerationService $articleGeneration, ArticlePersistenceService $persistence)
    {
        $this->delivery = $delivery;
        $this->sourceDiscovery = $sourceDiscovery;
        $this->sourceExtraction = $sourceExtraction;
        $this->articleGeneration = $articleGeneration;
        $this->persistence = $persistence;
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
        $log[] = $this->entry('info', "Starting campaign: {$campaign->name} (mode: {$mode})");

        // 1. Resolve site + server
        $site = PublishSite::find($campaign->publish_site_id);
        if (!$site) {
            $log[] = $this->entry('error', 'Site not found.');
            return ['success' => false, 'log' => $log, 'article' => null];
        }
        $log[] = $this->entry('step', "Site: {$site->name} ({$site->url})");

        // 2. Find source articles (from campaign preset keywords/genre)
        $log[] = $this->entry('step', 'Finding source articles...');
        $sourceUrls = $this->findSources($campaign);
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
            'publish_template_id' => $campaign->publish_template_id,
            'preset_id' => $campaign->preset_id,
            'user_id' => $campaign->user_id,
            'created_by' => $campaign->created_by,
            'source_articles' => $sourceTexts,
            'ai_engine_used' => $campaign->ai_engine ?? 'claude-sonnet-4-6',
            'author' => $campaign->author,
            'user_ip' => '0.0.0.0',
            'scheduled_for' => $scheduledFor,
        ]);
        $log[] = $this->entry('info', "Article created: {$article->article_id}");

        // 5. Spin with AI
        $log[] = $this->entry('step', 'Spinning article with AI...');
        try {
            $spinResult = $this->spinArticle($campaign, $sourceTexts);
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
        $isWpAction = in_array($mode, ['publish', 'wp-draft']);

        if ($isWpAction) {
            $log[] = $this->entry('step', 'Publishing to WordPress...');
            try {
                $postStatus = $mode === 'wp-draft' ? 'draft' : ($campaign->post_status ?? 'draft');
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
            'next_run_at' => $this->calculateNextRun($campaign),
        ]);

        $log[] = $this->entry('success', "Campaign run complete. Article: {$article->article_id}");

        hexaLog('campaigns', 'campaign_run', "Campaign '{$campaign->name}' ran (mode: {$mode}), article: {$article->article_id}", [
            'campaign_id' => $campaign->id,
            'article_id' => $article->id,
            'mode' => $mode,
        ]);

        return ['success' => true, 'log' => $log, 'article' => $article];
    }

    /**
     * Find source article URLs based on campaign preset settings.
     *
     * @param PublishCampaign $campaign
     * @return array
     */
    private function findSources(PublishCampaign $campaign): array
    {
        $preset = $campaign->campaignPreset;
        $keywords = $campaign->keywords ?? ($preset ? $preset->keywords : []);
        $genre = $preset->genre ?? null;
        $method = $preset->source_method ?? 'trending';
        $searchQuery = implode(' ', $keywords ?: ['latest news']);

        return $this->sourceDiscovery->discoverUrls($searchQuery, [
            'mode'   => $method,
            'genre'  => $genre,
        ], 3);
    }

    /**
     * Spin article content using AI.
     *
     * @param PublishCampaign $campaign
     * @param array $sourceTexts
     * @return array
     */
    private function spinArticle(PublishCampaign $campaign, array $sourceTexts): array
    {
        $preset = $campaign->campaignPreset;
        $model = $campaign->ai_engine ?? 'claude-sonnet-4-6';

        $result = $this->articleGeneration->generate($sourceTexts, [
            'model'          => $model,
            'template_id'    => $campaign->publish_template_id,
            'custom_prompt'  => $preset && $preset->ai_instructions ? $preset->ai_instructions : null,
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

    /**
     * Calculate the next run time based on campaign schedule.
     *
     * @param PublishCampaign $campaign
     * @return \Carbon\Carbon
     */
    private function calculateNextRun(PublishCampaign $campaign): \Carbon\Carbon
    {
        $tz = $campaign->timezone ?? 'America/New_York';
        $now = now()->setTimezone($tz);

        return match ($campaign->interval_unit) {
            'hourly' => $now->addHour(),
            'daily' => $now->addDay()->setTimeFromTimeString($campaign->run_at_time ?? '09:00'),
            'weekly' => $now->addWeek()->setTimeFromTimeString($campaign->run_at_time ?? '09:00'),
            'monthly' => $now->addMonth()->setTimeFromTimeString($campaign->run_at_time ?? '09:00'),
            default => $now->addDay(),
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
