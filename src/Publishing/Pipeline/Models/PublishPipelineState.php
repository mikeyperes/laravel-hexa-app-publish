<?php

namespace hexa_app_publish\Publishing\Pipeline\Models;

use hexa_app_publish\Publishing\Articles\Models\PublishArticle;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PublishPipelineState extends Model
{
    protected $table = 'publish_pipeline_states';

    protected $fillable = [
        'publish_article_id',
        'workflow_type',
        'state_version',
        'payload',
    ];

    protected $casts = [
        'payload' => 'array',
        'state_version' => 'integer',
    ];

    public function article(): BelongsTo
    {
        return $this->belongsTo(PublishArticle::class, 'publish_article_id');
    }
}
