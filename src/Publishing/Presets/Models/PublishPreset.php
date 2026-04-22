<?php

namespace hexa_app_publish\Publishing\Presets\Models;

use hexa_app_publish\Publishing\Accounts\Models\PublishAccount;
use hexa_app_publish\Publishing\Accounts\Models\PublishAccountUser;
use hexa_app_publish\Publishing\Articles\Models\PublishArticle;
use hexa_app_publish\Publishing\Articles\Models\PublishBookmark;
use hexa_app_publish\Publishing\Campaigns\Models\PublishCampaign;
use hexa_app_publish\Publishing\Sites\Models\PublishSite;
use hexa_app_publish\Publishing\Templates\Models\PublishTemplate;
use hexa_app_publish\Publishing\Prompts\Models\PublishPrompt;
use hexa_app_publish\Publishing\Settings\Models\PublishMasterSetting;
use hexa_app_publish\Discovery\Sources\Models\PublishUsedSource;
use hexa_app_publish\Discovery\Links\Models\PublishLinkList;
use hexa_app_publish\Discovery\Links\Models\PublishSitemap;
use hexa_app_publish\Quality\Detection\Models\AiActivityLog;
use hexa_app_publish\Quality\Detection\Models\AiDetectionLog;
use hexa_app_publish\Quality\SmartEdits\Models\AiSmartEditTemplate;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use hexa_app_publish\Publishing\Presets\Forms\WordPressPresetForm;
use hexa_core\Forms\Runtime\FormRuntimeService;
use hexa_core\Models\User;

/**
 * PublishPreset — saved publishing configuration presets.
 * Combines default settings for article format, tone, images,
 * and publishing behavior into reusable presets.
 *
 * @property int $id
 * @property int|null $user_id
 * @property string $name
 * @property bool $is_default
 * @property int|null $default_site_id
 * @property string $follow_links
 * @property string|null $article_format
 * @property string|null $tone
 * @property string|null $image_preference
 * @property string $default_publish_action
 * @property int $default_category_count
 * @property int $default_tag_count
 * @property string|null $image_layout
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class PublishPreset extends Model
{
    protected $table = 'publish_presets';

    protected $fillable = [
        'user_id',
        'name',
        'status',
        'is_default',
        'default_site_id',
        'follow_links',
        'article_format',
        'tone',
        'image_preference',
        'default_publish_action',
        'default_category_count',
        'default_tag_count',
        'image_layout',
        'searching_agent',
        'scraping_agent',
        'spinning_agent',
    ];

    protected $casts = [
        'is_default'             => 'boolean',
        'default_site_id'        => 'integer',
        'default_category_count' => 'integer',
        'default_tag_count'      => 'integer',
    ];

    /**
     * Return field schema for pipeline-style preset rendering.
     * Delegates to WordPressPresetForm — single source of truth.
     *
     * @param string $context
     * @return array
     */
    public static function getFieldSchema(string $context = 'pipeline'): array
    {
        return app(FormRuntimeService::class)->schema(WordPressPresetForm::FORM_KEY, $context, [
            'context' => $context,
            'mode' => $context,
        ]);
    }

    /**
     * The user who created this preset.
     *
     * @return BelongsTo
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
