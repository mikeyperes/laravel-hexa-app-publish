<?php

namespace hexa_app_publish\Publishing\Campaigns\Services;

class CampaignModeResolver
{
    /**
     * @param string|null $mode
     * @return string draft|wp-draft|publish
     */
    public function toExecutionMode(?string $mode): string
    {
        return match ($mode) {
            'auto-publish', 'publish' => 'publish',
            'draft-wordpress', 'wp-draft' => 'wp-draft',
            'draft-local', 'draft', 'review', 'notify', null, '' => 'draft',
            default => 'draft',
        };
    }

    /**
     * @param string|null $mode
     * @return string
     */
    public function normalizeDeliveryMode(?string $mode): string
    {
        return match ($mode) {
            'publish', 'auto-publish' => 'auto-publish',
            'wp-draft', 'draft-wordpress' => 'draft-wordpress',
            default => 'draft-local',
        };
    }
}
