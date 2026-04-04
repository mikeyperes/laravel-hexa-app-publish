<?php

namespace hexa_app_publish\Publishing\Accounts\Models;

use hexa_app_publish\Models\PublishAccountUser;
use hexa_app_publish\Models\PublishArticle;
use hexa_app_publish\Models\PublishBookmark;
use hexa_app_publish\Models\PublishCampaign;
use hexa_app_publish\Models\PublishSite;
use hexa_app_publish\Models\PublishTemplate;
use hexa_app_publish\Models\PublishPreset;
use hexa_app_publish\Models\PublishPrompt;
use hexa_app_publish\Models\PublishMasterSetting;
use hexa_app_publish\Models\PublishUsedSource;
use hexa_app_publish\Models\PublishLinkList;
use hexa_app_publish\Models\PublishSitemap;
use hexa_app_publish\Models\AiActivityLog;
use hexa_app_publish\Models\AiDetectionLog;
use hexa_app_publish\Models\AiSmartEditTemplate;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use hexa_core\Models\User;

class PublishAccount extends Model
{
    protected $table = 'publish_accounts';

    protected $fillable = [
        'name',
        'account_id',
        'email',
        'status',
        'owner_user_id',
        'stripe_customer_id',
        'stripe_subscription_id',
        'plan',
        'notes',
    ];

    /**
     * Generate a unique account ID: PUB-YYYYMMDD-NNN.
     *
     * @return string
     */
    public static function generateAccountId(): string
    {
        $date = now()->format('Ymd');
        $prefix = "PUB-{$date}-";
        $latest = static::where('account_id', 'like', "{$prefix}%")
            ->orderByDesc('account_id')
            ->value('account_id');

        $next = $latest ? ((int) substr($latest, -3)) + 1 : 1;

        return $prefix . str_pad($next, 3, '0', STR_PAD_LEFT);
    }

    /**
     * @return BelongsTo
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    /**
     * @return HasMany
     */
    public function users(): HasMany
    {
        return $this->hasMany(PublishAccountUser::class, 'publish_account_id');
    }

    /**
     * @return HasMany
     */
    public function sites(): HasMany
    {
        return $this->hasMany(PublishSite::class, 'publish_account_id');
    }

    /**
     * @return HasMany
     */
    public function templates(): HasMany
    {
        return $this->hasMany(PublishTemplate::class, 'publish_account_id');
    }

    /**
     * @return HasMany
     */
    public function campaigns(): HasMany
    {
        return $this->hasMany(PublishCampaign::class, 'publish_account_id');
    }

    /**
     * @return HasMany
     */
    public function articles(): HasMany
    {
        return $this->hasMany(PublishArticle::class, 'publish_account_id');
    }

    /**
     * @return HasMany
     */
    public function usedSources(): HasMany
    {
        return $this->hasMany(PublishUsedSource::class, 'publish_account_id');
    }
}
