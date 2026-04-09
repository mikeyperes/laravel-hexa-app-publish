<?php

namespace hexa_app_publish\Publishing\Templates\Forms;

use hexa_app_publish\Publishing\Accounts\Models\PublishAccount;
use hexa_app_publish\Publishing\Templates\Models\PublishTemplate;
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
                    'photos_per_article',
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

                FieldDefinition::make('name', 'text', 'Template Name')
                    ->required()
                    ->rules(['required', 'string', 'max:255'])
                    ->placeholder('e.g. Tech Press Release')
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

                FieldDefinition::make('ai_company', 'select', 'AI Company')
                    ->default(fn () => self::defaultCompany())
                    ->options(self::companyOptions())
                    ->view('app-publish::forms.fields.ai-company-select')
                    ->meta([
                        'dehydrated' => false,
                        'empty_label' => 'Select company...',
                        'section' => 'basic',
                    ])
                    ->contexts(['create', 'edit']),

                FieldDefinition::make('ai_engine', 'select', 'AI Model')
                    ->default(fn () => self::defaultEngine())
                    ->rules(['nullable', 'string', 'max:100'])
                    ->options(fn () => self::flatModelOptions())
                    ->view('app-publish::forms.fields.ai-engine-select')
                    ->meta([
                        'empty_label' => 'Select model...',
                        'company_models' => self::companyModels(),
                        'section' => 'basic',
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

                FieldDefinition::make('photo_sources', 'checkbox_group', 'Photo Sources')
                    ->multiple()
                    ->default(fn (array $context = []) => self::defaultPhotoSources($context))
                    ->rules(['nullable', 'array'])
                    ->options(self::photoSourceOptions())
                    ->columns('md:col-span-2')
                    ->meta([
                        'section' => 'content',
                    ])
                    ->contexts(['create', 'edit', 'pipeline']),

                FieldDefinition::make('description', 'textarea', 'Description')
                    ->rules(['nullable', 'string'])
                    ->placeholder('What this template is for...')
                    ->columns('md:col-span-2')
                    ->meta([
                        'rows' => 2,
                        'section' => 'copy',
                    ])
                    ->contexts(['create', 'edit', 'pipeline']),

                FieldDefinition::make('ai_prompt', 'textarea', 'AI Prompt / Instructions')
                    ->rules(['nullable', 'string'])
                    ->placeholder('Custom instructions for the AI when using this template.')
                    ->columns('md:col-span-2')
                    ->meta([
                        'rows' => 5,
                        'section' => 'copy',
                    ])
                    ->contexts(['create', 'edit', 'pipeline']),

                // ── WordPress publishing fields (merged from WP Preset) ──
                FieldDefinition::make('follow_links', 'select', 'Follow Links')
                    ->default('follow')
                    ->rules(['nullable', 'string', 'in:follow,nofollow,sponsored,ugc'])
                    ->options(['follow' => 'Follow', 'nofollow' => 'Nofollow', 'sponsored' => 'Sponsored', 'ugc' => 'UGC'])
                    ->meta(['empty_label' => '— Select —', 'section' => 'wordpress'])
                    ->contexts(['create', 'edit', 'pipeline']),

                FieldDefinition::make('image_preference', 'select', 'Image Preference')
                    ->rules(['nullable', 'string', 'max:100'])
                    ->options(fn () => self::listItemOptions('image_preferences'))
                    ->meta(['empty_label' => 'Select preference...', 'section' => 'wordpress'])
                    ->contexts(['create', 'edit', 'pipeline']),

                FieldDefinition::make('default_publish_action', 'select', 'Default Publish Action')
                    ->rules(['nullable', 'string', 'max:50'])
                    ->options([
                        'publish_immediate' => 'Publish Immediately',
                        'draft_local' => 'Save as Local Draft',
                        'draft_wordpress' => 'Save as WordPress Draft',
                        'schedule' => 'Schedule for Later',
                    ])
                    ->meta(['empty_label' => 'Select action...', 'section' => 'wordpress'])
                    ->contexts(['create', 'edit', 'pipeline']),

                FieldDefinition::make('default_category_count', 'number', 'Category Count')
                    ->default(3)
                    ->rules(['nullable', 'integer', 'min:0', 'max:20'])
                    ->placeholder('3')
                    ->meta(['section' => 'wordpress'])
                    ->contexts(['create', 'edit', 'pipeline']),

                FieldDefinition::make('default_tag_count', 'number', 'Tag Count')
                    ->default(5)
                    ->rules(['nullable', 'integer', 'min:0', 'max:50'])
                    ->placeholder('5')
                    ->meta(['section' => 'wordpress'])
                    ->contexts(['create', 'edit', 'pipeline']),

                FieldDefinition::make('image_layout', 'select', 'Image Layout')
                    ->rules(['nullable', 'string', 'max:100'])
                    ->options(fn () => self::listItemOptions('image_layout_rules'))
                    ->meta(['empty_label' => 'Select layout...', 'section' => 'wordpress'])
                    ->contexts(['create', 'edit', 'pipeline']),

                FieldDefinition::make('is_default', 'boolean', 'Set as default template')
                    ->default(false)
                    ->rules(['nullable', 'boolean'])
                    ->columns('md:col-span-2')
                    ->meta([
                        'section' => 'wordpress',
                    ])
                    ->contexts(['create', 'edit']),
            ]);
    }

    public static function values(null|PublishTemplate|array $source = null, array $overrides = []): array
    {
        $values = [];

        if ($source instanceof PublishTemplate) {
            $values = $source->toArray();
        } elseif (is_array($source)) {
            $values = $source;
        }

        $values['ai_company'] = $values['ai_company'] ?? self::detectCompany($values['ai_engine'] ?? null);

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
        return [
            'Anthropic' => 'Anthropic',
            'OpenAI' => 'OpenAI',
        ];
    }

    protected static function companyModels(): array
    {
        return [
            'Anthropic' => array_values(array_filter((array) config('anthropic.available_models', []))),
            'OpenAI' => array_values(array_filter((array) config('chatgpt.available_models', []))),
        ];
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
        foreach (self::companyModels() as $company => $models) {
            if (!empty($models)) {
                return $company;
            }
        }

        return 'Anthropic';
    }

    protected static function defaultEngine(): ?string
    {
        $anthropicModels = self::companyModels()['Anthropic'] ?? [];
        if (in_array('claude-opus-4-6', $anthropicModels, true)) {
            return 'claude-opus-4-6';
        }

        foreach (self::companyModels() as $models) {
            if (!empty($models)) {
                return $models[0];
            }
        }

        return null;
    }

    protected static function defaultPhotoSources(array $context = []): array
    {
        if (($context['mode'] ?? $context['context'] ?? 'create') === 'edit') {
            return [];
        }

        return array_values((array) config('hws-publish.photo_sources', []));
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
