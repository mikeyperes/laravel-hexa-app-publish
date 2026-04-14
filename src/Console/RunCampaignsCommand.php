<?php

namespace hexa_app_publish\Console;

use Illuminate\Console\Command;
use hexa_app_publish\Publishing\Campaigns\Models\PublishCampaign;
use hexa_app_publish\Publishing\Campaigns\Services\CampaignExecutionService;
use hexa_app_publish\Publishing\Campaigns\Services\CampaignModeResolver;

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

        foreach ($dueCampaigns as $campaign) {
            $this->line("  Campaign: {$campaign->name} ({$campaign->campaign_id})");

            $count = $campaign->articles_per_interval;
            $dripMinutes = $campaign->drip_interval_minutes ?? 60;
            $deliveryMode = $modeResolver->normalizeDeliveryMode($campaign->delivery_mode);

            for ($i = 0; $i < $count; $i++) {
                // Drip: set scheduled_for on articles after the first
                // No sleep() — articles are created with staggered timestamps
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
        }

        return 0;
    }
}
