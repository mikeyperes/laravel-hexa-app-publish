<?php

namespace hexa_app_publish\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use hexa_core\Models\User;

class PublishTemplate extends Model
{
    protected $table = 'publish_templates';

    protected $fillable = [
        'publish_account_id',
        'user_id',
        'name',
        'article_type',
        'description',
        'ai_prompt',
        'ai_engine',
        'tone',
        'word_count_min',
        'word_count_max',
        'photos_per_article',
        'photo_sources',
        'max_links',
        'structure',
        'rules',
    ];

    protected $casts = [
        'photo_sources' => 'array',
        'structure' => 'array',
        'rules' => 'array',
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
     * @return HasMany
     */
    public function campaigns(): HasMany
    {
        return $this->hasMany(PublishCampaign::class, 'publish_template_id');
    }

    /**
     * @return HasMany
     */
    public function articles(): HasMany
    {
        return $this->hasMany(PublishArticle::class, 'publish_template_id');
    }
}
