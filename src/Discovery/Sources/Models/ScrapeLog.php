<?php

namespace hexa_app_publish\Discovery\Sources\Models;

use Illuminate\Database\Eloquent\Model;

class ScrapeLog extends Model
{
    protected $table = 'publish_scrape_logs';

    protected $fillable = [
        'user_id', 'url', 'domain', 'method', 'user_agent',
        'timeout', 'retries', 'http_status', 'response_time_ms',
        'word_count', 'success', 'error_message', 'fallback_used',
        'source', 'draft_id',
    ];

    protected $casts = [
        'success' => 'boolean',
    ];
}
