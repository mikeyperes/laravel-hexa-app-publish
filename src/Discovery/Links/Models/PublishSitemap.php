<?php

namespace hexa_app_publish\Discovery\Links\Models;

use hexa_app_publish\Models\PublishAccount;
use hexa_app_publish\Models\PublishAccountUser;
use hexa_app_publish\Models\PublishArticle;
use hexa_app_publish\Models\PublishBookmark;
use hexa_app_publish\Models\PublishCampaign;
use hexa_app_publish\Models\PublishSite;
use hexa_app_publish\Models\PublishTemplate;
use hexa_app_publish\Models\PublishPreset;
use hexa_app_publish\Models\PublishPrompt;
use hexa_app_publish\Models\PublishMasterSetting;
use hexa_app_publish\Models\PublishUsedSource;
use hexa_app_publish\Models\PublishLinkList;
use hexa_app_publish\Models\AiActivityLog;
use hexa_app_publish\Models\AiDetectionLog;
use hexa_app_publish\Models\AiSmartEditTemplate;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PublishSitemap extends Model
{
    protected $table = 'publish_sitemaps';

    protected $fillable = [
        'publish_account_id',
        'name',
        'sitemap_url',
        'parsed_urls',
        'url_count',
        'last_parsed_at',
        'active',
    ];

    protected $casts = [
        'parsed_urls' => 'array',
        'last_parsed_at' => 'datetime',
        'active' => 'boolean',
    ];

    /**
     * @return BelongsTo
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(PublishAccount::class, 'publish_account_id');
    }
}
