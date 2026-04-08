<?php

namespace hexa_app_publish\Publishing\Sites\Models;

use hexa_app_publish\Publishing\Accounts\Models\PublishAccount;
use hexa_app_publish\Publishing\Accounts\Models\PublishAccountUser;
use hexa_app_publish\Publishing\Articles\Models\PublishArticle;
use hexa_app_publish\Publishing\Articles\Models\PublishBookmark;
use hexa_app_publish\Publishing\Campaigns\Models\PublishCampaign;
use hexa_app_publish\Publishing\Templates\Models\PublishTemplate;
use hexa_app_publish\Publishing\Presets\Models\PublishPreset;
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

class PublishSite extends Model
{
    protected $table = 'publish_sites';

    protected $fillable = [
        'user_id',
        'publish_account_id',
        'name',
        'url',
        'connection_type',
        'hosting_account_id',
        'wordpress_install_id',
        'wp_username',
        'wp_application_password',
        'status',
        'default_author',
        'last_error',
        'last_connected_at',
        'notes',
    ];

    protected $casts = [
        'last_connected_at' => 'datetime',
    ];

    protected $hidden = [
        'wp_application_password',
    ];

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
        return $this->hasMany(PublishCampaign::class, 'publish_site_id');
    }

    /**
     * @return HasMany
     */
    public function articles(): HasMany
    {
        return $this->hasMany(PublishArticle::class, 'publish_site_id');
    }

    /**
     * Check if site is connected via WP Toolkit (cPanel).
     *
     * @return bool
     */
    public function isWpToolkit(): bool
    {
        return $this->connection_type === 'wptoolkit';
    }

    /**
     * Check if site is connected via WordPress REST API directly.
     *
     * @return bool
     */
    public function isRestApi(): bool
    {
        return $this->connection_type === 'wp_rest_api';
    }
}
