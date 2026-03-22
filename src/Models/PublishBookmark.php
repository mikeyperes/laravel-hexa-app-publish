<?php

namespace hexa_app_publish\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use hexa_core\Models\User;

/**
 * PublishBookmark — saved URLs for content sourcing and research.
 *
 * @property int $id
 * @property int|null $user_id
 * @property string $url
 * @property string|null $title
 * @property string $source
 * @property string|null $tags
 * @property string|null $notes
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class PublishBookmark extends Model
{
    protected $table = 'publish_bookmarks';

    protected $fillable = [
        'user_id',
        'url',
        'title',
        'source',
        'tags',
        'notes',
    ];

    /**
     * The user who created this bookmark.
     *
     * @return BelongsTo
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
