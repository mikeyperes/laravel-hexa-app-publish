<?php

namespace hexa_app_publish\Quality\Detection\Models;

use hexa_app_publish\Models\PublishAccount;
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
use hexa_app_publish\Models\AiSmartEditTemplate;

use Illuminate\Database\Eloquent\Model;
use hexa_core\Models\User;

/**
 * AiDetectionLog — tracks every AI detection API call.
 */
class AiDetectionLog extends Model
{
    protected $table = 'ai_detection_logs';

    protected $fillable = [
        'detector',
        'user_id',
        'article_id',
        'text_length',
        'text_sent',
        'raw_response',
        'score',
        'cost',
        'debug_mode',
        'success',
        'error_message',
    ];

    protected $casts = [
        'debug_mode' => 'boolean',
        'success' => 'boolean',
    ];

    /**
     * Log a detection call.
     *
     * @param array $data
     * @return static
     */
    public static function logCall(array $data): static
    {
        return static::create([
            'detector' => $data['detector'],
            'user_id' => auth()->id(),
            'article_id' => $data['article_id'] ?? null,
            'text_length' => strlen($data['text'] ?? ''),
            'text_sent' => \Illuminate\Support\Str::limit($data['text'] ?? '', 2000),
            'raw_response' => is_string($data['response'] ?? null) ? $data['response'] : json_encode($data['response'] ?? null),
            'score' => $data['score'] ?? null,
            'cost' => $data['cost'] ?? null,
            'debug_mode' => $data['debug_mode'] ?? false,
            'success' => $data['success'] ?? true,
            'error_message' => \Illuminate\Support\Str::limit($data['error'] ?? '', 250),
        ]);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
