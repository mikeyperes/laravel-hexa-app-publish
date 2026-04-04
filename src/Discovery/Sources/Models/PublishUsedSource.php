<?php

namespace hexa_app_publish\Discovery\Sources\Models;

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
use hexa_app_publish\Models\PublishLinkList;
use hexa_app_publish\Models\PublishSitemap;
use hexa_app_publish\Models\AiActivityLog;
use hexa_app_publish\Models\AiDetectionLog;
use hexa_app_publish\Models\AiSmartEditTemplate;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PublishUsedSource extends Model
{
    protected $table = 'publish_used_sources';

    protected $fillable = [
        'publish_account_id',
        'publish_article_id',
        'url',
        'url_hash',
        'title',
        'source_api',
    ];

    /**
     * Auto-generate url_hash on save.
     */
    protected static function booted(): void
    {
        static::saving(function ($model) {
            if ($model->url) {
                $model->url_hash = hash('sha256', $model->url);
            }
        });
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
    public function article(): BelongsTo
    {
        return $this->belongsTo(PublishArticle::class, 'publish_article_id');
    }

    /**
     * Check if a URL has already been used by an account.
     *
     * @param int $accountId
     * @param string $url
     * @return bool
     */
    /**
     * Check if a URL has already been used by an account.
     *
     * @param int $accountId
     * @param string $url
     * @return bool
     */
    public static function isUsed(int $accountId, string $url): bool
    {
        return static::where('publish_account_id', $accountId)
            ->where('url_hash', hash('sha256', $url))
            ->exists();
    }
}
