<?php

namespace hexa_app_publish\Publishing\Presets\Forms;

use hexa_app_publish\Publishing\Presets\Models\PublishPreset;
use hexa_app_publish\Publishing\Sites\Models\PublishSite;
use hexa_app_publish\Support\AiModelCatalog;
use hexa_core\Forms\Definitions\FieldDefinition;
use hexa_core\Forms\Definitions\FormDefinition;
use hexa_core\Forms\Runtime\FormRuntimeService;
use hexa_core\Models\ListItem;
use hexa_core\Models\User;

/**
 * WordPress Preset form definition — single source of truth for
 * both the edit form and the pipeline preset-fields rendering.
 */
class WordPressPresetForm
{
    public const FORM_KEY = 'app-publish.wordpress-preset';

    /**
     * Build the form definition.
     *
     * @param array $context ['mode' => 'create'|'edit'|'pipeline', 'record' => PublishPreset|null]
     * @return FormDefinition
     */
    public static function make(array $context = []): FormDefinition
    {
        $mode = (string) ($context['mode'] ?? $context['context'] ?? 'create');
        /** @var PublishPreset|null $record */
        $record = $context['record'] ?? null;

        return FormDefinition::make(self::FORM_KEY)
            ->title($mode === 'edit' ? 'Edit WordPress Preset' : 'Create WordPress Preset')
            ->model(PublishPreset::class)
            ->action(self::actionUrl($mode, $record))
            ->method($mode === 'edit' ? 'PUT' : 'POST')
            ->meta(['grid_classes' => 'grid-cols-1 md:grid-cols-2'])
            ->fields([
                FieldDefinition::make('user_id', 'select', 'Account')
                    ->required()
                    ->rules(['required', 'exists:users,id'])
                    ->options(fn () => self::userOptions())
                    ->meta([
                        'empty_label' => 'Select user...',
                        'section' => 'account',
                    ])
                    ->contexts(['create', 'edit']),

                FieldDefinition::make('name', 'text', 'Preset Name')
                    ->required()
                    ->rules(['required', 'string', 'max:255'])
                    ->placeholder('e.g. Standard Editorial')
                    ->columns('md:col-span-2')
                    ->meta(['section' => 'basic'])
                    ->contexts(['create', 'edit']),

                FieldDefinition::make('default_site_id', 'select', 'Default WordPress Site')
                    ->rules(['nullable', 'integer'])
                    ->options(fn () => self::siteOptions())
                    ->meta([
                        'empty_label' => '— No default site —',
                        'section' => 'basic',
                    ])
                    ->contexts(['create', 'edit']),

                FieldDefinition::make('follow_links', 'select', 'Follow Links')
                    ->default('follow')
                    ->rules(['nullable', 'string', 'in:follow,nofollow,sponsored,ugc'])
                    ->options([
                        'follow' => 'Follow',
                        'nofollow' => 'Nofollow',
                        'sponsored' => 'Sponsored',
                        'ugc' => 'UGC',
                    ])
                    ->meta([
                        'empty_label' => '— Select —',
                        'section' => 'content',
                    ])
                    ->contexts(['create', 'edit', 'pipeline']),

                FieldDefinition::make('image_preference', 'select', 'Image Preference')
                    ->rules(['nullable', 'string', 'max:100'])
                    ->options(fn () => self::imagePreferenceOptions())
                    ->meta([
                        'empty_label' => 'Select preference...',
                        'section' => 'content',
                    ])
                    ->contexts(['create', 'edit', 'pipeline']),

                FieldDefinition::make('default_publish_action', 'select', 'Default Publish Action')
                    ->rules(['nullable', 'string', 'max:50'])
                    ->options(self::publishActionOptions())
                    ->meta([
                        'empty_label' => 'Select action...',
                        'section' => 'content',
                    ])
                    ->contexts(['create', 'edit', 'pipeline']),

                FieldDefinition::make('default_category_count', 'number', 'Category Count')
                    ->default(3)
                    ->rules(['nullable', 'integer', 'min:0', 'max:20'])
                    ->placeholder('3')
                    ->meta(['section' => 'content'])
                    ->contexts(['create', 'edit', 'pipeline']),

                FieldDefinition::make('default_tag_count', 'number', 'Tag Count')
                    ->default(5)
                    ->rules(['nullable', 'integer', 'min:0', 'max:50'])
                    ->placeholder('5')
                    ->meta(['section' => 'content'])
                    ->contexts(['create', 'edit', 'pipeline']),

                FieldDefinition::make('image_layout', 'select', 'Image Layout')
                    ->rules(['nullable', 'string', 'max:100'])
                    ->options(fn () => self::imageLayoutOptions())
                    ->meta([
                        'empty_label' => 'Select layout...',
                        'section' => 'content',
                    ])
                    ->contexts(['create', 'edit', 'pipeline']),

                FieldDefinition::make('searching_agent', 'select', 'Searching Agent')
                    ->rules(['nullable', 'string', 'max:100'])
                    ->options(fn () => self::aiModelOptions())
                    ->meta([
                        'empty_label' => 'Default (Claude Haiku)',
                        'section' => 'ai_agents',
                    ])
                    ->contexts(['create', 'edit', 'pipeline']),

                FieldDefinition::make('scraping_agent', 'select', 'Scraping Agent')
                    ->rules(['nullable', 'string', 'max:100'])
                    ->options(fn () => self::aiModelOptions())
                    ->meta([
                        'empty_label' => 'Default (Claude Haiku)',
                        'section' => 'ai_agents',
                    ])
                    ->contexts(['create', 'edit', 'pipeline']),

                FieldDefinition::make('spinning_agent', 'select', 'Spinning Agent')
                    ->rules(['nullable', 'string', 'max:100'])
                    ->options(fn () => self::aiModelOptions())
                    ->meta([
                        'empty_label' => 'Default (Claude Opus)',
                        'section' => 'ai_agents',
                    ])
                    ->contexts(['create', 'edit', 'pipeline']),

                FieldDefinition::make('article_format', 'select', 'Article Format')
                    ->rules(['nullable', 'string', 'max:50'])
                    ->options([
                        'standard' => 'Standard',
                        'listicle' => 'Listicle',
                        'how-to' => 'How-To',
                        'opinion' => 'Opinion',
                        'review' => 'Review',
                    ])
                    ->meta([
                        'empty_label' => 'Select format...',
                        'section' => 'content',
                    ])
                    ->contexts(['create', 'edit', 'pipeline']),

                FieldDefinition::make('tone', 'text', 'Tone')
                    ->rules(['nullable', 'string', 'max:100'])
                    ->placeholder('e.g. Professional, Casual')
                    ->meta(['section' => 'content'])
                    ->contexts(['create', 'edit', 'pipeline']),

                FieldDefinition::make('is_default', 'boolean', 'Set as default preset')
                    ->default(false)
                    ->rules(['nullable', 'boolean'])
                    ->columns('md:col-span-2')
                    ->meta(['section' => 'defaults'])
                    ->contexts(['create', 'edit']),
            ]);
    }

    /**
     * Build values array from a preset record or overrides.
     *
     * @param PublishPreset|array|null $source
     * @param array $overrides
     * @return array
     */
    public static function values(null|PublishPreset|array $source = null, array $overrides = []): array
    {
        $values = [];

        if ($source instanceof PublishPreset) {
            $values = $source->toArray();
        } elseif (is_array($source)) {
            $values = $source;
        }

        foreach ($overrides as $key => $value) {
            if ($value !== null) {
                $values[$key] = $value;
            }
        }

        return $values;
    }

    /**
     * Generate pipeline schema from the form definition.
     * This is the SINGLE SOURCE OF TRUTH — called by PublishPreset::getFieldSchema().
     *
     * @param string $context
     * @return array
     */
    public static function schema(string $context = 'pipeline'): array
    {
        return app(FormRuntimeService::class)->schema(self::FORM_KEY, $context, [
            'context' => $context,
            'mode' => $context,
        ]);
    }

    // ── Option providers ──────────────────────────────────────

    /**
     * @return array
     */
    protected static function userOptions(): array
    {
        return User::orderBy('name')->pluck('name', 'id')->map(fn ($v) => (string) $v)->toArray();
    }

    /**
     * @return array
     */
    protected static function siteOptions(): array
    {
        return PublishSite::orderBy('name')
            ->get(['id', 'name', 'url'])
            ->pluck('name', 'id')
            ->map(fn ($name, $id) => (string) $name)
            ->toArray();
    }

    /**
     * @return array
     */
    protected static function imagePreferenceOptions(): array
    {
        return self::listItemOptions('image_preferences');
    }

    /**
     * @return array
     */
    protected static function imageLayoutOptions(): array
    {
        return self::listItemOptions('image_layout_rules');
    }

    /**
     * Build AI model options from all installed providers.
     *
     * @return array
     */
    protected static function aiModelOptions(): array
    {
        return app(AiModelCatalog::class)->selectOptions();
    }

    /**
     * @return array
     */
    protected static function publishActionOptions(): array
    {
        return [
            'publish_immediate' => 'Publish Immediately',
            'draft_local'       => 'Save as Local Draft',
            'draft_wordpress'   => 'Save as WordPress Draft',
            'schedule'          => 'Schedule for Later',
        ];
    }

    /**
     * Build key => label options from ListItem values.
     *
     * @param string $category
     * @return array
     */
    protected static function listItemOptions(string $category): array
    {
        $values = ListItem::getValues($category);
        $options = [];
        foreach ($values as $val) {
            $options[$val] = $val;
        }
        return $options;
    }

    /**
     * @param string $mode
     * @param PublishPreset|null $record
     * @return string|null
     */
    protected static function actionUrl(string $mode, ?PublishPreset $record = null): ?string
    {
        if ($mode === 'edit' && $record) {
            return route('publish.presets.update', $record->id);
        }
        return route('publish.presets.store');
    }
}
