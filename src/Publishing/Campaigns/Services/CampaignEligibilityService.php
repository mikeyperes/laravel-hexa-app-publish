<?php

namespace hexa_app_publish\Publishing\Campaigns\Services;

use Illuminate\Validation\ValidationException;

class CampaignEligibilityService
{
    /**
     * @return array<int, string>
     */
    public function supportedArticleTypes(): array
    {
        return array_values((array) config('hws-publish.campaign_supported_article_types', [
            'news-report',
            'local-news',
            'editorial',
        ]));
    }

    /**
     * @return array<int, string>
     */
    public function supportedDeliveryModes(): array
    {
        return array_values((array) config('hws-publish.campaign_supported_modes', [
            'draft-local',
            'draft-wordpress',
            'auto-publish',
        ]));
    }

    public function assertArticleTypeAllowed(?string $articleType): void
    {
        if (!$articleType) {
            return;
        }

        if (!in_array($articleType, $this->supportedArticleTypes(), true)) {
            throw ValidationException::withMessages([
                'article_type' => "Campaigns do not support the article type '{$articleType}'.",
            ]);
        }
    }

    public function assertDeliveryModeAllowed(?string $deliveryMode): void
    {
        if (!$deliveryMode) {
            return;
        }

        if (!in_array($deliveryMode, $this->supportedDeliveryModes(), true)) {
            throw ValidationException::withMessages([
                'delivery_mode' => "Campaigns do not support the delivery mode '{$deliveryMode}'.",
            ]);
        }
    }
}
