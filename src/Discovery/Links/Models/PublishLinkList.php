<?php

namespace hexa_app_publish\Discovery\Links\Models;

use hexa_app_publish\Publishing\Accounts\Models\PublishAccount;
use hexa_app_publish\Publishing\Accounts\Models\PublishAccountUser;
use hexa_app_publish\Publishing\Articles\Models\PublishArticle;
use hexa_app_publish\Publishing\Articles\Models\PublishBookmark;
use hexa_app_publish\Publishing\Campaigns\Models\PublishCampaign;
use hexa_app_publish\Publishing\Sites\Models\PublishSite;
use hexa_app_publish\Publishing\Templates\Models\PublishTemplate;
use hexa_app_publish\Publishing\Presets\Models\PublishPreset;
use hexa_app_publish\Publishing\Prompts\Models\PublishPrompt;
use hexa_app_publish\Publishing\Settings\Models\PublishMasterSetting;
use hexa_app_publish\Discovery\Sources\Models\PublishUsedSource;
use hexa_app_publish\Discovery\Links\Models\PublishSitemap;
use hexa_app_publish\Quality\Detection\Models\AiActivityLog;
use hexa_app_publish\Quality\Detection\Models\AiDetectionLog;
use hexa_app_publish\Quality\SmartEdits\Models\AiSmartEditTemplate;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PublishLinkList extends Model
{
    protected $table = 'publish_link_lists';

    protected $fillable = [
        'publish_account_id',
        'name',
        'type',
        'url',
        'anchor_text',
        'context',
        'priority',
        'times_used',
        'active',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

    /**
     * @return BelongsTo
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(PublishAccount::class, 'publish_account_id');
    }

    /**
     * Increment the usage counter.
     */
    public function markUsed(): void
    {
        $this->increment('times_used');
    }
}
