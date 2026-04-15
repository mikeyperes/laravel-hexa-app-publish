<?php

namespace hexa_app_publish\Publishing\Pipeline\Models;

use hexa_app_publish\Publishing\Articles\Models\PublishArticle;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PublishPipelineRunEvent extends Model
{
    protected $table = 'publish_pipeline_run_events';

    protected $fillable = [
        'publish_pipeline_run_id',
        'publish_article_id',
        'client_event_id',
        'run_trace',
        'captured_at',
        'client_sequence',
        'scope',
        'type',
        'message',
        'stage',
        'substage',
        'trace_id',
        'sequence_no',
        'method',
        'status_code',
        'duration_ms',
        'step',
        'url',
        'details',
        'payload_preview',
        'response_preview',
        'debug_only',
        'meta',
    ];

    protected $casts = [
        'captured_at' => 'datetime',
        'client_sequence' => 'integer',
        'sequence_no' => 'integer',
        'status_code' => 'integer',
        'duration_ms' => 'integer',
        'step' => 'integer',
        'debug_only' => 'boolean',
        'meta' => 'array',
    ];

    public function run(): BelongsTo
    {
        return $this->belongsTo(PublishPipelineRun::class, 'publish_pipeline_run_id');
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(PublishArticle::class, 'publish_article_id');
    }
}
