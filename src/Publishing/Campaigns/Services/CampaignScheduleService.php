<?php

namespace hexa_app_publish\Publishing\Campaigns\Services;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use hexa_app_publish\Publishing\Campaigns\Models\PublishCampaign;

class CampaignScheduleService
{
    public function initialRunAt(PublishCampaign $campaign, ?CarbonInterface $from = null): Carbon
    {
        $tz = $campaign->timezone ?: 'America/New_York';
        $reference = ($from ?: now())->copy()->setTimezone($tz);

        if (($campaign->interval_unit ?? 'daily') === 'hourly') {
            return $reference->copy()->addHour()->setSecond(0);
        }

        $time = $campaign->run_at_time ?: '09:00';
        $candidate = $reference->copy()->setTimeFromTimeString($time)->setSecond(0);

        if ($candidate->lessThanOrEqualTo($reference)) {
            return $this->incrementForInterval($candidate, $campaign->interval_unit ?? 'daily');
        }

        return $candidate;
    }

    public function nextRunAt(PublishCampaign $campaign, ?CarbonInterface $from = null): Carbon
    {
        $tz = $campaign->timezone ?: 'America/New_York';
        $reference = ($from ?: now())->copy()->setTimezone($tz)->setSecond(0);

        if (($campaign->interval_unit ?? 'daily') === 'hourly') {
            return $reference->copy()->addHour();
        }

        $time = $campaign->run_at_time ?: '09:00';
        $base = $reference->copy()->setTimeFromTimeString($time);

        return $this->incrementForInterval($base, $campaign->interval_unit ?? 'daily');
    }

    private function incrementForInterval(Carbon $date, string $intervalUnit): Carbon
    {
        return match ($intervalUnit) {
            'hourly' => $date->copy()->addHour(),
            'weekly' => $date->copy()->addWeek(),
            'monthly' => $date->copy()->addMonth(),
            default => $date->copy()->addDay(),
        };
    }
}
