<?php

namespace hexa_app_publish\Campaigns\Models;

use Illuminate\Database\Eloquent\Model;
use hexa_core\Models\User;

/**
 * CampaignPreset — defines automated article sourcing preferences for campaigns.
 */
class CampaignPreset extends Model
{
    protected $table = 'campaign_presets';

    protected $fillable = [
        'user_id',
        'name',
        'keywords',
        'local_preference',
        'source_method',
        'genre',
        'trending_categories',
        'auto_select_sources',
        'ai_instructions',
        'is_active',
        'is_default',
        'created_by',
    ];

    protected $casts = [
        'keywords' => 'array',
        'trending_categories' => 'array',
        'auto_select_sources' => 'boolean',
        'is_active' => 'boolean',
        'is_default' => 'boolean',
    ];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
