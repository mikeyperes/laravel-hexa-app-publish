<?php

namespace hexa_app_publish\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use hexa_core\Models\User;

/**
 * PublishPrompt — reusable AI prompts for content generation.
 *
 * @property int $id
 * @property int|null $user_id
 * @property string $name
 * @property string $content
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class PublishPrompt extends Model
{
    protected $table = 'publish_prompts';

    protected $fillable = [
        'user_id',
        'name',
        'content',
    ];

    /**
     * The user who created this prompt.
     *
     * @return BelongsTo
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
