<?php

namespace hexa_app_publish\Discovery\Links\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PublishSitemap extends Model
{
    protected $table = 'publish_sitemaps';

    protected $fillable = [
        'publish_account_id',
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
    public function account(): BelongsTo
    {
        return $this->belongsTo(PublishAccount::class, 'publish_account_id');
    }
}
