<?php

namespace hexa_app_publish\Publishing\Articles\Models;

use Illuminate\Database\Eloquent\Model;
use hexa_core\Models\User;

/**
 * PublishFailedSource — tracks URLs that failed article extraction.
 *
 * Displayed in the Bookmarked Articles > Failed tab.
 */
class PublishFailedSource extends Model
{
    protected $table = 'publish_failed_sources';

    protected $fillable = [
        'user_id',
        'url',
        'title',
        'error_message',
        'source_api',
    ];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
