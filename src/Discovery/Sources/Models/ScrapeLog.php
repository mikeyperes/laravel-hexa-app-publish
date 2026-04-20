<?php

namespace hexa_app_publish\Discovery\Sources\Models;

use Illuminate\Database\Eloquent\Model;

class ScrapeLog extends Model
{
    protected $table = 'publish_scrape_logs';

    protected $fillable = [
        'user_id', 'url', 'effective_url', 'domain', 'method', 'http_method',
        'user_agent', 'request_headers', 'request_meta', 'timeout', 'retries',
        'http_status', 'response_reason', 'response_headers', 'response_meta',
        'response_time_ms', 'word_count', 'success', 'error_message',
        'response_body_snippet', 'fallback_used', 'attempt_log', 'fetch_info',
        'source', 'draft_id',
    ];

    protected $casts = [
        'success' => 'boolean',
        'request_headers' => 'array',
        'request_meta' => 'array',
        'response_headers' => 'array',
        'response_meta' => 'array',
        'attempt_log' => 'array',
        'fetch_info' => 'array',
    ];
}
