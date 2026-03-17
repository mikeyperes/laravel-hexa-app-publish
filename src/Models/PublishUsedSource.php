<?php

namespace hexa_app_publish\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PublishUsedSource extends Model
{
    protected $table = 'publish_used_sources';

    protected $fillable = [
        'publish_account_id',
        'publish_article_id',
        'url',
        'title',
        'source_api',
    ];

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
    public static function isUsed(int $accountId, string $url): bool
    {
        return static::where('publish_account_id', $accountId)
            ->where('url', $url)
            ->exists();
    }
}
