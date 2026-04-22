<?php

namespace hexa_app_publish\Publishing\Campaigns\Forms;

use hexa_app_publish\Publishing\Campaigns\Models\CampaignPreset;
use hexa_app_publish\Publishing\Campaigns\Services\NewsDiscoveryOptionsService;
use hexa_core\Forms\Definitions\FieldDefinition;
use hexa_core\Forms\Definitions\FormDefinition;
use hexa_core\Forms\Runtime\FormRuntimeService;
use hexa_core\Models\User;

class CampaignPresetForm
{
    public const FORM_KEY = 'app-publish.campaign-preset';

    public static function make(array $context = []): FormDefinition
    {
        $mode = (string) ($context['mode'] ?? $context['context'] ?? 'create');
        /** @var CampaignPreset|null $record */
        $record = $context['record'] ?? null;

        return FormDefinition::make(self::FORM_KEY)
            ->title($mode === 'edit' ? 'Edit Campaign Preset' : 'Create Campaign Preset')
            ->model(CampaignPreset::class)
            ->action(self::actionUrl($mode, $record))
            ->method($mode === 'edit' ? 'PUT' : 'POST')
            ->meta([
                'grid_classes' => 'grid-cols-1 md:grid-cols-2',
            ])
            ->fields([
                FieldDefinition::make('user_id', 'select', 'User')
                    ->rules(['nullable', 'integer', 'exists:users,id'])
                    ->options(fn () => self::userOptions())
                    ->meta([
                        'empty_label' => 'Select user...',
                        'section' => 'account',
                    ])
                    ->contexts(['create', 'edit']),

                FieldDefinition::make('name', 'text', 'Preset Name')
                    ->required()
                    ->rules(['required', 'string', 'max:255'])
                    ->placeholder('e.g. Her Forward Editorial')
                    ->columns('md:col-span-2')
                    ->meta(['section' => 'basic'])
                    ->contexts(['create', 'edit']),

                FieldDefinition::make('source_method', 'select', 'Source Method')
                    ->default('keyword')
                    ->rules(['nullable', 'string', 'in:' . implode(',', self::discoveryModes())])
                    ->options(self::optionMap(self::discoveryModes()))
                    ->help('How the campaign discovers candidate articles before extraction.')
                    ->meta([
                        'empty_label' => 'Select method...',
                        'section' => 'discovery',
                    ])
                    ->contexts(['create', 'edit', 'pipeline']),

                FieldDefinition::make('final_article_method', 'select', 'Final Article Method')
                    ->default('news-search')
                    ->rules(['nullable', 'string', 'in:' . implode(',', self::finalArticleMethods())])
                    ->options(self::optionMap(self::finalArticleMethods()))
                    ->meta([
                        'empty_label' => 'Select method...',
                        'section' => 'discovery',
                    ])
                    ->contexts(['create', 'edit', 'pipeline']),

                FieldDefinition::make('search_queries', 'textarea', 'Search Queries')
                    ->rules(['nullable', 'string', 'max:5000'])
                    ->placeholder("female entrepreneur\nwomen-led business\nstartup funding")
                    ->help('One query per line. These are normalized to an array when the preset is saved.')
                    ->columns('md:col-span-2')
                    ->hydrateUsing(function ($source, $missing) {
                        $queries = data_get($source, 'search_queries', $missing);
                        if ($queries === $missing) {
                            $queries = data_get($source, 'keywords', $missing);
                        }

                        if ($queries === $missing) {
                            return $missing;
                        }

                        return self::stringifyQueries($queries);
                    })
                    ->dehydrateUsing(function ($value) {
                        $queries = self::parseQueries($value);

                        return [
                            'search_queries' => $queries,
                            'keywords' => $queries,
                        ];
                    })
                    ->meta([
                        'rows' => 5,
                        'section' => 'discovery',
                    ])
                    ->contexts(['create', 'edit', 'pipeline']),

                FieldDefinition::make('campaign_instructions', 'textarea', 'Campaign Instructions')
                    ->rules(['nullable', 'string', 'max:5000'])
                    ->placeholder('Prioritize real reporting, avoid advertorials, and favor substantive business coverage.')
                    ->columns('md:col-span-2')
                    ->hydrateUsing(function ($source, $missing) {
                        $instructions = data_get($source, 'campaign_instructions', $missing);
                        if ($instructions === $missing) {
                            $instructions = data_get($source, 'ai_instructions', $missing);
                        }

                        return $instructions;
                    })
                    ->dehydrateUsing(fn ($value) => [
                        'campaign_instructions' => filled($value) ? $value : null,
                        'ai_instructions' => filled($value) ? $value : null,
                    ])
                    ->meta([
                        'rows' => 4,
                        'section' => 'discovery',
                    ])
                    ->contexts(['create', 'edit', 'pipeline']),

                FieldDefinition::make('local_preference', 'text', 'Local Preference')
                    ->rules(['nullable', 'string', 'max:255'])
                    ->placeholder('e.g. United States or New York')
                    ->meta(['section' => 'discovery'])
                    ->contexts(['create', 'edit', 'pipeline']),

                FieldDefinition::make('genre', 'select', 'Genre')
                    ->rules(['nullable', 'string', 'max:100'])
                    ->options(fn () => self::newsCategoryOptions())
                    ->meta([
                        'empty_label' => 'Select genre...',
                        'section' => 'discovery',
                    ])
                    ->contexts(['create', 'edit', 'pipeline']),

                FieldDefinition::make('trending_categories', 'checkbox_group', 'Trending Categories')
                    ->multiple()
                    ->default([])
                    ->rules(['nullable', 'array'])
                    ->options(fn () => self::newsCategoryOptions())
                    ->columns('md:col-span-2')
                    ->meta(['section' => 'discovery'])
                    ->contexts(['create', 'edit', 'pipeline']),

                FieldDefinition::make('auto_select_sources', 'boolean', 'Auto Select Sources')
                    ->default(false)
                    ->rules(['nullable', 'boolean'])
                    ->meta(['section' => 'discovery'])
                    ->contexts(['create', 'edit', 'pipeline']),

                FieldDefinition::make('posts_per_run', 'number', 'Posts Per Run')
                    ->default(1)
                    ->rules(['nullable', 'integer', 'min:1', 'max:50'])
                    ->meta(['section' => 'schedule'])
                    ->contexts(['create', 'edit', 'pipeline']),

                FieldDefinition::make('frequency', 'select', 'Frequency')
                    ->default('daily')
                    ->rules(['nullable', 'string', 'in:' . implode(',', self::frequencyOptions())])
                    ->options(self::optionMap(self::frequencyOptions()))
                    ->meta([
                        'empty_label' => 'Select frequency...',
                        'section' => 'schedule',
                    ])
                    ->contexts(['create', 'edit', 'pipeline']),

                FieldDefinition::make('run_at_time', 'time', 'Run At')
                    ->default('09:00')
                    ->rules(['nullable', 'date_format:H:i'])
                    ->meta(['section' => 'schedule'])
                    ->contexts(['create', 'edit', 'pipeline']),

                FieldDefinition::make('drip_minutes', 'number', 'Drip Minutes')
                    ->default(60)
                    ->rules(['nullable', 'integer', 'min:1', 'max:1440'])
                    ->meta(['section' => 'schedule'])
                    ->contexts(['create', 'edit', 'pipeline']),

                FieldDefinition::make('is_active', 'boolean', 'Preset Active')
                    ->default(true)
                    ->rules(['nullable', 'boolean'])
                    ->meta(['section' => 'defaults'])
                    ->contexts(['create', 'edit']),

                FieldDefinition::make('is_default', 'boolean', 'Set As Default Preset')
                    ->default(false)
                    ->rules(['nullable', 'boolean'])
                    ->meta(['section' => 'defaults'])
                    ->contexts(['create', 'edit']),
            ]);
    }

    public static function values(null|CampaignPreset|array $source = null, array $overrides = []): array
    {
        $mode = $source instanceof CampaignPreset || (is_array($source) && array_key_exists('id', $source))
            ? 'edit'
            : 'create';

        return app(FormRuntimeService::class)->hydrate(self::FORM_KEY, $source, $overrides, [
            'context' => $mode,
            'mode' => $mode,
            'record' => $source instanceof CampaignPreset ? $source : null,
        ]);
    }

    public static function schema(string $context = 'pipeline'): array
    {
        return app(FormRuntimeService::class)->schema(self::FORM_KEY, $context, [
            'context' => $context,
            'mode' => $context,
        ]);
    }

    protected static function actionUrl(string $mode, ?CampaignPreset $record = null): ?string
    {
        return $mode === 'edit' && $record
            ? route('campaigns.presets.update', $record->id)
            : route('campaigns.presets.store');
    }

    protected static function userOptions(): array
    {
        return User::orderBy('name')->pluck('name', 'id')->map(fn ($value) => (string) $value)->toArray();
    }

    protected static function discoveryModes(): array
    {
        return app(NewsDiscoveryOptionsService::class)->discoveryModes();
    }

    protected static function finalArticleMethods(): array
    {
        return app(NewsDiscoveryOptionsService::class)->finalArticleMethods();
    }

    protected static function frequencyOptions(): array
    {
        return array_values((array) config('hws-publish.campaign_intervals', ['hourly', 'daily', 'weekly', 'monthly']));
    }

    protected static function newsCategoryOptions(): array
    {
        return self::optionMap(app(NewsDiscoveryOptionsService::class)->newsCategories());
    }

    protected static function optionMap(array $values): array
    {
        $options = [];
        foreach ($values as $value) {
            $label = str_replace(['-', '_'], ' ', (string) $value);
            $options[(string) $value] = ucwords($label);
        }

        return $options;
    }

    protected static function stringifyQueries(array|string|null $queries): string
    {
        if (is_string($queries)) {
            return trim($queries);
        }

        return collect((array) $queries)
            ->map(fn ($query) => trim((string) $query))
            ->filter()
            ->values()
            ->implode("\n");
    }

    protected static function parseQueries(array|string|null $queries): array
    {
        if (is_array($queries)) {
            return collect($queries)
                ->map(fn ($query) => trim((string) $query))
                ->filter()
                ->values()
                ->all();
        }

        return collect(preg_split('/\r\n|\r|\n/', (string) $queries) ?: [])
            ->map(fn ($query) => trim((string) $query))
            ->filter()
            ->values()
            ->all();
    }
}
