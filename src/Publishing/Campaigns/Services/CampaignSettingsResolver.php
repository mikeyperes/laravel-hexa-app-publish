<?php

namespace hexa_app_publish\Publishing\Campaigns\Services;

use hexa_app_publish\Publishing\Campaigns\Models\CampaignPreset;
use hexa_app_publish\Publishing\Campaigns\Models\PublishCampaign;
use hexa_app_publish\Publishing\Campaigns\Forms\CampaignPresetForm;
use hexa_app_publish\Publishing\Templates\Forms\ArticlePresetForm;
use hexa_app_publish\Publishing\Templates\Models\PublishTemplate;
use hexa_core\Forms\Runtime\FormRuntimeService;
use hexa_app_publish\Support\AiModelCatalog;

class CampaignSettingsResolver
{
    public function __construct(
        protected CampaignEligibilityService $eligibility,
        protected CampaignModeResolver $modeResolver,
        protected AiModelCatalog $catalog,
        protected FormRuntimeService $formRuntime,
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
        $campaignPresetValues = $this->resolveCampaignPresetValues($campaignPreset, (array) ($campaign->campaign_preset_overrides ?? []));
        $templateValues = $this->resolveTemplateValues($template, (array) ($campaign->article_preset_overrides ?? []));

        $articleType = $campaign->article_type
            ?: ($templateValues['article_type'] ?? $template?->article_type ?: config('hws-publish.campaign_supported_article_types.0'));
        $deliveryMode = $campaign->delivery_mode ?: 'draft-local';
        $normalizedDeliveryMode = $this->modeResolver->normalizeDeliveryMode($deliveryMode);
        $onlineSearchPrimary = $templateValues['searching_agent'] ?? $template?->searching_agent ?: $this->catalog->defaultSearchModel();
        $onlineSearchFallback = $templateValues['online_search_model_fallback'] ?? $template?->online_search_model_fallback ?: ($this->catalog->defaultSearchFallbackModel($onlineSearchPrimary) ?: $onlineSearchPrimary);
        $scrapeAiPrimary = $templateValues['scraping_agent'] ?? $template?->scraping_agent ?: $this->catalog->defaultSearchModel();
        $scrapeAiFallback = $templateValues['scrape_ai_model_fallback'] ?? $template?->scrape_ai_model_fallback ?: ($this->catalog->defaultSearchFallbackModel($scrapeAiPrimary) ?: $scrapeAiPrimary);
        $spinPrimary = $templateValues['spinning_agent'] ?? $template?->spinning_agent ?: ($templateValues['ai_engine'] ?? $template?->ai_engine ?: $this->catalog->defaultSpinModel());
        $spinFallback = $templateValues['spin_model_fallback'] ?? $template?->spin_model_fallback ?: ($this->catalog->defaultSpinFallbackModel($spinPrimary) ?: $spinPrimary);
        $inlinePhotoMin = max(1, (int) ($templateValues['inline_photo_min'] ?? $template?->inline_photo_min ?: 2));
        $inlinePhotoMax = max($inlinePhotoMin, (int) ($templateValues['inline_photo_max'] ?? $template?->inline_photo_max ?: max(3, $inlinePhotoMin)));

        $resolved = [
            'article_type' => $articleType,
            'delivery_mode' => $normalizedDeliveryMode,
            'execution_mode' => $this->modeResolver->toExecutionMode($deliveryMode),
            'search_terms' => $this->resolveSearchTerms($campaign, $campaignPresetValues),
            'topic' => trim((string) ($campaign->topic ?: '')),
            'article_sources' => $this->normalizeArticleSources($campaign->article_sources),
            'photo_sources' => $this->resolvePhotoSources($campaign, $templateValues, $template),
            'max_links_per_article' => $campaign->max_links_per_article ?: ($templateValues['max_links'] ?? $template?->max_links ?: config('hws-publish.defaults.max_links_per_article', 5)),
            'ai_engine' => $campaign->ai_engine ?: $spinPrimary,
            'spin_model_primary' => $spinPrimary,
            'spin_model_fallback' => $spinFallback,
            'search_online_for_additional_context' => $templateValues['search_online_for_additional_context'] ?? $template?->search_online_for_additional_context ?? true,
            'online_search_model_primary' => $onlineSearchPrimary,
            'online_search_model_fallback' => $onlineSearchFallback,
            'scrape_ai_model_primary' => $scrapeAiPrimary,
            'scrape_ai_model_fallback' => $scrapeAiFallback,
            'headline_rules' => trim((string) ($templateValues['headline_rules'] ?? $template?->headline_rules ?: '')),
            'h2_notation' => trim((string) ($templateValues['h2_notation'] ?? $template?->h2_notation ?: 'capital_case')),
            'inline_photo_min' => $inlinePhotoMin,
            'inline_photo_max' => $inlinePhotoMax,
            'featured_image_required' => $templateValues['featured_image_required'] ?? $template?->featured_image_required ?? true,
            'featured_image_must_be_landscape' => $templateValues['featured_image_must_be_landscape'] ?? $template?->featured_image_must_be_landscape ?? true,
            'publish_template_id' => $campaign->publish_template_id ?: $template?->id,
            'post_status' => $this->resolvePostStatus($normalizedDeliveryMode, $campaign->post_status),
            'author' => $campaign->author ?: $campaign->site?->default_author,
            'ai_instructions' => $this->combineInstructions(
                $campaignPresetValues['campaign_instructions'] ?? null,
                $campaignPresetValues['ai_instructions'] ?? null,
                $campaign->ai_instructions ?: $campaign->notes
            ),
            'campaign_source_method' => $campaignPresetValues['source_method'] ?? 'keyword',
            'campaign_final_article_method' => $campaignPresetValues['final_article_method'] ?? 'news-search',
            'campaign_local_preference' => $campaignPresetValues['local_preference'] ?? null,
            'campaign_genre' => $campaignPresetValues['genre'] ?? null,
            'campaign_trending_categories' => array_values((array) ($campaignPresetValues['trending_categories'] ?? [])),
            'campaign_auto_select_sources' => (bool) ($campaignPresetValues['auto_select_sources'] ?? false),
            'campaign_preset' => $campaignPreset,
            'campaign_preset_values' => $campaignPresetValues,
            'article_preset' => $template,
            'article_preset_values' => $templateValues,
            'template' => $template,
        ];

        $this->eligibility->assertArticleTypeAllowed($resolved['article_type']);
        $this->eligibility->assertDeliveryModeAllowed($resolved['delivery_mode']);

        return $resolved;
    }

    /**
     * @return array<int, string>
     */
    private function resolveSearchTerms(PublishCampaign $campaign, array $campaignPresetValues = []): array
    {
        $terms = $campaign->keywords;
        if (empty($terms)) {
            $terms = $campaignPresetValues['search_queries'] ?? $campaignPresetValues['keywords'] ?? [];
        }

        return collect((array) $terms)
            ->flatMap(function ($term) {
                return preg_split("/[\r\n,]+/", (string) $term) ?: [];
            })
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
    private function resolvePhotoSources(PublishCampaign $campaign, array $templateValues = [], ?PublishTemplate $template = null): array
    {
        $sources = $campaign->photo_sources;
        if (empty($sources)) {
            $sources = $templateValues['photo_sources'] ?? ($template?->photo_sources);
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

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function resolveCampaignPresetValues(?CampaignPreset $campaignPreset, array $overrides = []): array
    {
        return $this->formRuntime->hydrate(
            CampaignPresetForm::FORM_KEY,
            $campaignPreset,
            $overrides,
            [
                'context' => 'pipeline',
                'mode' => 'pipeline',
                'record' => $campaignPreset,
            ]
        );
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function resolveTemplateValues(?PublishTemplate $template, array $overrides = []): array
    {
        return $this->formRuntime->hydrate(
            ArticlePresetForm::FORM_KEY,
            $template,
            $overrides,
            [
                'context' => 'pipeline',
                'mode' => 'pipeline',
                'record' => $template,
            ]
        );
    }
}
