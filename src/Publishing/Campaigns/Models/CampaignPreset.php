<?php

namespace hexa_app_publish\Publishing\Campaigns\Models;

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
use hexa_app_publish\Models\PublishSitemap;
use hexa_app_publish\Models\AiActivityLog;
use hexa_app_publish\Models\AiDetectionLog;
use hexa_app_publish\Models\AiSmartEditTemplate;

use Illuminate\Database\Eloquent\Model;
use hexa_core\Models\User;

/**
 * CampaignPreset — defines automated article sourcing preferences for campaigns.
 */
class CampaignPreset extends Model
{
    protected $table = 'campaign_presets';

    protected $fillable = [
        'user_id',
        'name',
        'keywords',
        'local_preference',
        'source_method',
        'genre',
        'trending_categories',
        'auto_select_sources',
        'ai_instructions',
        'is_active',
        'is_default',
        'created_by',
    ];

    protected $casts = [
        'keywords' => 'array',
        'trending_categories' => 'array',
        'auto_select_sources' => 'boolean',
        'is_active' => 'boolean',
        'is_default' => 'boolean',
    ];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
