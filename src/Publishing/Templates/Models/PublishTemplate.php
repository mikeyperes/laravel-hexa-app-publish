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
     * Return field schema for preset field rendering.
     * Each field declares its type and options — the UI renders accordingly.
     *
     * @return array
     */
    public static function getFieldSchema(): array
    {
        $models = collect(config('anthropic.models', []))->pluck('name', 'id')->toArray();
        return [
            'ai_engine'          => ['type' => 'select', 'options' => $models],
            'article_type'       => ['type' => 'select', 'options' => ['editorial','opinion','news-report','local-news','expert-article','pr-full-feature','press-release','listicle','how-to','review']],
            'ai_prompt'          => ['type' => 'textarea'],
            'tone'               => ['type' => 'array'],
            'word_count_min'     => ['type' => 'number'],
            'word_count_max'     => ['type' => 'number'],
            'photos_per_article' => ['type' => 'number'],
            'photo_sources'      => ['type' => 'array'],
            'max_links'          => ['type' => 'number'],
            'structure'          => ['type' => 'array'],
            'rules'              => ['type' => 'array'],
        ];
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
