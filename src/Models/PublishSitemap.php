<?php

namespace hexa_app_publish\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use hexa_core\Models\User;

class PublishSitemap extends Model
{
    protected $table = 'publish_sitemaps';

    protected $fillable = [
        'publish_account_id',
        'user_id',
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
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @deprecated Use user() instead. Kept for backward compatibility.
     * @return BelongsTo
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(PublishAccount::class, 'publish_account_id');
    }
}
