<?php

namespace hexa_app_publish\Publishing\Campaigns\Services;

use hexa_app_publish\Publishing\Campaigns\Models\CampaignPreset;
use hexa_app_publish\Publishing\Campaigns\Models\PublishCampaign;
use hexa_app_publish\Publishing\Presets\Models\PublishPreset;
use hexa_app_publish\Publishing\Templates\Models\PublishTemplate;

class CampaignSettingsResolver
{
    public function __construct(
        protected CampaignEligibilityService $eligibility,
        protected CampaignModeResolver $modeResolver,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function resolve(PublishCampaign $campaign): array
    {
        $campaign->loadMissing(['campaignPreset', 'template', 'wpPreset', 'site']);

        $campaignPreset = $campaign->campaignPreset ?: $this->defaultCampaignPreset($campaign);
        $template = $campaign->template ?: $this->defaultTemplate($campaign);
        $wpPreset = $campaign->wpPreset ?: $this->defaultWpPreset($campaign);

        $articleType = $campaign->article_type
            ?: ($template?->article_type ?: config('hws-publish.campaign_supported_article_types.0'));
        $deliveryMode = $campaign->delivery_mode
            ?: ($wpPreset?->default_publish_action ? $this->mapWpPresetAction($wpPreset->default_publish_action) : 'draft-local');
        $normalizedDeliveryMode = $this->modeResolver->normalizeDeliveryMode($deliveryMode);

        $resolved = [
            'article_type' => $articleType,
            'delivery_mode' => $normalizedDeliveryMode,
            'execution_mode' => $this->modeResolver->toExecutionMode($deliveryMode),
            'final_article_method' => $campaignPreset?->final_article_method ?: 'news-search',
            'source_method' => $campaignPreset?->source_method ?: 'keyword',
            'search_terms' => $this->resolveSearchTerms($campaign, $campaignPreset),
            'topic' => trim((string) ($campaign->topic ?: '')),
            'genre' => $campaignPreset?->genre,
            'trending_categories' => array_values((array) ($campaignPreset?->trending_categories ?: [])),
            'local_preference' => trim((string) ($campaignPreset?->local_preference ?: '')),
            'article_sources' => $this->normalizeArticleSources($campaign->article_sources),
            'photo_sources' => $this->resolvePhotoSources($campaign, $template),
            'max_links_per_article' => $campaign->max_links_per_article ?: ($template?->max_links ?: config('hws-publish.defaults.max_links_per_article', 5)),
            'ai_engine' => $campaign->ai_engine ?: ($template?->ai_engine ?: 'claude-sonnet-4-6'),
            'publish_template_id' => $campaign->publish_template_id ?: $template?->id,
            'preset_id' => $campaign->preset_id ?: $wpPreset?->id,
            'post_status' => $this->resolvePostStatus($normalizedDeliveryMode, $campaign->post_status),
            'author' => $campaign->author,
            'ai_instructions' => $this->combineInstructions(
                $campaignPreset?->ai_instructions,
                $campaign->ai_instructions ?: $campaign->notes
            ),
            'campaign_preset' => $campaignPreset,
            'template' => $template,
            'wp_preset' => $wpPreset,
        ];

        $this->eligibility->assertArticleTypeAllowed($resolved['article_type']);
        $this->eligibility->assertDeliveryModeAllowed($resolved['delivery_mode']);

        return $resolved;
    }

    /**
     * @return array<int, string>
     */
    private function resolveSearchTerms(PublishCampaign $campaign, ?CampaignPreset $campaignPreset): array
    {
        $terms = $campaign->keywords;
        if (empty($terms) && $campaignPreset) {
            $terms = $campaignPreset->keywords;
        }

        return collect((array) $terms)
            ->map(fn ($term) => trim((string) $term))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function normalizeArticleSources($sources): array
    {
        $resolved = collect((array) $sources)
            ->map(fn ($source) => trim((string) $source))
            ->filter()
            ->map(function (string $source) {
                return match ($source) {
                    'currents' => 'currents_news',
                    default => $source,
                };
            })
            ->unique()
            ->values()
            ->all();

        if (!empty($resolved)) {
            return $resolved;
        }

        return collect((array) config('hws-publish.article_sources', []))
            ->map(fn ($source) => $source === 'currents' ? 'currents_news' : $source)
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function resolvePhotoSources(PublishCampaign $campaign, ?PublishTemplate $template): array
    {
        $sources = $campaign->photo_sources;
        if (empty($sources) && $template) {
            $sources = $template->photo_sources;
        }

        $resolved = collect((array) $sources)
            ->map(fn ($source) => trim((string) $source))
            ->filter()
            ->unique()
            ->values()
            ->all();

        return !empty($resolved)
            ? $resolved
            : array_values((array) config('hws-publish.photo_sources', []));
    }

    private function combineInstructions(?string ...$parts): ?string
    {
        $combined = collect($parts)
            ->map(fn ($part) => trim((string) $part))
            ->filter()
            ->implode("\n\n");

        return $combined !== '' ? $combined : null;
    }

    private function defaultCampaignPreset(PublishCampaign $campaign): ?CampaignPreset
    {
        if ($campaign->user_id) {
            $preset = CampaignPreset::where('user_id', $campaign->user_id)
                ->where('is_default', true)
                ->first();
            if ($preset) {
                return $preset;
            }
        }

        return CampaignPreset::where('is_default', true)->first();
    }

    private function defaultTemplate(PublishCampaign $campaign): ?PublishTemplate
    {
        if ($campaign->publish_account_id) {
            $template = PublishTemplate::where('publish_account_id', $campaign->publish_account_id)
                ->where('is_default', true)
                ->first();
            if ($template) {
                return $template;
            }
        }

        return PublishTemplate::where('is_default', true)->first();
    }

    private function defaultWpPreset(PublishCampaign $campaign): ?PublishPreset
    {
        if ($campaign->user_id) {
            $preset = PublishPreset::where('user_id', $campaign->user_id)
                ->where('is_default', true)
                ->first();
            if ($preset) {
                return $preset;
            }
        }

        return PublishPreset::where('is_default', true)->first();
    }

    private function mapWpPresetAction(string $action): string
    {
        return match ($action) {
            'publish_immediate' => 'auto-publish',
            'draft_wordpress' => 'draft-wordpress',
            default => 'draft-local',
        };
    }

    private function resolvePostStatus(string $deliveryMode, ?string $postStatus): string
    {
        return match ($deliveryMode) {
            'auto-publish' => 'publish',
            'draft-wordpress' => 'draft',
            default => $postStatus ?: 'draft',
        };
    }
}
