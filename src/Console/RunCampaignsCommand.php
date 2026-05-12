<?php

namespace hexa_app_publish\Console;

use Illuminate\Console\Command;
use hexa_app_publish\Publishing\Campaigns\Models\PublishCampaign;
use hexa_app_publish\Publishing\Articles\Models\PublishArticle;
use hexa_app_publish\Publishing\Campaigns\Services\CampaignExecutionService;
use hexa_app_publish\Publishing\Campaigns\Services\CampaignModeResolver;
use hexa_app_publish\Publishing\Campaigns\Services\CampaignScheduleService;
use Illuminate\Support\Facades\Cache;

/**
 * RunCampaignsCommand — processes all due campaigns via cron.
 *
 * Usage: php artisan publish:run-campaigns
 * Schedule: * * * * * (runs every minute, checks for due campaigns)
 */
class RunCampaignsCommand extends Command
{
    protected $signature = 'publish:run-campaigns';
    protected $description = 'Process all due campaigns — find sources, spin, publish.';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int
    {
        $dueCampaigns = PublishCampaign::with(['site', 'template', 'campaignPreset'])
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

        $runService = app(CampaignExecutionService::class);
        $modeResolver = app(CampaignModeResolver::class);
        $scheduleService = app(CampaignScheduleService::class);

        foreach ($dueCampaigns as $campaign) {
            $lock = Cache::lock('publish:campaign-run:' . $campaign->id, 900);
            if (!$lock->get()) {
                $this->line("  Campaign: {$campaign->name} ({$campaign->campaign_id}) — already claimed, skipping.");
                continue;
            }

            try {
                $campaign = PublishCampaign::with(['site', 'template', 'campaignPreset'])
                    ->find($campaign->id);

                if (!$campaign) {
                    $this->line('  Campaign no longer exists, skipping.');
                    continue;
                }

                if ($campaign->status !== 'active') {
                    $this->line("  Campaign: {$campaign->name} ({$campaign->campaign_id}) — paused before execution, skipping.");
                    continue;
                }

                if ($campaign->next_run_at && $campaign->next_run_at->gt(now())) {
                    $this->line("  Campaign: {$campaign->name} ({$campaign->campaign_id}) — next run already reserved for {$campaign->next_run_at->toDateTimeString()}, skipping.");
                    continue;
                }

                $intervalLimit = max(1, (int) $campaign->articles_per_interval);
                [$windowStartUtc, $windowEndUtc] = $scheduleService->currentWindowUtc($campaign);
                $existingThisWindow = PublishArticle::query()
                    ->where('publish_campaign_id', $campaign->id)
                    ->where('status', '!=', 'deleted')
                    ->where('created_at', '>=', $windowStartUtc)
                    ->where('created_at', '<', $windowEndUtc)
                    ->count();

                if ($existingThisWindow >= $intervalLimit) {
                    $this->line("  Campaign: {$campaign->name} ({$campaign->campaign_id}) — interval cap reached ({$existingThisWindow}/{$intervalLimit}) for current {$campaign->interval_unit} window, skipping.");
                    continue;
                }

                $reservedAt = now();
                $campaign->forceFill([
                    'last_run_at' => $reservedAt,
                    'next_run_at' => $scheduleService->nextRunAt($campaign),
                ])->save();

                $this->line("  Campaign: {$campaign->name} ({$campaign->campaign_id})");

                $count = max(0, $intervalLimit - $existingThisWindow);
                $dripMinutes = $campaign->drip_interval_minutes ?? 60;
                $deliveryMode = $modeResolver->normalizeDeliveryMode($campaign->delivery_mode);

                for ($i = 0; $i < $count; $i++) {
                    $campaign->refresh();
                    if ($campaign->status !== 'active') {
                        $this->line("    Campaign paused mid-run. Stopping after {$i} article(s).");
                        break;
                    }

                    $scheduledFor = null;
                    if ($i > 0 && $dripMinutes > 0) {
                        $scheduledFor = now()->addMinutes($i * $dripMinutes);
                        $this->line("    Article {$i} scheduled for: {$scheduledFor->format('H:i')}");
                    }

                    $result = $runService->run($campaign, $deliveryMode, $scheduledFor);

                    foreach ($result['log'] as $entry) {
                        $prefix = match ($entry['type']) {
                            'success' => '<info>',
                            'error' => '<error>',
                            'warning' => '<comment>',
                            default => '',
                        };
                        $suffix = $prefix ? '</' . substr($prefix, 1) : '';
                        $this->line("    {$prefix}{$entry['time']} {$entry['message']}{$suffix}");
                    }

                    if ($result['article']) {
                        $this->line("    Article: {$result['article']->article_id}");
                    }
                }
            } finally {
                optional($lock)->release();
            }
        }

        return 0;
    }
}
