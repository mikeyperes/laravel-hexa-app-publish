<?php

namespace hexa_app_publish\Quality\SmartEdits\Models;

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

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $name
 * @property string $prompt
 * @property string $category
 * @property int $sort_order
 * @property bool $is_active
 */
class AiSmartEditTemplate extends Model
{
    protected $table = 'ai_smart_edit_templates';

    protected $fillable = [
        'name',
        'prompt',
        'category',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];
}
