<?php

namespace hexa_app_publish\Publishing\Pipeline\Models;

use hexa_app_publish\Publishing\Articles\Models\PublishArticle;
use hexa_app_publish\Publishing\Sites\Models\PublishSite;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PublishPipelineOperation extends Model
{
    public const TYPE_PREPARE = 'prepare';
    public const TYPE_PUBLISH = 'publish';

    public const STATUS_QUEUED = 'queued';
    public const STATUS_RUNNING = 'running';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';

    protected $table = 'publish_pipeline_operations';

    protected $fillable = [
        'publish_article_id',
        'publish_site_id',
        'created_by',
        'operation_type',
        'status',
        'workflow_type',
        'transport',
        'queue_connection',
        'queue_name',
        'client_trace',
        'trace_id',
        'debug_enabled',
        'event_sequence',
        'total_events',
        'last_stage',
        'last_substage',
        'last_message',
        'error_message',
        'request_summary',
        'result_payload',
        'started_at',
        'completed_at',
        'last_event_at',
    ];

    protected $casts = [
        'debug_enabled' => 'boolean',
        'event_sequence' => 'integer',
        'total_events' => 'integer',
        'request_summary' => 'array',
        'result_payload' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'last_event_at' => 'datetime',
    ];

    public function article(): BelongsTo
    {
        return $this->belongsTo(PublishArticle::class, 'publish_article_id');
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(PublishSite::class, 'publish_site_id');
    }

    public function isActive(): bool
    {
        return in_array($this->status, [self::STATUS_QUEUED, self::STATUS_RUNNING], true);
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, [self::STATUS_COMPLETED, self::STATUS_FAILED], true);
    }

    public function isCancelled(): bool
    {
        return (bool) (($this->result_payload['cancelled'] ?? false) === true);
    }

    public function isStale(int $minutes = 10): bool
    {
        if (!$this->isActive()) {
            return false;
        }

        $reference = $this->last_event_at ?: $this->started_at ?: $this->created_at;
        if (!$reference) {
            return false;
        }

        return $reference->lt(now()->subMinutes($minutes));
    }
}
