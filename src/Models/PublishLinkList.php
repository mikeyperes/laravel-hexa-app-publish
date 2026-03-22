<?php

namespace hexa_app_publish\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use hexa_core\Models\User;

class PublishLinkList extends Model
{
    protected $table = 'publish_link_lists';

    protected $fillable = [
        'publish_account_id',
        'user_id',
        'name',
        'type',
        'url',
        'anchor_text',
        'context',
        'priority',
        'times_used',
        'active',
    ];

    protected $casts = [
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

    /**
     * Increment the usage counter.
     */
    public function markUsed(): void
    {
        $this->increment('times_used');
    }
}
