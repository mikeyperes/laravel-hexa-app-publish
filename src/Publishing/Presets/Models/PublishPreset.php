<?php

namespace hexa_app_publish\Publishing\Presets\Models;

use hexa_app_publish\Models\PublishAccount;
use hexa_app_publish\Models\PublishAccountUser;
use hexa_app_publish\Models\PublishArticle;
use hexa_app_publish\Models\PublishBookmark;
use hexa_app_publish\Models\PublishCampaign;
use hexa_app_publish\Models\PublishSite;
use hexa_app_publish\Models\PublishTemplate;
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
use hexa_core\Models\User;

/**
 * PublishPreset — saved publishing configuration presets.
 * Combines default settings for article format, tone, images,
 * and publishing behavior into reusable presets.
 *
 * @property int $id
 * @property int|null $user_id
 * @property string $name
 * @property bool $is_default
 * @property int|null $default_site_id
 * @property string $follow_links
 * @property string|null $article_format
 * @property string|null $tone
 * @property string|null $image_preference
 * @property string $default_publish_action
 * @property int $default_category_count
 * @property int $default_tag_count
 * @property string|null $image_layout
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class PublishPreset extends Model
{
    protected $table = 'publish_presets';

    protected $fillable = [
        'user_id',
        'name',
        'status',
        'is_default',
        'default_site_id',
        'follow_links',
        'article_format',
        'tone',
        'image_preference',
        'default_publish_action',
        'default_category_count',
        'default_tag_count',
        'image_layout',
    ];

    protected $casts = [
        'is_default'             => 'boolean',
        'default_site_id'        => 'integer',
        'default_category_count' => 'integer',
        'default_tag_count'      => 'integer',
    ];

    /**
     * The user who created this preset.
     *
     * @return BelongsTo
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
