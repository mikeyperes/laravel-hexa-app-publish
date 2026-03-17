<?php

namespace hexa_app_publish\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PublishSite extends Model
{
    protected $table = 'publish_sites';

    protected $fillable = [
        'publish_account_id',
        'name',
        'url',
        'connection_type',
        'hosting_account_id',
        'wordpress_install_id',
        'wp_username',
        'wp_application_password',
        'status',
        'last_error',
        'last_connected_at',
        'notes',
    ];

    protected $casts = [
        'last_connected_at' => 'datetime',
    ];

    protected $hidden = [
        'wp_application_password',
    ];

    /**
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
        return $this->hasMany(PublishCampaign::class, 'publish_site_id');
    }

    /**
     * @return HasMany
     */
    public function articles(): HasMany
    {
        return $this->hasMany(PublishArticle::class, 'publish_site_id');
    }

    /**
     * Check if site is connected via WP Toolkit (cPanel).
     *
     * @return bool
     */
    public function isWpToolkit(): bool
    {
        return $this->connection_type === 'wptoolkit';
    }

    /**
     * Check if site is connected via WordPress REST API directly.
     *
     * @return bool
     */
    public function isRestApi(): bool
    {
        return $this->connection_type === 'wp_rest_api';
    }
}
