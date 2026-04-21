<?php

namespace hexa_app_publish\Publishing\Articles\Models;

use hexa_app_publish\Publishing\Accounts\Models\PublishAccount;
use hexa_app_publish\Publishing\Accounts\Models\PublishAccountUser;
use hexa_app_publish\Publishing\Articles\Models\PublishBookmark;
use hexa_app_publish\Publishing\Campaigns\Models\PublishCampaign;
use hexa_app_publish\Publishing\Sites\Models\PublishSite;
use hexa_app_publish\Publishing\Templates\Models\PublishTemplate;
use hexa_app_publish\Publishing\Presets\Models\PublishPreset;
use hexa_app_publish\Publishing\Pipeline\Models\PublishPipelineState;
use hexa_app_publish\Publishing\Pipeline\Models\PublishPipelineRun;
use hexa_app_publish\Publishing\Pipeline\Models\PublishPipelineOperation;
use hexa_app_publish\Publishing\Articles\Models\PublishArticleActivity;
use hexa_app_publish\Publishing\Prompts\Models\PublishPrompt;
use hexa_app_publish\Publishing\Settings\Models\PublishMasterSetting;
use hexa_app_publish\Discovery\Sources\Models\PublishUsedSource;
use hexa_app_publish\Discovery\Links\Models\PublishLinkList;
use hexa_app_publish\Discovery\Links\Models\PublishSitemap;
use hexa_app_publish\Quality\Detection\Models\AiActivityLog;
use hexa_app_publish\Quality\Detection\Models\AiDetectionLog;
use hexa_app_publish\Quality\SmartEdits\Models\AiSmartEditTemplate;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use hexa_core\Models\User;

class PublishArticle extends Model
{
    protected $table = 'publish_articles';

    protected $fillable = [
        'user_id',
        'pipeline_session_id',
        'publish_account_id',
        'publish_site_id',
        'publish_campaign_id',
        'publish_template_id',
        'preset_id',
        'article_id',
        'title',
        'body',
        'excerpt',
        'article_type',
        'ai_engine_used',
        'ai_cost',
        'resolved_prompt',
        'ai_tokens_input',
        'ai_tokens_output',
        'ai_provider',
        'status',
        'delivery_mode',
        'source_articles',
        'photos',
        'wp_images',
        'links_injected',
        'categories',
        'tags',
        'ai_detection_score',
        'seo_score',
        'seo_data',
        'word_count',
        'wp_post_id',
        'wp_post_url',
        'wp_status',
        'published_at',
        'scheduled_for',
        'created_by',
        'author',
        'user_ip',
        'photo_suggestions',
        'featured_image_search',
        'notes',
    ];

    protected $casts = [
        'source_articles' => 'array',
        'photos' => 'array',
        'wp_images' => 'array',
        'photo_suggestions' => 'array',
        'links_injected' => 'array',
        'categories' => 'array',
        'tags' => 'array',
        'seo_data' => 'array',
        'published_at' => 'datetime',
        'scheduled_for' => 'datetime',
    ];

    /**
     * Generate a unique article ID: ART-YYYYMMDD-NNN.
     *
     * @return string
     */
    public static function generateArticleId(): string
    {
        $date = now()->format('Ymd');
        $prefix = "ART-{$date}-";
        $latest = static::where('article_id', 'like', "{$prefix}%")
            ->orderByDesc('article_id')
            ->value('article_id');

        $next = $latest ? ((int) substr($latest, -3)) + 1 : 1;

        return $prefix . str_pad($next, 3, '0', STR_PAD_LEFT);
    }

    /**
     * @return BelongsTo
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(PublishAccount::class, 'publish_account_id');
    }

    /**
     * @return BelongsTo
     */
    public function site(): BelongsTo
    {
        return $this->belongsTo(PublishSite::class, 'publish_site_id');
    }

    /**
     * @return BelongsTo
     */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(PublishCampaign::class, 'publish_campaign_id');
    }

    /**
     * @return BelongsTo
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(PublishTemplate::class, 'publish_template_id');
    }

    public function pipelineState(): HasOne
    {
        return $this->hasOne(PublishPipelineState::class, 'publish_article_id');
    }

    public function pipelineRuns(): HasMany
    {
        return $this->hasMany(PublishPipelineRun::class, 'publish_article_id');
    }

    public function pipelineOperations(): HasMany
    {
        return $this->hasMany(PublishPipelineOperation::class, 'publish_article_id');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(PublishArticleActivity::class, 'publish_article_id');
    }

    /**
     * @return HasMany
     */
    public function usedSources(): HasMany
    {
        return $this->hasMany(PublishUsedSource::class, 'publish_article_id');
    }

    /**
     * @return BelongsTo
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Check if article has been delivered to WordPress.
     *
     * @return bool
     */
    public function isDelivered(): bool
    {
        return in_array($this->status, ['published', 'completed']);
    }

    /**
     * Check if article is a standalone (not from a campaign).
     *
     * @return bool
     */
    public function isStandalone(): bool
    {
        return is_null($this->publish_campaign_id);
    }
}
