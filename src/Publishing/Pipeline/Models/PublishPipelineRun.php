<?php

namespace hexa_app_publish\Publishing\Pipeline\Models;

use hexa_app_publish\Publishing\Articles\Models\PublishArticle;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PublishPipelineRun extends Model
{
    protected $table = 'publish_pipeline_runs';

    protected $fillable = [
        'publish_article_id',
        'created_by',
        'client_trace',
        'workflow_type',
        'debug_enabled',
        'started_at',
        'last_event_at',
        'last_scope',
        'last_type',
        'last_stage',
        'last_substage',
        'total_events',
    ];

    protected $casts = [
        'debug_enabled' => 'boolean',
        'started_at' => 'datetime',
        'last_event_at' => 'datetime',
        'total_events' => 'integer',
    ];

    public function article(): BelongsTo
    {
        return $this->belongsTo(PublishArticle::class, 'publish_article_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(PublishPipelineRunEvent::class, 'publish_pipeline_run_id');
    }
}
