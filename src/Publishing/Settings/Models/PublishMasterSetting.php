<?php

namespace hexa_app_publish\Publishing\Settings\Models;

use hexa_app_publish\Publishing\Accounts\Models\PublishAccount;
use hexa_app_publish\Publishing\Accounts\Models\PublishAccountUser;
use hexa_app_publish\Publishing\Articles\Models\PublishArticle;
use hexa_app_publish\Publishing\Articles\Models\PublishBookmark;
use hexa_app_publish\Publishing\Campaigns\Models\PublishCampaign;
use hexa_app_publish\Publishing\Sites\Models\PublishSite;
use hexa_app_publish\Publishing\Templates\Models\PublishTemplate;
use hexa_app_publish\Publishing\Presets\Models\PublishPreset;
use hexa_app_publish\Publishing\Prompts\Models\PublishPrompt;
use hexa_app_publish\Discovery\Sources\Models\PublishUsedSource;
use hexa_app_publish\Discovery\Links\Models\PublishLinkList;
use hexa_app_publish\Discovery\Links\Models\PublishSitemap;
use hexa_app_publish\Quality\Detection\Models\AiActivityLog;
use hexa_app_publish\Quality\Detection\Models\AiDetectionLog;
use hexa_app_publish\Quality\SmartEdits\Models\AiSmartEditTemplate;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

/**
 * PublishMasterSetting — system-wide publishing guidelines and rules.
 * Supports WordPress content guidelines and spinning/rewriting guidelines.
 *
 * @property int $id
 * @property string $name
 * @property string $type
 * @property string $content
 * @property bool $is_active
 * @property int $sort_order
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class PublishMasterSetting extends Model
{
    protected $table = 'publish_master_settings';

    protected $fillable = [
        'name',
        'type',
        'content',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active'  => 'boolean',
        'sort_order' => 'integer',
    ];

    /**
     * Scope to WordPress guidelines only.
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeWordpressGuidelines(Builder $query): Builder
    {
        return $query->where('type', 'wordpress_guidelines');
    }

    /**
     * Scope to spinning guidelines only.
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeSpinningGuidelines(Builder $query): Builder
    {
        return $query->where('type', 'spinning_guidelines');
    }
}
