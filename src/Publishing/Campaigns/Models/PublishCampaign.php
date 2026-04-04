<?php

namespace hexa_app_publish\Publishing\Campaigns\Models;

use hexa_app_publish\Models\PublishAccount;
use hexa_app_publish\Models\PublishAccountUser;
use hexa_app_publish\Models\PublishArticle;
use hexa_app_publish\Models\PublishBookmark;
use hexa_app_publish\Models\PublishSite;
use hexa_app_publish\Models\PublishTemplate;
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
use hexa_core\Models\User;

class PublishCampaign extends Model
{
    protected $table = 'publish_campaigns';

    protected $fillable = [
        'user_id',
        'publish_account_id',
        'publish_site_id',
        'publish_template_id',
        'campaign_preset_id',
        'preset_id',
        'name',
        'campaign_id',
        'description',
        'topic',
        'keywords',
        'article_type',
        'ai_engine',
        'delivery_mode',
        'auto_publish',
        'author',
        'post_status',
        'articles_per_interval',
        'interval_unit',
        'timezone',
        'run_at_time',
        'drip_interval_minutes',
        'article_sources',
        'photo_sources',
        'link_list',
        'sitemap_urls',
        'max_links_per_article',
        'status',
        'last_run_at',
        'next_run_at',
        'created_by',
        'notes',
    ];

    protected $casts = [
        'keywords' => 'array',
        'article_sources' => 'array',
        'photo_sources' => 'array',
        'link_list' => 'array',
        'sitemap_urls' => 'array',
        'auto_publish' => 'boolean',
        'last_run_at' => 'datetime',
        'next_run_at' => 'datetime',
    ];

    /**
     * Generate a unique campaign ID: CMP-YYYYMMDD-NNN.
     *
     * @return string
     */
    public static function generateCampaignId(): string
    {
        $date = now()->format('Ymd');
        $prefix = "CMP-{$date}-";
        $latest = static::where('campaign_id', 'like', "{$prefix}%")
            ->orderByDesc('campaign_id')
            ->value('campaign_id');

        $next = $latest ? ((int) substr($latest, -3)) + 1 : 1;

        return $prefix . str_pad($next, 3, '0', STR_PAD_LEFT);
    }

    /**
     * @return BelongsTo
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo
     */
    public function campaignPreset(): BelongsTo
    {
        return $this->belongsTo(\hexa_app_publish\Publishing\Campaigns\Models\CampaignPreset::class);
    }

    /**
     * @return BelongsTo
     */
    public function wpPreset(): BelongsTo
    {
        return $this->belongsTo(PublishPreset::class, 'preset_id');
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
    public function template(): BelongsTo
    {
        return $this->belongsTo(PublishTemplate::class, 'publish_template_id');
    }

    /**
     * @return HasMany
     */
    public function articles(): HasMany
    {
        return $this->hasMany(PublishArticle::class, 'publish_campaign_id');
    }

    /**
     * @return BelongsTo
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Check if the campaign is actively running.
     *
     * @return bool
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Check if the campaign is due to run.
     *
     * @return bool
     */
    public function isDue(): bool
    {
        if (!$this->isActive()) {
            return false;
        }

        if (!$this->next_run_at) {
            return true;
        }

        return now()->gte($this->next_run_at);
    }
}
