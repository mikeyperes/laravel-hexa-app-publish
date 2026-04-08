<?php

namespace hexa_app_publish\Publishing\Prompts\Models;

use hexa_app_publish\Publishing\Accounts\Models\PublishAccount;
use hexa_app_publish\Publishing\Accounts\Models\PublishAccountUser;
use hexa_app_publish\Publishing\Articles\Models\PublishArticle;
use hexa_app_publish\Publishing\Articles\Models\PublishBookmark;
use hexa_app_publish\Publishing\Campaigns\Models\PublishCampaign;
use hexa_app_publish\Publishing\Sites\Models\PublishSite;
use hexa_app_publish\Publishing\Templates\Models\PublishTemplate;
use hexa_app_publish\Publishing\Presets\Models\PublishPreset;
use hexa_app_publish\Publishing\Settings\Models\PublishMasterSetting;
use hexa_app_publish\Discovery\Sources\Models\PublishUsedSource;
use hexa_app_publish\Discovery\Links\Models\PublishLinkList;
use hexa_app_publish\Discovery\Links\Models\PublishSitemap;
use hexa_app_publish\Quality\Detection\Models\AiActivityLog;
use hexa_app_publish\Quality\Detection\Models\AiDetectionLog;
use hexa_app_publish\Quality\SmartEdits\Models\AiSmartEditTemplate;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use hexa_core\Models\User;

/**
 * PublishPrompt — reusable AI prompts for content generation.
 *
 * @property int $id
 * @property int|null $user_id
 * @property string $name
 * @property string $content
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class PublishPrompt extends Model
{
    protected $table = 'publish_prompts';

    protected $fillable = [
        'user_id',
        'name',
        'content',
    ];

    /**
     * The user who created this prompt.
     *
     * @return BelongsTo
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
