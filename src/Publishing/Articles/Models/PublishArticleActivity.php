<?php

namespace hexa_app_publish\Publishing\Articles\Models;

use hexa_app_publish\Publishing\Campaigns\Models\PublishCampaign;
use hexa_app_publish\Publishing\Pipeline\Models\PublishPipelineOperation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PublishArticleActivity extends Model
{
    protected $table = 'publish_article_activities';

    protected $fillable = [
        'publish_article_id',
        'publish_campaign_id',
        'publish_pipeline_operation_id',
        'created_by',
        'activity_group',
        'activity_type',
        'stage',
        'substage',
        'status',
        'provider',
        'model',
        'agent',
        'method',
        'attempt_no',
        'is_retry',
        'success',
        'title',
        'url',
        'message',
        'trace_id',
        'request_payload',
        'response_payload',
        'meta',
        'happened_at',
    ];

    protected $casts = [
        'publish_article_id' => 'integer',
        'publish_campaign_id' => 'integer',
        'publish_pipeline_operation_id' => 'integer',
        'created_by' => 'integer',
        'attempt_no' => 'integer',
        'is_retry' => 'boolean',
        'success' => 'boolean',
        'request_payload' => 'array',
        'response_payload' => 'array',
        'meta' => 'array',
        'happened_at' => 'datetime',
    ];

    public function article(): BelongsTo
    {
        return $this->belongsTo(PublishArticle::class, 'publish_article_id');
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(PublishCampaign::class, 'publish_campaign_id');
    }

    public function operation(): BelongsTo
    {
        return $this->belongsTo(PublishPipelineOperation::class, 'publish_pipeline_operation_id');
    }
}
