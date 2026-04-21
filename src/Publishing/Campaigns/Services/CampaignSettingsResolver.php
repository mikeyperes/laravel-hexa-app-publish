<?php

namespace hexa_app_publish\Publishing\Campaigns\Services;

use hexa_app_publish\Publishing\Campaigns\Models\CampaignPreset;
use hexa_app_publish\Publishing\Campaigns\Models\PublishCampaign;
use hexa_app_publish\Publishing\Templates\Models\PublishTemplate;
use hexa_app_publish\Support\AiModelCatalog;

class CampaignSettingsResolver
{
    public function __construct(
        protected CampaignEligibilityService $eligibility,
        protected CampaignModeResolver $modeResolver,
        protected AiModelCatalog $catalog,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function resolve(PublishCampaign $campaign): array
    {
        $campaign->loadMissing(['campaignPreset', 'template', 'site']);

        $campaignPreset = $campaign->campaignPreset ?: $this->defaultCampaignPreset($campaign);
        $template = $campaign->template ?: $this->defaultTemplate($campaign);

        $articleType = $campaign->article_type
            ?: ($template?->article_type ?: config('hws-publish.campaign_supported_article_types.0'));
        $deliveryMode = $campaign->delivery_mode ?: 'draft-local';
        $normalizedDeliveryMode = $this->modeResolver->normalizeDeliveryMode($deliveryMode);
        $onlineSearchPrimary = $template?->searching_agent ?: $this->catalog->defaultSearchModel();
        $onlineSearchFallback = $template?->online_search_model_fallback ?: ($this->catalog->defaultSearchFallbackModel($onlineSearchPrimary) ?: $onlineSearchPrimary);
        $scrapeAiPrimary = $template?->scraping_agent ?: $this->catalog->defaultSearchModel();
        $scrapeAiFallback = $template?->scrape_ai_model_fallback ?: ($this->catalog->defaultSearchFallbackModel($scrapeAiPrimary) ?: $scrapeAiPrimary);
        $spinPrimary = $template?->spinning_agent ?: ($template?->ai_engine ?: $this->catalog->defaultSpinModel());
        $spinFallback = $template?->spin_model_fallback ?: ($this->catalog->defaultSpinFallbackModel($spinPrimary) ?: $spinPrimary);
        $inlinePhotoMin = max(1, (int) ($template?->inline_photo_min ?: 2));
        $inlinePhotoMax = max($inlinePhotoMin, (int) ($template?->inline_photo_max ?: max(3, $inlinePhotoMin)));

        $resolved = [
            'article_type' => $articleType,
            'delivery_mode' => $normalizedDeliveryMode,
            'execution_mode' => $this->modeResolver->toExecutionMode($deliveryMode),
            'search_terms' => $this->resolveSearchTerms($campaign, $campaignPreset),
            'topic' => trim((string) ($campaign->topic ?: '')),
            'article_sources' => $this->normalizeArticleSources($campaign->article_sources),
            'photo_sources' => $this->resolvePhotoSources($campaign, $template),
            'max_links_per_article' => $campaign->max_links_per_article ?: ($template?->max_links ?: config('hws-publish.defaults.max_links_per_article', 5)),
            'ai_engine' => $campaign->ai_engine ?: $spinPrimary,
            'spin_model_primary' => $spinPrimary,
            'spin_model_fallback' => $spinFallback,
            'search_online_for_additional_context' => $template?->search_online_for_additional_context ?? true,
            'online_search_model_primary' => $onlineSearchPrimary,
            'online_search_model_fallback' => $onlineSearchFallback,
            'scrape_ai_model_primary' => $scrapeAiPrimary,
            'scrape_ai_model_fallback' => $scrapeAiFallback,
            'headline_rules' => trim((string) ($template?->headline_rules ?: '')),
            'h2_notation' => trim((string) ($template?->h2_notation ?: 'capital_case')),
            'inline_photo_min' => $inlinePhotoMin,
            'inline_photo_max' => $inlinePhotoMax,
            'featured_image_required' => $template?->featured_image_required ?? true,
            'featured_image_must_be_landscape' => $template?->featured_image_must_be_landscape ?? true,
            'publish_template_id' => $campaign->publish_template_id ?: $template?->id,
            'post_status' => $this->resolvePostStatus($normalizedDeliveryMode, $campaign->post_status),
            'author' => $campaign->author ?: $campaign->site?->default_author,
            'ai_instructions' => $this->combineInstructions(
                $campaignPreset?->campaign_instructions,
                $campaignPreset?->ai_instructions,
                $campaign->ai_instructions ?: $campaign->notes
            ),
            'campaign_preset' => $campaignPreset,
            'article_preset' => $template,
            'template' => $template,
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
            $terms = $campaignPreset->search_queries ?: $campaignPreset->keywords;
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

    private function resolvePostStatus(string $deliveryMode, ?string $postStatus): string
    {
        return match ($deliveryMode) {
            'auto-publish' => 'publish',
            'draft-wordpress' => 'draft',
            default => $postStatus ?: 'draft',
        };
    }
}
