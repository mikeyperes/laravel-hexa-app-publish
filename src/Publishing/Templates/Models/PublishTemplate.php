<?php

namespace hexa_app_publish\Publishing\Templates\Models;

use hexa_app_publish\Models\PublishAccount;
use hexa_app_publish\Models\PublishAccountUser;
use hexa_app_publish\Models\PublishArticle;
use hexa_app_publish\Models\PublishBookmark;
use hexa_app_publish\Models\PublishCampaign;
use hexa_app_publish\Models\PublishSite;
use hexa_app_publish\Models\PublishPreset;
use hexa_app_publish\Models\PublishPrompt;
use hexa_app_publish\Models\PublishMasterSetting;
use hexa_app_publish\Models\PublishUsedSource;
use hexa_app_publish\Models\PublishLinkList;
use hexa_app_publish\Models\PublishSitemap;
use hexa_app_publish\Models\AiActivityLog;
use hexa_app_publish\Models\AiDetectionLog;
use hexa_app_publish\Models\AiSmartEditTemplate;
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
        'ai_engine',
        'tone',
        'word_count_min',
        'word_count_max',
        'photos_per_article',
        'photo_sources',
        'max_links',
        'structure',
        'rules',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'tone' => 'array',
        'photo_sources' => 'array',
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
