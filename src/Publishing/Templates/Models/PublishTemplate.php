<?php

namespace hexa_app_publish\Publishing\Templates\Models;

use hexa_app_publish\Publishing\Accounts\Models\PublishAccount;
use hexa_app_publish\Publishing\Accounts\Models\PublishAccountUser;
use hexa_app_publish\Publishing\Articles\Models\PublishArticle;
use hexa_app_publish\Publishing\Articles\Models\PublishBookmark;
use hexa_app_publish\Publishing\Campaigns\Models\PublishCampaign;
use hexa_app_publish\Publishing\Sites\Models\PublishSite;
use hexa_app_publish\Publishing\Presets\Models\PublishPreset;
use hexa_app_publish\Publishing\Prompts\Models\PublishPrompt;
use hexa_app_publish\Publishing\Settings\Models\PublishMasterSetting;
use hexa_app_publish\Discovery\Sources\Models\PublishUsedSource;
use hexa_app_publish\Discovery\Links\Models\PublishLinkList;
use hexa_app_publish\Discovery\Links\Models\PublishSitemap;
use hexa_app_publish\Quality\Detection\Models\AiActivityLog;
use hexa_app_publish\Quality\Detection\Models\AiDetectionLog;
use hexa_app_publish\Quality\SmartEdits\Models\AiSmartEditTemplate;
use hexa_app_publish\Publishing\Templates\Forms\ArticlePresetForm;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PublishTemplate extends Model
{
    protected $table = 'publish_templates';

    protected $fillable = [
        'publish_account_id',
        'name',
        'status',
        'is_default',
        'article_type',
        'description',
        'ai_prompt',
        'headline_rules',
        'ai_engine',
        'tone',
        'word_count_min',
        'word_count_max',
        'photos_per_article',
        'photo_sources',
        'max_links',
        'search_online_for_additional_context',
        'online_search_model_fallback',
        'scrape_ai_model_fallback',
        'spin_model_fallback',
        'h2_notation',
        'inline_photo_min',
        'inline_photo_max',
        'featured_image_required',
        'featured_image_must_be_landscape',
        'structure',
        'rules',
        'searching_agent',
        'scraping_agent',
        'spinning_agent',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'tone' => 'array',
        'photo_sources' => 'array',
        'search_online_for_additional_context' => 'boolean',
        'featured_image_required' => 'boolean',
        'featured_image_must_be_landscape' => 'boolean',
        'structure' => 'array',
        'rules' => 'array',
    ];

    /**
     * Return field schema for pipeline-style preset rendering.
     */
    public static function getFieldSchema(string $context = 'pipeline'): array
    {
        return ArticlePresetForm::schema($context);
    }

    /**
     * @return BelongsTo
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(PublishAccount::class, 'publish_account_id');
    }

    /**
     * @return HasMany
     */
    public function campaigns(): HasMany
    {
        return $this->hasMany(PublishCampaign::class, 'publish_template_id');
    }

    /**
     * @return HasMany
     */
    public function articles(): HasMany
    {
        return $this->hasMany(PublishArticle::class, 'publish_template_id');
    }
}
