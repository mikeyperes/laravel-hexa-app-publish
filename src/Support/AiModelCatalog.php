<?php

namespace hexa_app_publish\Support;

class AiModelCatalog
{
    private const PROVIDERS = [
        'anthropic' => 'Anthropic',
        'openai' => 'OpenAI',
        'grok' => 'xAI',
        'gemini' => 'Google',
    ];

    private const PRICING = [
        'claude-opus-4-6' => ['input' => 15.0, 'output' => 75.0],
        'claude-opus-4-20250514' => ['input' => 15.0, 'output' => 75.0],
        'claude-sonnet-4-6' => ['input' => 3.0, 'output' => 15.0],
        'claude-sonnet-4-20250514' => ['input' => 3.0, 'output' => 15.0],
        'claude-haiku-4-5-20251001' => ['input' => 0.80, 'output' => 4.0],
        'gpt-4o' => ['input' => 2.50, 'output' => 10.0],
        'gpt-4-turbo' => ['input' => 10.0, 'output' => 30.0],
        'gpt-4' => ['input' => 30.0, 'output' => 60.0],
        'gpt-3.5-turbo' => ['input' => 0.50, 'output' => 1.50],
        'grok-3' => ['input' => 3.0, 'output' => 15.0],
        'grok-3-mini' => ['input' => 0.30, 'output' => 0.50],
        'grok-2' => ['input' => 2.0, 'output' => 10.0],
        'gemini-2.5-flash-lite' => ['input' => 0.10, 'output' => 0.40],
        'gemini-2.5-flash' => ['input' => 0.30, 'output' => 2.50],
        'gemini-2.5-pro' => ['input' => 1.25, 'output' => 10.0],
    ];

    private const SEARCH_PRIORITY = [
        'gemini-2.5-flash-lite',
        'claude-haiku-4-5-20251001',
        'grok-3-mini',
        'gpt-4o',
    ];

    private const SPIN_PRIORITY = [
        'grok-3',
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

    /**
     * @return array<int, array{id: string, provider: string, provider_label: string, name: string, label: string, price_label: string, pricing: array{input: float, output: float}}>
     */
    public function entries(): array
    {
        $entries = [];

        foreach ($this->anthropicModels() as $model) {
            $entries[] = $this->makeEntry('anthropic', $model);
        }

        foreach ($this->openAiModels() as $model) {
            $entries[] = $this->makeEntry('openai', $model);
        }

        foreach ($this->grokModels() as $model) {
            $entries[] = $this->makeEntry('grok', $model);
        }

        foreach ($this->geminiModels() as $model) {
            $entries[] = $this->makeEntry('gemini', $model);
        }

        return $entries;
    }

    /**
     * @return array<string, array<int, array{id: string, label: string}>>
     */
    public function groupedSelectOptions(): array
    {
        $groups = [];

        foreach ($this->entries() as $entry) {
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
    public function selectOptions(): array
    {
        $options = [];

        foreach ($this->entries() as $entry) {
            $options[$entry['id']] = $entry['label'];
        }

        return $options;
    }

    /**
     * @return array<string, string>
     */
    public function companyOptions(): array
    {
        $options = [];

        foreach ($this->companyModels() as $company => $models) {
            if (!empty($models)) {
                $options[$company] = $company;
            }
        }

        return $options;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function companyModels(): array
    {
        $companies = [];

        foreach ($this->entries() as $entry) {
            $companies[$entry['provider_label']][] = $entry['id'];
        }

        return $companies;
    }

    public function defaultSearchModel(): ?string
    {
        return $this->preferredModel(self::SEARCH_PRIORITY);
    }

    public function defaultSpinModel(): ?string
    {
        return $this->preferredModel(self::SPIN_PRIORITY);
    }

    public function defaultPhotoMetaModel(): ?string
    {
        return $this->preferredModel(self::PHOTO_META_PRIORITY);
    }

    public function detectCompany(?string $model): string
    {
        $entry = $this->find($model);

        return $entry['provider_label'] ?? ($this->companyOptions()[array_key_first($this->companyOptions())] ?? 'Anthropic');
    }

    public function providerForModel(?string $model): string
    {
        $entry = $this->find($model);
        if ($entry) {
            return $entry['provider'];
        }

        $model = (string) $model;

        return match (true) {
            str_starts_with($model, 'claude-') => 'anthropic',
            str_starts_with($model, 'gpt-') => 'openai',
            str_starts_with($model, 'grok-') => 'grok',
            str_starts_with($model, 'gemini-') => 'gemini',
            default => 'anthropic',
        };
    }

    /**
     * @return array{input: float, output: float}
     */
    public function pricing(string $model): array
    {
        return self::PRICING[$model] ?? ['input' => 0.0, 'output' => 0.0];
    }

    public function priceLabel(string $model): string
    {
        $pricing = $this->pricing($model);

        if ($pricing['input'] <= 0 && $pricing['output'] <= 0) {
            return 'price unavailable';
        }

        return '$' . $this->formatMoney($pricing['input']) . ' in / $' . $this->formatMoney($pricing['output']) . ' out per 1M';
    }

    public function calculateCost(string $model, array $usage): float
    {
        $pricing = $this->pricing($model);
        $inputTokens = (int) ($usage['input_tokens'] ?? 0);
        $outputTokens = (int) ($usage['output_tokens'] ?? 0);

        return ($inputTokens * $pricing['input'] / 1_000_000) + ($outputTokens * $pricing['output'] / 1_000_000);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(?string $model): ?array
    {
        if (!$model) {
            return null;
        }

        foreach ($this->entries() as $entry) {
            if ($entry['id'] === $model) {
                return $entry;
            }
        }

        return null;
    }

    /**
     * @param array{id: string, name: string} $model
     * @return array{id: string, provider: string, provider_label: string, name: string, label: string, price_label: string, pricing: array{input: float, output: float}}
     */
    private function makeEntry(string $provider, array $model): array
    {
        $modelId = (string) $model['id'];
        $providerLabel = self::PROVIDERS[$provider] ?? ucfirst($provider);

        return [
            'id' => $modelId,
            'provider' => $provider,
            'provider_label' => $providerLabel,
            'name' => (string) $model['name'],
            'label' => $providerLabel . ' - ' . (string) $model['name'] . ' - ' . $this->priceLabel($modelId),
            'price_label' => $this->priceLabel($modelId),
            'pricing' => $this->pricing($modelId),
        ];
    }

    /**
     * @return array<int, array{id: string, name: string}>
     */
    private function anthropicModels(): array
    {
        return array_values(array_map(
            static fn (array $model): array => ['id' => (string) $model['id'], 'name' => (string) $model['name']],
            array_filter((array) config('anthropic.models', []), static function (array $model): bool {
                $type = (string) ($model['type'] ?? '');

                return $type === 'api' || $type === 'both';
            })
        ));
    }

    /**
     * @return array<int, array{id: string, name: string}>
     */
    private function openAiModels(): array
    {
        return array_values(array_map(
            static fn (array $model): array => ['id' => (string) $model['id'], 'name' => (string) $model['name']],
            (array) config('chatgpt.models', [])
        ));
    }

    /**
     * @return array<int, array{id: string, name: string}>
     */
    private function grokModels(): array
    {
        if (class_exists(\hexa_package_grok\Services\GrokService::class)) {
            try {
                return array_values(array_map(
                    static fn (array $model): array => ['id' => (string) $model['id'], 'name' => (string) $model['name']],
                    app(\hexa_package_grok\Services\GrokService::class)->listModels()
                ));
            } catch (\Throwable) {
            }
        }

        return array_values(array_map(
            static fn (array $model): array => ['id' => (string) $model['id'], 'name' => (string) $model['name']],
            (array) config('grok.models', [])
        ));
    }

    /**
     * @return array<int, array{id: string, name: string}>
     */
    private function geminiModels(): array
    {
        return array_values(array_map(
            static fn (array $model): array => ['id' => (string) $model['id'], 'name' => (string) $model['name']],
            (array) config('gemini.models', [])
        ));
    }

    private function preferredModel(array $priority): ?string
    {
        $available = array_keys($this->selectOptions());

        foreach ($priority as $model) {
            if (in_array($model, $available, true)) {
                return $model;
            }
        }

        return $available[0] ?? null;
    }

    private function formatMoney(float $amount): string
    {
        $formatted = number_format($amount, $amount < 1 ? 2 : 2, '.', '');

        return str_contains($formatted, '.')
            ? rtrim(rtrim($formatted, '0'), '.')
            : $formatted;
    }
}
