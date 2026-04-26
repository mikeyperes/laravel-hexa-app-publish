<?php

namespace hexa_app_publish\Support;

use hexa_core\AI\Services\AiModelCatalog as CoreAiModelCatalog;

class AiModelCatalog extends CoreAiModelCatalog
{
    private const SEARCH_PRIORITY = [
        'gemini-2.5-flash',
        'claude-haiku-4-5-20251001',
        'gpt-4o-mini',
        'grok-3-mini',
        'gemini-2.5-flash-lite',
        'grok-3',
        'gpt-4o',
        'grok-4-1-fast',
    ];

    private const SPIN_PRIORITY = [
        'grok-3',
        'grok-4.20-reasoning',
        'claude-sonnet-4-6',
        'claude-sonnet-4-20250514',
        'gemini-2.5-pro',
        'gemini-2.5-flash',
        'gpt-4o',
    ];

    private const PHOTO_META_PRIORITY = [
        'gemini-2.5-flash-lite',
        'claude-haiku-4-5-20251001',
        'gpt-4o',
        'grok-3-mini',
    ];

    private const OPTIMIZED_SEARCH_PREFIX = 'optimized:';

    private const OPTIMIZED_SEARCH_CONFIG = [
        'anthropic' => [
            'label' => 'Claude Optimized Search',
            'preferred_model' => 'claude-haiku-4-5-20251001',
        ],
        'openai' => [
            'label' => 'OpenAI Optimized Search',
            'preferred_model' => 'gpt-4o-mini',
        ],
        'grok' => [
            'label' => 'Grok Optimized Search',
            'preferred_model' => 'grok-3-mini',
        ],
        'gemini' => [
            'label' => 'Gemini Optimized Search',
            'preferred_model' => 'gemini-2.5-flash',
        ],
    ];

    public function defaultSearchModel(): ?string
    {
        return $this->preferredModel(self::SEARCH_PRIORITY);
    }

    public function defaultSearchSelection(): ?string
    {
        return $this->defaultSearchModel();
    }

    public function defaultSearchFallbackModel(?string $primary = null): ?string
    {
        return $this->preferredModel(self::SEARCH_PRIORITY, [$primary]);
    }

    public function defaultSpinModel(): ?string
    {
        return $this->preferredModel(self::SPIN_PRIORITY);
    }

    public function defaultSpinFallbackModel(?string $primary = null): ?string
    {
        return $this->preferredModel(self::SPIN_PRIORITY, [$primary]);
    }

    public function defaultPhotoMetaModel(): ?string
    {
        return $this->preferredModel(self::PHOTO_META_PRIORITY);
    }

    /**
     * @return array<string, array<int, array{id: string, label: string}>>
     */
    public function groupedSearchSelectOptions(bool $requireConfigured = true): array
    {
        $groups = $this->siteEnabledGroupedSelectOptions($requireConfigured);

        foreach ($this->optimizedSearchEntries($requireConfigured) as $entry) {
            $groups[$entry['provider_label']][] = [
                'id' => $entry['id'],
                'label' => $entry['label'],
            ];
        }

        return $groups;
    }

    /**
     * @return array<string, string>
     */
    public function searchSelectOptions(bool $requireConfigured = true): array
    {
        $options = $this->siteEnabledSelectOptions($requireConfigured);

        foreach ($this->optimizedSearchEntries($requireConfigured) as $entry) {
            $options[$entry['id']] = $entry['label'];
        }

        return $options;
    }

    /**
     * @return array<string, string>
     */
    public function searchOptionLabels(bool $requireConfigured = true): array
    {
        $labels = [];

        foreach ($this->groupedSearchSelectOptions($requireConfigured) as $entries) {
            foreach ($entries as $entry) {
                $labels[$entry['id']] = $entry['label'];
            }
        }

        return $labels;
    }

    public function isOptimizedSearchSelection(?string $selection): bool
    {
        return is_string($selection) && str_starts_with($selection, self::OPTIMIZED_SEARCH_PREFIX);
    }

    /**
     * @return array{selection:string,mode:string,provider:?string,provider_label:string,model:?string,backend_label:string,label:string}
     */
    public function resolveSearchSelection(?string $selection): array
    {
        $selection = trim((string) ($selection ?? ''));

        if ($this->isOptimizedSearchSelection($selection)) {
            $provider = substr($selection, strlen(self::OPTIMIZED_SEARCH_PREFIX));
            $providerLabel = $this->providerLabels()[$provider] ?? ucfirst($provider);
            $backendLabel = self::OPTIMIZED_SEARCH_CONFIG[$provider]['label'] ?? ($providerLabel . ' Optimized Search');
            $model = $this->preferredOptimizedSearchModel($provider);

            return [
                'selection' => $selection,
                'mode' => 'optimized',
                'provider' => $provider,
                'provider_label' => $providerLabel,
                'model' => $model,
                'backend_label' => $backendLabel,
                'label' => $this->searchOptionLabels()[$selection] ?? $backendLabel,
            ];
        }

        $entry = $this->find($selection);
        $provider = $entry['provider'] ?? $this->providerForModel($selection);
        $providerLabel = $entry['provider_label'] ?? ($this->providerLabels()[$provider] ?? ucfirst((string) $provider));

        return [
            'selection' => $selection,
            'mode' => 'model',
            'provider' => $provider,
            'provider_label' => $providerLabel,
            'model' => $selection,
            'backend_label' => $providerLabel . ' Model Search',
            'label' => $entry['label'] ?? $selection,
        ];
    }

    /**
     * @return array<int, array{id: string, provider: string, provider_label: string, model: string, label: string, backend_label: string}>
     */
    public function optimizedSearchEntries(bool $requireConfigured = true): array
    {
        $entries = [];
        $providers = $this->siteEnabledProviders($requireConfigured);

        foreach (self::OPTIMIZED_SEARCH_CONFIG as $provider => $config) {
            if (!isset($providers[$provider])) {
                continue;
            }

            $model = $this->preferredOptimizedSearchModel($provider);
            if (!$model) {
                continue;
            }

            $modelEntry = $this->find($model);
            $providerLabel = $providers[$provider]['label'] ?? ($this->providerLabels()[$provider] ?? ucfirst($provider));
            $backendLabel = $config['label'];
            $label = $providerLabel . ' - ' . $backendLabel;

            if (!empty($modelEntry['price_label'])) {
                $label .= ' - ' . $modelEntry['price_label'];
            }

            $entries[] = [
                'id' => self::OPTIMIZED_SEARCH_PREFIX . $provider,
                'provider' => $provider,
                'provider_label' => $providerLabel,
                'model' => $model,
                'label' => $label,
                'backend_label' => $backendLabel,
            ];
        }

        return $entries;
    }

    protected function preferredOptimizedSearchModel(string $provider): ?string
    {
        $preferred = self::OPTIMIZED_SEARCH_CONFIG[$provider]['preferred_model'] ?? null;
        if ($preferred && $this->find($preferred)) {
            return $preferred;
        }

        return $this->firstModelForProvider($provider);
    }
}
