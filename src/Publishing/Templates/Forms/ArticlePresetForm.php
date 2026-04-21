<?php

namespace hexa_app_publish\Publishing\Templates\Forms;

use hexa_app_publish\Publishing\Accounts\Models\PublishAccount;
use hexa_app_publish\Publishing\Templates\Models\PublishTemplate;
use hexa_app_publish\Support\AiModelCatalog;
use hexa_core\Forms\Definitions\FieldDefinition;
use hexa_core\Forms\Definitions\FormDefinition;
use hexa_core\Forms\Services\FormRegistryService;
use hexa_core\ListRegistry\Models\ListItem;

class ArticlePresetForm
{
    public const FORM_KEY = 'app-publish.article-preset';

    public static function make(array $context = []): FormDefinition
    {
        $mode = (string) ($context['mode'] ?? $context['context'] ?? 'create');
        /** @var PublishTemplate|null $record */
        $record = $context['record'] ?? null;

        return FormDefinition::make(self::FORM_KEY)
            ->title($mode === 'edit' ? 'Edit Article Preset' : 'Create Article Preset')
            ->model(PublishTemplate::class)
            ->action(self::actionUrl($mode, $record))
            ->method($mode === 'edit' ? 'PUT' : 'POST')
            ->meta([
                'grid_classes' => 'grid-cols-1 md:grid-cols-2',
            ])
            ->inferFromModel(true, [
                'exclude' => [
                    'id',
                    'created_at',
                    'updated_at',
                    'deleted_at',
                    'status',
                    'description',
                    'ai_engine',
                    'photos_per_article',
                    'photo_sources',
                    'structure',
                    'rules',
                ],
            ])
            ->fields([
                FieldDefinition::make('publish_account_id', 'select', 'Account')
                    ->required()
                    ->rules(['required', 'exists:publish_accounts,id'])
                    ->options(fn (array $context = []) => self::accountOptions($context))
                    ->contexts(['create', 'edit'])
                    ->meta([
                        'empty_label' => 'Select account...',
                        'section' => 'account',
                    ]),

                FieldDefinition::make('name', 'text', 'Article Preset Name')
                    ->required()
                    ->rules(['required', 'string', 'max:255'])
                    ->placeholder('e.g. Her Forward News Report')
                    ->columns('md:col-span-3')
                    ->contexts(['create', 'edit'])
                    ->meta([
                        'section' => 'basic',
                    ]),

                FieldDefinition::make('article_type', 'select', 'Article Type')
                    ->rules(['nullable', 'string', 'max:50'])
                    ->options(self::articleTypeOptions())
                    ->meta([
                        'empty_label' => '— None —',
                        'section' => 'basic',
                    ])
                    ->contexts(['create', 'edit']),

                FieldDefinition::make('ai_prompt', 'textarea', 'Writing Instructions')
                    ->rules(['nullable', 'string'])
                    ->placeholder('Write like a real newsroom. No fluff.')
                    ->columns('md:col-span-2')
                    ->meta([
                        'rows' => 5,
                        'section' => 'copy',
                    ])
                    ->contexts(['create', 'edit', 'pipeline']),

                FieldDefinition::make('headline_rules', 'textarea', 'Headline Rules')
                    ->rules(['nullable', 'string'])
                    ->placeholder('One clear angle. No stitched headlines.')
                    ->columns('md:col-span-2')
                    ->meta([
                        'rows' => 3,
                        'section' => 'copy',
                    ])
                    ->contexts(['create', 'edit', 'pipeline']),

                FieldDefinition::make('tone', 'checkbox_group', 'Tone')
                    ->multiple()
                    ->default([])
                    ->rules(['nullable', 'array'])
                    ->options(self::toneOptions())
                    ->columns('md:col-span-2')
                    ->meta([
                        'section' => 'content',
                    ])
                    ->contexts(['create', 'edit', 'pipeline']),

                FieldDefinition::make('word_count_min', 'number', 'Min Words')
                    ->default(fn () => config('hws-publish.defaults.word_count_min', 800))
                    ->rules(['nullable', 'integer', 'min:100'])
                    ->placeholder((string) config('hws-publish.defaults.word_count_min', 800))
                    ->meta([
                        'section' => 'content',
                    ])
                    ->contexts(['create', 'edit', 'pipeline']),

                FieldDefinition::make('word_count_max', 'number', 'Max Words')
                    ->default(fn () => config('hws-publish.defaults.word_count_max', 1500))
                    ->rules(['nullable', 'integer', 'min:100'])
                    ->placeholder((string) config('hws-publish.defaults.word_count_max', 1500))
                    ->meta([
                        'section' => 'content',
                    ])
                    ->contexts(['create', 'edit', 'pipeline']),

                FieldDefinition::make('max_links', 'number', 'Max Links Per Article')
                    ->rules(['nullable', 'integer', 'min:0'])
                    ->placeholder((string) config('hws-publish.defaults.max_links_per_article', 5))
                    ->meta([
                        'section' => 'content',
                    ])
                    ->contexts(['create', 'edit', 'pipeline']),

                FieldDefinition::make('search_online_for_additional_context', 'boolean', 'Search Online For Additional Context')
                    ->default(true)
                    ->rules(['nullable', 'boolean'])
                    ->help('Default on. AI search runs first, then its fallback, then PHP/local discovery takes over.')
                    ->columns('md:col-span-2')
                    ->meta([
                        'section' => 'research',
                    ])
                    ->contexts(['create', 'edit', 'pipeline']),

                FieldDefinition::make('searching_agent', 'select', 'Online Search Primary Model')
                    ->default(fn () => self::defaultSearchPrimary())
                    ->rules(['nullable', 'string', 'max:100'])
                    ->options(fn () => self::allProviderModelOptions())
                    ->meta(['empty_label' => 'Select model...', 'section' => 'research'])
                    ->contexts(['create', 'edit', 'pipeline']),

                FieldDefinition::make('online_search_model_fallback', 'select', 'Online Search Fallback Model')
                    ->default(fn () => self::defaultSearchFallback())
                    ->rules(['nullable', 'string', 'max:100'])
                    ->options(fn () => self::allProviderModelOptions())
                    ->meta(['empty_label' => 'Select model...', 'section' => 'research'])
                    ->contexts(['create', 'edit', 'pipeline']),

                FieldDefinition::make('scraping_agent', 'select', 'Scrape AI Primary Model')
                    ->default(fn () => self::defaultSearchPrimary())
                    ->rules(['nullable', 'string', 'max:100'])
                    ->options(fn () => self::allProviderModelOptions())
                    ->help('PHP auto extraction always runs first. This model is only used after local extraction fails.')
                    ->meta(['empty_label' => 'Select model...', 'section' => 'research'])
                    ->contexts(['create', 'edit', 'pipeline']),

                FieldDefinition::make('scrape_ai_model_fallback', 'select', 'Scrape AI Fallback Model')
                    ->default(fn () => self::defaultSearchFallback())
                    ->rules(['nullable', 'string', 'max:100'])
                    ->options(fn () => self::allProviderModelOptions())
                    ->meta(['empty_label' => 'Select model...', 'section' => 'research'])
                    ->contexts(['create', 'edit', 'pipeline']),

                FieldDefinition::make('spinning_agent', 'select', 'Spin Primary Model')
                    ->default(fn () => self::defaultSpinPrimary())
                    ->rules(['nullable', 'string', 'max:100'])
                    ->options(fn () => self::allProviderModelOptions())
                    ->meta(['empty_label' => 'Select model...', 'section' => 'research'])
                    ->contexts(['create', 'edit', 'pipeline']),

                FieldDefinition::make('spin_model_fallback', 'select', 'Spin Fallback Model')
                    ->default(fn () => self::defaultSpinFallback())
                    ->rules(['nullable', 'string', 'max:100'])
                    ->options(fn () => self::allProviderModelOptions())
                    ->meta(['empty_label' => 'Select model...', 'section' => 'research'])
                    ->contexts(['create', 'edit', 'pipeline']),

                FieldDefinition::make('h2_notation', 'select', 'H2 Notation')
                    ->default('capital_case')
                    ->rules(['nullable', 'string', 'in:capital_case,sentence_case,title_case'])
                    ->options([
                        'capital_case' => 'Capital Case',
                        'sentence_case' => 'Sentence case',
                        'title_case' => 'Title Case',
                    ])
                    ->meta(['empty_label' => 'Select notation...', 'section' => 'media'])
                    ->contexts(['create', 'edit', 'pipeline']),

                FieldDefinition::make('inline_photo_min', 'number', 'Inline Photo Minimum')
                    ->default(2)
                    ->rules(['nullable', 'integer', 'min:0', 'max:10'])
                    ->meta(['section' => 'media'])
                    ->contexts(['create', 'edit', 'pipeline']),

                FieldDefinition::make('inline_photo_max', 'number', 'Inline Photo Maximum')
                    ->default(3)
                    ->rules(['nullable', 'integer', 'min:0', 'max:10'])
                    ->meta(['section' => 'media'])
                    ->contexts(['create', 'edit', 'pipeline']),

                FieldDefinition::make('featured_image_required', 'boolean', 'Featured Image Required')
                    ->default(true)
                    ->rules(['nullable', 'boolean'])
                    ->meta(['section' => 'media'])
                    ->contexts(['create', 'edit', 'pipeline']),

                FieldDefinition::make('featured_image_must_be_landscape', 'boolean', 'Featured Image Must Be Landscape')
                    ->default(true)
                    ->rules(['nullable', 'boolean'])
                    ->meta(['section' => 'media'])
                    ->contexts(['create', 'edit', 'pipeline']),

                FieldDefinition::make('block_blacklisted_photos', 'boolean', 'Block Blacklisted Photos')
                    ->default(true)
                    ->disabled()
                    ->rules(['nullable', 'boolean'])
                    ->help('Automatic for now. Featured images are Google searched, inline photos are Google searched or stock, and blacklisted sources are always blocked.')
                    ->meta([
                        'section' => 'media',
                        'dehydrated' => false,
                    ])
                    ->contexts(['create', 'edit', 'pipeline']),

                FieldDefinition::make('is_default', 'boolean', 'Set as default template')
                    ->default(false)
                    ->rules(['nullable', 'boolean'])
                    ->columns('md:col-span-2')
                    ->meta([
                        'section' => 'system',
                    ])
                    ->contexts(['create', 'edit']),
            ]);
    }

    public static function values(null|PublishTemplate|array $source = null, array $overrides = []): array
    {
        $values = [];
        $catalog = app(AiModelCatalog::class);

        if ($source instanceof PublishTemplate) {
            $values = $source->toArray();
        } elseif (is_array($source)) {
            $values = $source;
        }

        $legacyPhotoCount = (int) ($values['photos_per_article'] ?? 0);
        $values['search_online_for_additional_context'] = $values['search_online_for_additional_context'] ?? true;
        $values['searching_agent'] = $values['searching_agent'] ?? ($catalog->defaultSearchModel() ?: null);
        $values['online_search_model_fallback'] = $values['online_search_model_fallback'] ?? ($catalog->defaultSearchFallbackModel($values['searching_agent'] ?? null) ?: ($values['searching_agent'] ?? null));
        $values['scraping_agent'] = $values['scraping_agent'] ?? ($catalog->defaultSearchModel() ?: null);
        $values['scrape_ai_model_fallback'] = $values['scrape_ai_model_fallback'] ?? ($catalog->defaultSearchFallbackModel($values['scraping_agent'] ?? null) ?: ($values['scraping_agent'] ?? null));
        $values['spinning_agent'] = $values['spinning_agent'] ?? ($values['ai_engine'] ?? ($catalog->defaultSpinModel() ?: null));
        $values['spin_model_fallback'] = $values['spin_model_fallback'] ?? ($catalog->defaultSpinFallbackModel($values['spinning_agent'] ?? null) ?: ($values['spinning_agent'] ?? null));
        $values['h2_notation'] = $values['h2_notation'] ?? 'capital_case';
        $values['inline_photo_min'] = $values['inline_photo_min'] ?? ($legacyPhotoCount > 0 ? min($legacyPhotoCount, 2) : 2);
        $values['inline_photo_max'] = $values['inline_photo_max'] ?? ($legacyPhotoCount > 0 ? $legacyPhotoCount : 3);
        $values['featured_image_required'] = $values['featured_image_required'] ?? true;
        $values['featured_image_must_be_landscape'] = $values['featured_image_must_be_landscape'] ?? true;

        foreach ($overrides as $key => $value) {
            if ($value === null) {
                continue;
            }

            $values[$key] = $value;
        }

        return $values;
    }

    public static function schema(string $context = 'pipeline'): array
    {
        /** @var FormRegistryService $registry */
        $registry = app(FormRegistryService::class);
        $form = $registry->resolve(self::FORM_KEY, [
            'mode' => $context,
            'context' => $context,
        ]);

        $schema = [];
        foreach ($form->fieldsForContext($context) as $field) {
            if ($field->metaValue('dehydrated', true) === false) {
                continue;
            }

            $type = match ($field->type()) {
                'checkbox_group' => 'checkbox',
                default => $field->type(),
            };

            $entry = ['type' => $type];
            $options = $field->resolveOptions([
                'context' => $context,
                'mode' => $context,
            ]);

            if (!empty($options)) {
                $entry['options'] = $options;
            }

            $schema[$field->name()] = $entry;
        }

        return $schema;
    }

    public static function detectCompany(?string $engine): string
    {
        if (!$engine) {
            return self::defaultCompany();
        }

        foreach (self::companyModels() as $company => $models) {
            if (in_array($engine, $models, true)) {
                return $company;
            }
        }

        return self::defaultCompany();
    }

    protected static function actionUrl(string $mode, ?PublishTemplate $record = null): ?string
    {
        return $mode === 'edit' && $record
            ? route('publish.templates.update', $record->id)
            : route('publish.templates.store');
    }

    protected static function accountOptions(array $context = []): array
    {
        $accounts = PublishAccount::where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name']);

        /** @var PublishTemplate|null $record */
        $record = $context['record'] ?? null;
        if ($record && $record->publish_account_id && !$accounts->contains('id', $record->publish_account_id)) {
            $currentAccount = PublishAccount::find($record->publish_account_id);
            if ($currentAccount) {
                $accounts->push($currentAccount);
                $accounts = $accounts->sortBy('name')->values();
            }
        }

        return $accounts
            ->pluck('name', 'id')
            ->map(fn ($label) => (string) $label)
            ->toArray();
    }

    protected static function articleTypeOptions(): array
    {
        return self::labelMap(config('hws-publish.article_types', []));
    }

    protected static function toneOptions(): array
    {
        return [
            'Professional' => 'Professional',
            'Conversational' => 'Conversational',
            'Authoritative' => 'Authoritative',
            'Casual' => 'Casual',
            'Investigative' => 'Investigative',
            'Persuasive' => 'Persuasive',
        ];
    }

    protected static function companyOptions(): array
    {
        return app(AiModelCatalog::class)->companyOptions();
    }

    protected static function companyModels(): array
    {
        return app(AiModelCatalog::class)->companyModels();
    }

    protected static function flatModelOptions(): array
    {
        $models = [];
        foreach (self::companyModels() as $providerModels) {
            foreach ($providerModels as $model) {
                $models[$model] = $model;
            }
        }

        return $models;
    }

    protected static function photoSourceOptions(): array
    {
        return self::labelMap(config('hws-publish.photo_sources', []), fn (string $value) => ucfirst($value));
    }

    protected static function defaultCompany(): string
    {
        return app(AiModelCatalog::class)->detectCompany(app(AiModelCatalog::class)->defaultSpinModel());
    }

    protected static function defaultEngine(): ?string
    {
        return app(AiModelCatalog::class)->defaultSpinModel();
    }

    protected static function defaultPhotoSources(array $context = []): array
    {
        if (($context['mode'] ?? $context['context'] ?? 'create') === 'edit') {
            return [];
        }

        return array_values((array) config('hws-publish.photo_sources', []));
    }

    protected static function allProviderModelOptions(): array
    {
        return app(AiModelCatalog::class)->selectOptions();
    }

    protected static function defaultSearchPrimary(): ?string
    {
        return app(AiModelCatalog::class)->defaultSearchModel();
    }

    protected static function defaultSearchFallback(): ?string
    {
        $catalog = app(AiModelCatalog::class);
        $primary = $catalog->defaultSearchModel();

        return $catalog->defaultSearchFallbackModel($primary) ?: $primary;
    }

    protected static function defaultSpinPrimary(): ?string
    {
        return app(AiModelCatalog::class)->defaultSpinModel();
    }

    protected static function defaultSpinFallback(): ?string
    {
        $catalog = app(AiModelCatalog::class);
        $primary = $catalog->defaultSpinModel();

        return $catalog->defaultSpinFallbackModel($primary) ?: $primary;
    }

    protected static function listItemOptions(string $category): array
    {
        $values = ListItem::getValues($category);
        $options = [];
        foreach ($values as $val) {
            $options[$val] = $val;
        }
        return $options;
    }

    protected static function labelMap(array $values, ?callable $labelResolver = null): array
    {
        $labelResolver ??= fn (string $value) => ucwords(str_replace('-', ' ', $value));

        $options = [];
        foreach ($values as $value) {
            $stringValue = (string) $value;
            $options[$stringValue] = (string) $labelResolver($stringValue);
        }

        return $options;
    }
}
