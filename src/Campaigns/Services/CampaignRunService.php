<?php

namespace hexa_app_publish\Campaigns\Services;

use hexa_app_publish\Models\PublishArticle;
use hexa_app_publish\Models\PublishCampaign;
use hexa_app_publish\Models\PublishSite;
use hexa_core\Models\Setting;
use hexa_package_anthropic\Services\AnthropicService;
use hexa_package_article_extractor\Services\ArticleExtractorService;
use hexa_package_wptoolkit\Services\WpToolkitService;
use hexa_package_wordpress\Services\WordPressService;
use hexa_package_whm\Models\HostingAccount;
use hexa_package_whm\Models\WhmServer;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

/**
 * CampaignRunService — executes a campaign's full pipeline programmatically.
 *
 * Flow: find sources → extract content → spin with AI → prepare for WP → publish/draft.
 * Each step logs progress. Returns a structured result with log entries.
 */
class CampaignRunService
{
    protected ArticleExtractorService $extractor;
    protected AnthropicService $anthropic;
    protected WpToolkitService $wptoolkit;
    protected WordPressService $wp;

    /**
     * @param ArticleExtractorService $extractor
     * @param AnthropicService $anthropic
     * @param WpToolkitService $wptoolkit
     * @param WordPressService $wp
     */
    public function __construct(ArticleExtractorService $extractor, AnthropicService $anthropic, WpToolkitService $wptoolkit, WordPressService $wp)
    {
        $this->extractor = $extractor;
        $this->anthropic = $anthropic;
        $this->wptoolkit = $wptoolkit;
        $this->wp = $wp;
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

        $account = HostingAccount::find($site->hosting_account_id);
        $server = $account ? WhmServer::find($account->whm_server_id) : null;
        $installId = $site->wordpress_install_id;

        // 2. Find source articles (from campaign preset keywords/genre)
        $log[] = $this->entry('step', 'Finding source articles...');
        $sourceUrls = $this->findSources($campaign);
        if (empty($sourceUrls)) {
            $log[] = $this->entry('error', 'No source articles found for campaign keywords/settings.');
            return ['success' => false, 'log' => $log, 'article' => null];
        }
        $log[] = $this->entry('success', 'Found ' . count($sourceUrls) . ' source URL(s)');

        // 3. Extract content from sources
        $log[] = $this->entry('step', 'Extracting article content...');
        $sourceTexts = [];
        foreach ($sourceUrls as $url) {
            try {
                $result = $this->extractor->extract($url);
                if ($result['success'] && !empty($result['data']['content_text'])) {
                    $sourceTexts[] = [
                        'url' => $url,
                        'title' => $result['data']['title'] ?? '',
                        'text' => $result['data']['content_text'],
                    ];
                    $log[] = $this->entry('success', 'Extracted: ' . Str::limit($result['data']['title'] ?? $url, 60));
                } else {
                    $log[] = $this->entry('warning', 'Failed to extract: ' . Str::limit($url, 60));
                }
            } catch (\Exception $e) {
                $log[] = $this->entry('warning', 'Extract error: ' . $e->getMessage());
            }
        }

        if (empty($sourceTexts)) {
            $log[] = $this->entry('error', 'No source content extracted.');
            return ['success' => false, 'log' => $log, 'article' => null];
        }

        // 4. Create article record
        $article = PublishArticle::create([
            'article_id' => PublishArticle::generateArticleId(),
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
                $article->update(['status' => 'failed']);
                return ['success' => false, 'log' => $log, 'article' => $article];
            }
        } catch (\Exception $e) {
            $log[] = $this->entry('error', 'Spin error: ' . $e->getMessage());
            $article->update(['status' => 'failed']);
            return ['success' => false, 'log' => $log, 'article' => $article];
        }

        // 6. Publish or save as draft (SSH via WP Toolkit, REST via WordPressService)
        $isWpAction = in_array($mode, ['publish', 'wp-draft']);
        $connectionType = $site->connection_type ?? 'wptoolkit';
        $canSsh = ($connectionType === 'wptoolkit') && $server && $installId;
        $canRest = ($connectionType !== 'wptoolkit') && $site->wp_username && $site->wp_application_password;

        if ($isWpAction && ($canSsh || $canRest)) {
            $log[] = $this->entry('step', 'Publishing to WordPress via ' . ($canSsh ? 'SSH' : 'REST') . '...');
            try {
                $postStatus = $mode === 'wp-draft' ? 'draft' : ($campaign->post_status ?? 'draft');

                if ($canSsh) {
                    $postResult = $this->wptoolkit->wpCliCreatePost(
                        $server,
                        $installId,
                        $article->title ?? 'Untitled',
                        $article->body ?? '',
                        $postStatus
                    );
                } else {
                    $postResult = $this->wp->createPost($site->url, $site->wp_username, $site->wp_application_password, [
                        'title'   => $article->title ?? 'Untitled',
                        'content' => $article->body ?? '',
                        'status'  => $postStatus,
                    ]);
                }

                if ($postResult['success']) {
                    $wpPostId = $postResult['data']['post_id'] ?? null;
                    // SSH returns only post_id; REST returns post_url — build fallback from site URL
                    $wpPostUrl = $postResult['data']['post_url'] ?? ($wpPostId ? rtrim($site->url, '/') . '/?p=' . $wpPostId : '');
                    $isActualPublish = ($postStatus === 'publish');
                    $article->update([
                        'wp_post_id' => $wpPostId,
                        'wp_post_url' => $wpPostUrl,
                        'wp_status' => $postStatus,
                        'status' => $isActualPublish ? 'completed' : 'drafting',
                        'published_at' => $isActualPublish ? now() : null,
                    ]);
                    $log[] = $this->entry('success', ($isActualPublish ? 'Published' : 'WP Draft') . ": #{$wpPostId} — {$wpPostUrl}");
                } else {
                    $log[] = $this->entry('error', 'WP publish failed: ' . ($postResult['message'] ?? ''));
                    $article->update(['status' => 'failed']);
                }
            } catch (\Exception $e) {
                $log[] = $this->entry('error', 'Publish error: ' . $e->getMessage());
            }
        } else if ($isWpAction && !$canSsh && !$canRest) {
            $log[] = $this->entry('warning', 'Site has no valid SSH or REST credentials — saving as local draft.');
            $article->update(['status' => 'drafting', 'delivery_mode' => 'draft-local']);
        } else {
            $article->update(['status' => 'drafting', 'delivery_mode' => 'draft-local']);
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

        // Try news search via available package
        $urls = [];
        $searchQuery = implode(' ', $keywords ?: ['latest news']);

        if (!class_exists(\hexa_package_currents_news\Services\CurrentsNewsService::class)) {
            Log::warning('[CampaignRun] CurrentsNewsService not available — install the currents-news package and configure API key.');
        } else {
            try {
                $newsService = app(\hexa_package_currents_news\Services\CurrentsNewsService::class);
                $results = $newsService->searchArticles($searchQuery, 'en', null, $genre);
                if ($results['success'] && !empty($results['data'])) {
                    foreach (array_slice($results['data'], 0, 3) as $article) {
                        if (!empty($article['url'])) {
                            $urls[] = $article['url'];
                        }
                    }
                }
            } catch (\Exception $e) {
                Log::warning('[CampaignRun] News search failed: ' . $e->getMessage());
            }
        }

        return $urls;
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
        $prompt = "You are a professional content writer. Rewrite the provided source articles into a single new unique article.\n\n";
        $prompt .= "CRITICAL OUTPUT FORMAT: You MUST output valid HTML only. Start with <h2> for section headings.\n\n";
        $prompt .= "METADATA: At the very end, output: <!-- METADATA: {\"titles\":[...5 titles],\"categories\":[...10 categories],\"tags\":[...10 tags],\"description\":\"SEO meta description\"} -->\n\n";

        // Add campaign preset instructions
        $preset = $campaign->campaignPreset;
        if ($preset && $preset->ai_instructions) {
            $prompt .= "ADDITIONAL INSTRUCTIONS: " . $preset->ai_instructions . "\n\n";
        }

        $prompt .= "SOURCE ARTICLES:\n\n";
        foreach ($sourceTexts as $i => $src) {
            $prompt .= "--- Source " . ($i + 1) . " ---\n";
            $prompt .= "Title: " . ($src['title'] ?? '') . "\n";
            $prompt .= Str::limit($src['text'], 3000) . "\n\n";
        }

        $model = $campaign->ai_engine ?? 'claude-sonnet-4-6';
        $result = $this->anthropic->chat('You are a professional content writer.', $prompt, $model);

        if (!$result['success']) {
            return ['success' => false, 'message' => $result['message'] ?? 'AI call failed'];
        }

        $content = $result['data']['content'] ?? '';
        $usage = $result['data']['usage'] ?? [];

        // Parse metadata
        $metadata = ['titles' => [], 'categories' => [], 'tags' => [], 'description' => ''];
        if (preg_match('/<!--\s*METADATA:\s*(\{.+?\})\s*-->/s', $content, $metaMatch)) {
            $parsed = json_decode(trim($metaMatch[1]), true);
            if ($parsed) $metadata = array_merge($metadata, $parsed);
            $content = preg_replace('/<!--\s*METADATA:\s*\{.+?\}\s*-->/s', '', $content);
        }

        $content = trim($content);
        $wordCount = str_word_count(strip_tags($content));

        // Calculate cost
        $inputTokens = $usage['input_tokens'] ?? 0;
        $outputTokens = $usage['output_tokens'] ?? 0;
        $cost = ($inputTokens * 0.003 + $outputTokens * 0.015) / 1000;

        return [
            'success' => true,
            'html' => $content,
            'word_count' => $wordCount,
            'title' => $metadata['titles'][0] ?? 'Untitled',
            'categories' => $metadata['categories'] ?? [],
            'tags' => $metadata['tags'] ?? [],
            'description' => $metadata['description'] ?? '',
            'usage' => $usage,
            'cost' => round($cost, 4),
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
