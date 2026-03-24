<?php

namespace hexa_app_publish\Console;

use Illuminate\Console\Command;
use hexa_app_publish\Models\PublishArticle;
use hexa_app_publish\Models\PublishCampaign;
use hexa_app_publish\Models\PublishUsedSource;
use hexa_package_gnews\Services\GNewsService;
use hexa_package_newsdata\Services\NewsDataService;

class RunCampaignsCommand extends Command
{
    protected $signature = 'publish:run-campaigns';
    protected $description = 'Process all due campaigns and spawn articles.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $dueCampaigns = PublishCampaign::with(['site', 'template', 'account'])
            ->where('status', 'active')
            ->where(function ($q) {
                $q->whereNull('next_run_at')
                  ->orWhere('next_run_at', '<=', now());
            })
            ->get();

        if ($dueCampaigns->isEmpty()) {
            $this->info('No campaigns due.');
            return 0;
        }

        $this->info("Processing {$dueCampaigns->count()} campaign(s)...");

        foreach ($dueCampaigns as $campaign) {
            $this->processCampaign($campaign);
        }

        return 0;
    }

    /**
     * Process a single campaign: source articles and create draft records.
     *
     * @param PublishCampaign $campaign
     */
    private function processCampaign(PublishCampaign $campaign): void
    {
        $this->line("  Campaign: {$campaign->name} ({$campaign->campaign_id})");

        $count = $campaign->articles_per_interval;

        for ($i = 0; $i < $count; $i++) {
            $this->spawnArticle($campaign);
        }

        // Calculate next run
        $nextRun = match ($campaign->interval_unit) {
            'hourly' => now()->addHour(),
            'daily' => now()->addDay(),
            'weekly' => now()->addWeek(),
            'monthly' => now()->addMonth(),
            default => now()->addDay(),
        };

        $campaign->update([
            'last_run_at' => now(),
            'next_run_at' => $nextRun,
        ]);

        $this->line("    Next run: {$nextRun->format('Y-m-d H:i')}");

        hexaLog('publish', 'campaign_run', "Campaign run: {$campaign->name} ({$campaign->campaign_id}) — spawned {$count} article(s)");
    }

    /**
     * Spawn a single article from campaign rules.
     *
     * @param PublishCampaign $campaign
     */
    private function spawnArticle(PublishCampaign $campaign): void
    {
        $articleId = PublishArticle::generateArticleId();

        $article = PublishArticle::create([
            'publish_account_id' => $campaign->publish_account_id,
            'publish_site_id' => $campaign->publish_site_id,
            'publish_campaign_id' => $campaign->id,
            'publish_template_id' => $campaign->publish_template_id,
            'article_id' => $articleId,
            'article_type' => $campaign->article_type ?? ($campaign->template->article_type ?? null),
            'delivery_mode' => $campaign->delivery_mode,
            'status' => 'sourcing',
            'created_by' => $campaign->created_by,
        ]);

        // Try to find source articles based on campaign topic/keywords
        $sourceArticles = $this->findSourceArticles($campaign);

        if (!empty($sourceArticles)) {
            // Pick the best unused one as the primary title source
            $primary = $sourceArticles[0];
            $article->update([
                'title' => $primary['title'] ?? 'Untitled',
                'source_articles' => $sourceArticles,
                'status' => 'drafting',
            ]);

            // Record used sources
            foreach ($sourceArticles as $src) {
                if (!empty($src['url'])) {
                    PublishUsedSource::create([
                        'publish_account_id' => $campaign->publish_account_id,
                        'publish_article_id' => $article->id,
                        'url' => $src['url'],
                        'title' => $src['title'] ?? null,
                        'source_api' => $src['source_api'] ?? null,
                    ]);
                }
            }

            $this->line("    Spawned: {$articleId} — \"{$primary['title']}\"");
        } else {
            $article->update([
                'title' => "Article from {$campaign->name}",
                'status' => 'drafting',
            ]);

            $this->line("    Spawned: {$articleId} (no sources found, manual draft)");
        }
    }

    /**
     * Search for source articles using campaign settings.
     *
     * @param PublishCampaign $campaign
     * @return array
     */
    private function findSourceArticles(PublishCampaign $campaign): array
    {
        $topic = $campaign->topic;
        if (!$topic) {
            return [];
        }

        $sources = $campaign->article_sources ?? ['google-news-rss'];
        $allArticles = [];

        // Google News RSS (free, always available)
        if (in_array('google-news-rss', $sources)) {
            try {
                $rssUrl = 'https://news.google.com/rss/search?q=' . urlencode($topic) . '&hl=en-US&gl=US&ceid=US:en';
                $xml = @simplexml_load_string(@file_get_contents($rssUrl));
                if ($xml && isset($xml->channel->item)) {
                    foreach ($xml->channel->item as $item) {
                        $url = (string) $item->link;
                        if (!PublishUsedSource::isUsed($campaign->publish_account_id, $url)) {
                            $allArticles[] = [
                                'source_api' => 'google-news-rss',
                                'title' => (string) $item->title,
                                'url' => $url,
                                'description' => strip_tags((string) $item->description),
                                'published_at' => (string) $item->pubDate,
                            ];
                        }
                        if (count($allArticles) >= 5) break;
                    }
                }
            } catch (\Exception $e) {
                // Silently skip RSS errors during scheduled runs
            }
        }

        if (in_array('gnews', $sources) && count($allArticles) < 5) {
            $result = app(GNewsService::class)->searchArticles($topic, 5);
            if ($result['success']) {
                foreach (($result['data']['articles'] ?? []) as $a) {
                    if (!empty($a['url']) && !PublishUsedSource::isUsed($campaign->publish_account_id, $a['url'])) {
                        $allArticles[] = $a;
                    }
                    if (count($allArticles) >= 5) break;
                }
            }
        }

        if (in_array('newsdata', $sources) && count($allArticles) < 5) {
            $result = app(NewsDataService::class)->searchArticles($topic, 5);
            if ($result['success']) {
                foreach (($result['data']['articles'] ?? []) as $a) {
                    if (!empty($a['url']) && !PublishUsedSource::isUsed($campaign->publish_account_id, $a['url'])) {
                        $allArticles[] = $a;
                    }
                    if (count($allArticles) >= 5) break;
                }
            }
        }

        return array_slice($allArticles, 0, 5);
    }
}
