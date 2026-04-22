<?php

namespace hexa_app_publish\Publishing\Campaigns\Models;

use hexa_app_publish\Publishing\Accounts\Models\PublishAccount;
use hexa_app_publish\Publishing\Accounts\Models\PublishAccountUser;
use hexa_app_publish\Publishing\Articles\Models\PublishArticle;
use hexa_app_publish\Publishing\Articles\Models\PublishBookmark;
use hexa_app_publish\Publishing\Campaigns\Models\PublishCampaign;
use hexa_app_publish\Publishing\Sites\Models\PublishSite;
use hexa_app_publish\Publishing\Templates\Models\PublishTemplate;
use hexa_app_publish\Publishing\Presets\Models\PublishPreset;
use hexa_app_publish\Publishing\Prompts\Models\PublishPrompt;
use hexa_app_publish\Publishing\Settings\Models\PublishMasterSetting;
use hexa_app_publish\Discovery\Sources\Models\PublishUsedSource;
use hexa_app_publish\Discovery\Links\Models\PublishLinkList;
use hexa_app_publish\Discovery\Links\Models\PublishSitemap;
use hexa_app_publish\Quality\Detection\Models\AiActivityLog;
use hexa_app_publish\Quality\Detection\Models\AiDetectionLog;
use hexa_app_publish\Quality\SmartEdits\Models\AiSmartEditTemplate;

use Illuminate\Database\Eloquent\Model;
use hexa_core\Forms\Runtime\FormRuntimeService;
use hexa_core\Models\User;
use hexa_app_publish\Publishing\Campaigns\Forms\CampaignPresetForm;

/**
 * CampaignPreset — defines automated article sourcing preferences for campaigns.
 */
class CampaignPreset extends Model
{
    protected $table = 'campaign_presets';

    protected $fillable = [
        'user_id',
        'name',
        'search_queries',
        'campaign_instructions',
        'posts_per_run',
        'frequency',
        'run_at_time',
        'drip_minutes',
        'final_article_method',
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
        'search_queries' => 'array',
        'keywords' => 'array',
        'trending_categories' => 'array',
        'posts_per_run' => 'integer',
        'drip_minutes' => 'integer',
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

    /**
     * Return field schema for pipeline-style preset rendering.
     */
    public static function getFieldSchema(string $context = 'pipeline'): array
    {
        return app(FormRuntimeService::class)->schema(CampaignPresetForm::FORM_KEY, $context, [
            'context' => $context,
            'mode' => $context,
        ]);
    }
}
