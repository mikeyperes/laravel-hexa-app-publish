<?php

namespace hexa_app_publish\Publishing\Articles\Models;

use hexa_app_publish\Models\PublishAccount;
use hexa_app_publish\Models\PublishAccountUser;
use hexa_app_publish\Models\PublishArticle;
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
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use hexa_core\Models\User;

/**
 * PublishBookmark — saved URLs for content sourcing and research.
 *
 * @property int $id
 * @property int|null $user_id
 * @property string $url
 * @property string|null $title
 * @property string $source
 * @property string|null $tags
 * @property string|null $notes
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class PublishBookmark extends Model
{
    protected $table = 'publish_bookmarks';

    protected $fillable = [
        'user_id',
        'url',
        'title',
        'source',
        'tags',
        'notes',
    ];

    /**
     * The user who created this bookmark.
     *
     * @return BelongsTo
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
