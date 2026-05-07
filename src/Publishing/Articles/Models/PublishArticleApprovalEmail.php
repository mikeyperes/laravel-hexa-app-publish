<?php

namespace hexa_app_publish\Publishing\Articles\Models;

use hexa_core\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PublishArticleApprovalEmail extends Model
{
    protected $table = 'publish_article_approval_emails';

    protected $fillable = [
        'publish_article_id',
        'created_by',
        'smtp_account_id',
        'context',
        'status',
        'image_mode',
        'to_recipients',
        'cc_recipients',
        'from_email',
        'from_name',
        'reply_to',
        'subject',
        'body_html',
        'body_text',
        'preview_html',
        'headers',
        'diagnostics',
        'snapshot',
        'error',
        'public_token',
        'sent_at',
        'viewed_at',
        'reviewed_at',
        'review_payload',
    ];

    protected $casts = [
        'to_recipients' => 'array',
        'cc_recipients' => 'array',
        'headers' => 'array',
        'diagnostics' => 'array',
        'snapshot' => 'array',
        'review_payload' => 'array',
        'sent_at' => 'datetime',
        'viewed_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function article(): BelongsTo
    {
        return $this->belongsTo(PublishArticle::class, 'publish_article_id');
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function statusLabel(): string
    {
        if ($this->reviewed_at) {
            return 'Reviewed';
        }

        if ($this->viewed_at) {
            return 'Viewed';
        }

        if ($this->status === 'failed') {
            return 'Failed';
        }

        if ($this->sent_at) {
            return 'Sent';
        }

        return ucfirst((string) $this->status);
    }
}
