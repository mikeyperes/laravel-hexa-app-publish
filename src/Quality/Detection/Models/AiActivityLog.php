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
use hexa_app_publish\Models\AiDetectionLog;
use hexa_app_publish\Models\AiSmartEditTemplate;

use Illuminate\Database\Eloquent\Model;

/**
 * Tracks every AI API request (Anthropic, OpenAI) with cost and content.
 *
 * @property int $id
 * @property int|null $user_id
 * @property string $provider
 * @property string $model
 * @property string $agent
 * @property int $prompt_tokens
 * @property int $completion_tokens
 * @property int $total_tokens
 * @property float $cost
 * @property string|null $ip_address
 * @property string|null $api_key_masked
 * @property string|null $system_prompt
 * @property string|null $user_message
 * @property string|null $response_content
 * @property bool $success
 * @property string|null $error_message
 */
class AiActivityLog extends Model
{
    protected $table = 'ai_activity_logs';

    protected $fillable = [
        'user_id',
        'provider',
        'model',
        'agent',
        'prompt_tokens',
        'completion_tokens',
        'total_tokens',
        'cost',
        'ip_address',
        'api_key_masked',
        'request_url',
        'system_prompt',
        'user_message',
        'response_content',
        'success',
        'error_message',
    ];

    protected $casts = [
        'success' => 'boolean',
        'cost' => 'decimal:6',
        'prompt_tokens' => 'integer',
        'completion_tokens' => 'integer',
        'total_tokens' => 'integer',
    ];

    /**
     * Log an AI API call.
     *
     * @param array $data
     * @return static
     */
    public static function logCall(array $data): static
    {
        // Calculate cost based on model pricing (per million tokens)
        $pricing = [
            'claude-opus-4-6'           => ['input' => 15.0, 'output' => 75.0],
            'claude-opus-4-20250514'    => ['input' => 15.0, 'output' => 75.0],
            'claude-sonnet-4-6'         => ['input' => 3.0,  'output' => 15.0],
            'claude-sonnet-4-20250514'  => ['input' => 3.0,  'output' => 15.0],
            'claude-haiku-4-5-20251001' => ['input' => 0.80, 'output' => 4.0],
        ];

        $model = $data['model'] ?? '';
        $rates = $pricing[$model] ?? ['input' => 0, 'output' => 0];
        $promptTokens = $data['prompt_tokens'] ?? 0;
        $completionTokens = $data['completion_tokens'] ?? 0;
        $cost = ($promptTokens * $rates['input'] / 1_000_000) + ($completionTokens * $rates['output'] / 1_000_000);

        return static::create([
            'user_id'          => $data['user_id'] ?? auth()->id(),
            'provider'         => $data['provider'] ?? 'anthropic',
            'model'            => $model,
            'agent'            => $data['agent'] ?? 'unknown',
            'prompt_tokens'    => $promptTokens,
            'completion_tokens' => $completionTokens,
            'total_tokens'     => $promptTokens + $completionTokens,
            'cost'             => $cost,
            'ip_address'       => $data['ip_address'] ?? request()->ip(),
            'api_key_masked'   => $data['api_key_masked'] ?? null,
            'request_url'      => $data['request_url'] ?? request()->header('Referer', request()->url()),
            'system_prompt'    => $data['system_prompt'] ?? null,
            'user_message'     => isset($data['user_message']) ? mb_substr($data['user_message'], 0, 10000) : null,
            'response_content' => isset($data['response_content']) ? mb_substr($data['response_content'], 0, 50000) : null,
            'success'          => $data['success'] ?? true,
            'error_message'    => $data['error_message'] ?? null,
        ]);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user()
    {
        $userModel = config('hws.user_model', \hexa_core\Models\User::class);
        return $this->belongsTo($userModel);
    }
}
