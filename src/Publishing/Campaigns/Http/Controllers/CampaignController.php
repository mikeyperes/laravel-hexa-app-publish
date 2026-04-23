<?php

namespace hexa_app_publish\Publishing\Campaigns\Http\Controllers;

use hexa_core\Http\Controllers\Controller;
use hexa_core\Services\GenericService;
use hexa_app_publish\Publishing\Accounts\Models\PublishAccount;
use hexa_app_publish\Publishing\Campaigns\Models\PublishCampaign;
use hexa_app_publish\Publishing\Campaigns\Services\CampaignEligibilityService;
use hexa_app_publish\Publishing\Campaigns\Services\CampaignChecklistService;
use hexa_app_publish\Publishing\Campaigns\Services\CampaignModeResolver;
use hexa_app_publish\Publishing\Campaigns\Services\CampaignRunOperationService;
use hexa_app_publish\Publishing\Campaigns\Services\CampaignScheduleService;
use hexa_app_publish\Publishing\Campaigns\Services\CampaignSettingsResolver;
use hexa_app_publish\Publishing\Pipeline\Models\PublishPipelineOperation;
use hexa_app_publish\Publishing\Pipeline\Services\PipelineOperationService;
use hexa_app_publish\Publishing\Sites\Models\PublishSite;
use hexa_app_publish\Publishing\Templates\Models\PublishTemplate;
use hexa_app_publish\Services\PublishService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CampaignController extends Controller
{
    protected GenericService $generic;
    protected PublishService $publishService;
    protected CampaignEligibilityService $eligibility;
    protected CampaignModeResolver $modeResolver;
    protected CampaignScheduleService $scheduleService;
    protected CampaignSettingsResolver $settingsResolver;
    protected CampaignChecklistService $checklistService;
    protected CampaignRunOperationService $runOperationService;
    protected PipelineOperationService $pipelineOperationService;

    /**
     * @param GenericService $generic
     * @param PublishService $publishService
     */
    public function __construct(
        GenericService $generic,
        PublishService $publishService,
        CampaignEligibilityService $eligibility,
        CampaignModeResolver $modeResolver,
        CampaignScheduleService $scheduleService,
        CampaignSettingsResolver $settingsResolver,
        CampaignChecklistService $checklistService,
        CampaignRunOperationService $runOperationService,
        PipelineOperationService $pipelineOperationService
    )
    {
        $this->generic = $generic;
        $this->publishService = $publishService;
        $this->eligibility = $eligibility;
        $this->modeResolver = $modeResolver;
        $this->scheduleService = $scheduleService;
        $this->settingsResolver = $settingsResolver;
        $this->checklistService = $checklistService;
        $this->runOperationService = $runOperationService;
        $this->pipelineOperationService = $pipelineOperationService;
    }

    /**
     * List all campaigns with filters.
     *
     * @param Request $request
     * @return View
     */
    public function index(Request $request): View
    {
        $query = PublishCampaign::with(['account', 'site', 'template', 'articles']);

        if ($request->filled('account_id')) {
            $query->where('publish_account_id', $request->input('account_id'));
        }

        if ($request->filled('site_id')) {
            $query->where('publish_site_id', $request->input('site_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('campaign_id', 'like', "%{$search}%")
                  ->orWhere('topic', 'like', "%{$search}%");
            });
        }

        $campaigns = $query->orderByDesc('created_at')->get();
        $accounts = PublishAccount::orderBy('name')->get();

        return view('app-publish::publishing.campaigns.index', [
            'campaigns' => $campaigns,
            'accounts' => $accounts,
        ]);
    }

    /**
     * Show create campaign form.
     *
     * @param Request $request
     * @return View
     */
    public function create(Request $request): View|\Illuminate\Http\RedirectResponse
    {
        if ($request->filled('id')) {
            PublishCampaign::findOrFail($request->integer('id'));

            return redirect()->route('campaigns.show', ['id' => $request->integer('id')]);
        }

        $campaign = PublishCampaign::where('created_by', auth()->id())
            ->where('status', 'draft')
            ->where('name', 'Untitled Campaign')
            ->orderByDesc('id')
            ->first();

        if (!$campaign) {
            $site = PublishSite::where('status', 'connected')->first();
            $campaign = PublishCampaign::create([
                'name' => 'Untitled Campaign',
                'campaign_id' => PublishCampaign::generateCampaignId(),
                'publish_account_id' => $site ? ($site->publish_account_id ?: null) : null,
                'publish_site_id' => $site ? $site->id : null,
                'status' => 'draft',
                'delivery_mode' => 'draft-wordpress',
                'articles_per_interval' => 1,
                'interval_unit' => 'daily',
                'timezone' => auth()->user()?->timezone ?: config('hws.timezone', 'America/New_York'),
                'run_at_time' => '09:00',
                'drip_interval_minutes' => 60,
                'created_by' => auth()->id(),
            ]);
        }

        $sites = PublishSite::where('status', 'connected')->orderBy('name')->get();
        $campaignPresets = \hexa_app_publish\Publishing\Campaigns\Models\CampaignPreset::orderBy('name')->get();
        $aiTemplates = PublishTemplate::orderBy('name')->get();

        return view('app-publish::publishing.campaigns.create', [
            'sites' => $sites,
            'campaignPresets' => $campaignPresets,
            'aiTemplates' => $aiTemplates,
            'editCampaign' => $campaign,
            'deliveryModes' => $this->eligibility->supportedDeliveryModes(),
            'articleTypes' => $this->eligibility->supportedArticleTypes(),
        ]);
    }

    /**
     * Store a new campaign.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        $this->normalizeNullableFields($request);

        $validated = $request->validate([
            'user_id' => 'nullable|integer|exists:users,id',
            'publish_site_id' => 'required|exists:publish_sites,id',
            'publish_template_id' => 'nullable|exists:publish_templates,id',
            'campaign_preset_id' => 'nullable|integer',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'ai_instructions' => 'nullable|string',
            'topic' => 'nullable|string|max:1000',
            'keywords' => 'nullable|array',
            'article_type' => ['nullable', 'string', Rule::in($this->eligibility->supportedArticleTypes())],
            'ai_engine' => 'nullable|string|max:100',
            'author' => 'nullable|string|max:255',
            'post_status' => 'nullable|in:publish,draft,pending',
            'delivery_mode' => ['nullable', 'string', Rule::in($this->eligibility->supportedDeliveryModes())],
            'articles_per_interval' => 'required|integer|min:1|max:50',
            'interval_unit' => 'required|in:hourly,daily,weekly,monthly',
            'run_at_time' => 'nullable|string|max:10',
            'drip_interval_minutes' => 'nullable|integer|min:1|max:1440',
        ]);

        $validated['campaign_id'] = PublishCampaign::generateCampaignId();
        $validated['status'] = 'draft';
        $validated['created_by'] = auth()->id();
        $validated['auto_publish'] = ($validated['delivery_mode'] ?? 'draft-local') === 'auto-publish';
        $validated['delivery_mode'] = $this->modeResolver->normalizeDeliveryMode($validated['delivery_mode'] ?? 'draft-local');
        $validated['drip_interval_minutes'] = $validated['drip_interval_minutes'] ?? 60;
        $validated['timezone'] = $this->resolveCampaignTimezone($validated['user_id'] ?? null, null);

        // Get publish_account_id from site (nullable FK — never set to 0)
        $site = PublishSite::find($validated['publish_site_id']);
        $validated['publish_account_id'] = $site ? ($site->publish_account_id ?: null) : null;

        $campaign = PublishCampaign::create($validated);

        hexaLog('campaigns', 'campaign_created', "Campaign created: {$campaign->name} ({$campaign->campaign_id})");

        return response()->json([
            'success' => true,
            'message' => "Campaign '{$campaign->name}' created.",
            'campaign' => $campaign,
        ]);
    }

    /**
     * Show a single campaign with its articles.
     *
     * @param int $id
     * @return View
     */
    public function show(int $id): View
    {
        $campaign = PublishCampaign::with([
            'account',
            'site',
            'template',
            'creator',
            'user',
            'campaignPreset',
            'articles' => function ($q) {
                $q->orderByDesc('created_at');
            },
        ])->findOrFail($id);

        $runLogs = \DB::table('activity_logs')
            ->where('category', 'campaigns')
            ->where('context', 'like', '%campaign_id":' . $campaign->id . '%')
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        try {
            $resolvedSettings = $this->settingsResolver->resolve($campaign);
        } catch (\Throwable $e) {
            $resolvedSettings = [
                'article_type' => $campaign->article_type,
                'delivery_mode' => $this->modeResolver->normalizeDeliveryMode($campaign->delivery_mode),
                'search_terms' => (array) ($campaign->keywords ?? $campaign->campaignPreset?->search_queries ?? $campaign->campaignPreset?->keywords ?? []),
                'error' => $e->getMessage(),
            ];
        }

        $sites = PublishSite::where('status', 'connected')->orderBy('name')->get();
        $campaignPresets = \hexa_app_publish\Publishing\Campaigns\Models\CampaignPreset::with('user')->orderBy('name')->get();
        $aiTemplates = PublishTemplate::with('account')->orderBy('name')->get();
        $cronJob = \DB::table('cron_jobs')->where('command', 'publish:run-campaigns')->first();
        $lastArticle = $campaign->articles->first();

        /** @var \hexa_core\Forms\Services\FormRegistryService $formRegistry */
        $formRegistry = app(\hexa_core\Forms\Services\FormRegistryService::class);

        $campaignPresetForm = $formRegistry->resolve(\hexa_app_publish\Publishing\Campaigns\Forms\CampaignPresetForm::FORM_KEY, [
            'mode' => 'edit',
            'context' => 'edit',
            'record' => $campaign->campaignPreset,
        ]);

        $articlePresetForm = $formRegistry->resolve(\hexa_app_publish\Publishing\Templates\Forms\ArticlePresetForm::FORM_KEY, [
            'mode' => 'edit',
            'context' => 'edit',
            'record' => $campaign->template,
        ]);

        $campaignPresetItems = $campaignPresets->map(function ($preset) {
            return [
                'id' => $preset->id,
                'name' => $preset->name,
                'form_values' => \hexa_app_publish\Publishing\Campaigns\Forms\CampaignPresetForm::values($preset),
                'record' => $preset->toArray(),
            ];
        })->values();

        $aiTemplateItems = $aiTemplates->map(function ($template) {
            return [
                'id' => $template->id,
                'name' => $template->name,
                'form_values' => \hexa_app_publish\Publishing\Templates\Forms\ArticlePresetForm::values($template),
                'record' => $template->toArray(),
            ];
        })->values();

        $campaignForm = [
            'user_id' => $campaign->user_id,
            'campaign_preset_id' => $campaign->campaign_preset_id ? (string) $campaign->campaign_preset_id : '',
            'publish_template_id' => $campaign->publish_template_id ? (string) $campaign->publish_template_id : '',
            'publish_site_id' => $campaign->publish_site_id ? (string) $campaign->publish_site_id : '',
            'name' => $campaign->name ?? '',
            'description' => $campaign->description ?? '',
            'topic' => $campaign->topic ?? '',
            'ai_instructions' => $campaign->ai_instructions ?? $campaign->notes ?? '',
            'ai_engine' => '',
            'keywords' => array_values((array) ($campaign->keywords ?? [])),
            'delivery_mode' => $this->modeResolver->normalizeDeliveryMode($campaign->delivery_mode ?? 'draft-wordpress'),
            'article_type' => $campaign->article_type ?? '',
            'author' => $campaign->author ?? '',
            'articles_per_interval' => $campaign->articles_per_interval ?? 1,
            'interval_unit' => $campaign->interval_unit ?? 'daily',
            'run_at_time' => $campaign->run_at_time ?? '09:00',
            'drip_interval_minutes' => $campaign->drip_interval_minutes ?? 60,
        ];

        return view('app-publish::publishing.campaigns.show', [
            'campaign' => $campaign,
            'runLogs' => $runLogs,
            'resolvedSettings' => $resolvedSettings,
            'sites' => $sites,
            'campaignPresets' => $campaignPresets,
            'campaignPresetItems' => $campaignPresetItems,
            'aiTemplates' => $aiTemplates,
            'aiTemplateItems' => $aiTemplateItems,
            'deliveryModes' => $this->eligibility->supportedDeliveryModes(),
            'articleTypes' => $this->eligibility->supportedArticleTypes(),
            'campaignForm' => $campaignForm,
            'campaignPresetFields' => $campaignPresetForm->toClientPayload('edit', [
                'mode' => 'edit',
                'context' => 'edit',
                'record' => $campaign->campaignPreset,
            ]),
            'campaignPresetValues' => \hexa_app_publish\Publishing\Campaigns\Forms\CampaignPresetForm::values($campaign->campaignPreset),
            'articlePresetFields' => $articlePresetForm->toClientPayload('edit', [
                'mode' => 'edit',
                'context' => 'edit',
                'record' => $campaign->template,
            ]),
            'articlePresetValues' => \hexa_app_publish\Publishing\Templates\Forms\ArticlePresetForm::values($campaign->template),
            'campaignPresetSchema' => \hexa_app_publish\Publishing\Campaigns\Models\CampaignPreset::getFieldSchema('edit'),
            'articlePresetSchema' => \hexa_app_publish\Publishing\Templates\Models\PublishTemplate::getFieldSchema('edit'),
            'cronJob' => $cronJob,
            'lastArticle' => $lastArticle,
            'checklistDefinitions' => [
                'draft-local' => $this->checklistService->definitions('draft-local'),
                'draft-wordpress' => $this->checklistService->definitions('draft-wordpress'),
                'auto-publish' => $this->checklistService->definitions('auto-publish'),
            ],
        ]);
    }


    public function startOperation(int $id, Request $request): JsonResponse
    {
        $campaign = PublishCampaign::findOrFail($id);
        $mode = $this->modeResolver->normalizeDeliveryMode($request->input('mode', 'draft-wordpress'));

        try {
            $started = $this->runOperationService->start($campaign, $mode);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => $mode === 'auto-publish' ? 'Instant publish started.' : 'Instant draft started.',
            'mode' => $mode,
            'checklist' => $this->checklistService->definitions($mode),
            'article_id' => $started['article']->id,
            'article_url' => route('publish.articles.show', $started['article']->id),
            'operation' => $this->serializeOperation($started['operation']),
        ]);
    }

    /**
     * Run campaign once (one-off). Returns SSE stream of progress.
     *
     * @param int $id
     * @param Request $request
     * @return JsonResponse
     */
    public function runNow(int $id, Request $request): JsonResponse
    {
        $campaign = PublishCampaign::findOrFail($id);
        $mode = $this->modeResolver->normalizeDeliveryMode($request->input('mode', $campaign->delivery_mode ?: 'draft-local'));

        try {
            $started = $this->runOperationService->start($campaign, $mode);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => $mode === 'auto-publish' ? 'Campaign run started.' : 'Campaign draft run started.',
            'mode' => $mode,
            'checklist' => $this->checklistService->definitions($mode),
            'article_id' => $started['article']->id,
            'article_url' => route('publish.articles.show', $started['article']->id),
            'operation' => $this->serializeOperation($started['operation']),
        ]);
    }


    private function serializeOperation(PublishPipelineOperation $operation): array
    {
        return [
            'id' => $operation->id,
            'draft_id' => $operation->publish_article_id,
            'site_id' => $operation->publish_site_id,
            'operation_type' => $operation->operation_type,
            'status' => $operation->status,
            'workflow_type' => $operation->workflow_type,
            'transport' => $operation->transport,
            'queue_connection' => $operation->queue_connection,
            'queue_name' => $operation->queue_name,
            'client_trace' => $operation->client_trace,
            'trace_id' => $operation->trace_id,
            'debug_enabled' => $operation->debug_enabled,
            'event_sequence' => $operation->event_sequence,
            'total_events' => $operation->total_events,
            'last_stage' => $operation->last_stage,
            'last_substage' => $operation->last_substage,
            'last_message' => $operation->last_message,
            'error_message' => $operation->error_message,
            'request_summary' => $operation->request_summary,
            'result_payload' => $operation->result_payload,
            'started_at' => optional($operation->started_at)->toIso8601String(),
            'completed_at' => optional($operation->completed_at)->toIso8601String(),
            'last_event_at' => optional($operation->last_event_at)->toIso8601String(),
            'stream_supported' => $this->pipelineOperationService->supportsLiveStream(),
            'show_url' => route('publish.pipeline.operations.show', ['operation' => $operation->id]),
            'stream_url' => route('publish.pipeline.operations.stream', ['operation' => $operation->id]),
        ];
    }

    /**
     * Delete a campaign.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function destroy(int $id): JsonResponse
    {
        $campaign = PublishCampaign::findOrFail($id);
        $name = $campaign->name;
        $campaign->delete();

        hexaLog('campaigns', 'campaign_deleted', "Campaign deleted: {$name}");

        return response()->json(['success' => true, 'message' => "Campaign '{$name}' deleted."]);
    }

    /**
     * Show edit form for a campaign.
     *
     * @param int $id
     * @return View
     */
    public function edit(int $id): RedirectResponse
    {
        PublishCampaign::findOrFail($id);

        return redirect()->route('campaigns.show', ['id' => $id]);
    }


    /**
     * Update a campaign.
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $campaign = PublishCampaign::findOrFail($id);
        $this->normalizeNullableFields($request);

        $validated = $request->validate([
            'user_id' => 'nullable|integer',
            'publish_site_id' => 'nullable|integer',
            'publish_template_id' => 'nullable|integer',
            'campaign_preset_id' => 'nullable|integer',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'ai_instructions' => 'nullable|string',
            'topic' => 'nullable|string|max:1000',
            'keywords' => 'nullable|array',
            'article_type' => ['nullable', 'string', Rule::in($this->eligibility->supportedArticleTypes())],
            'ai_engine' => 'nullable|string|max:100',
            'author' => 'nullable|string|max:255',
            'post_status' => 'nullable|in:publish,draft,pending',
            'delivery_mode' => ['nullable', 'string', Rule::in($this->eligibility->supportedDeliveryModes())],
            'articles_per_interval' => 'nullable|integer|min:1|max:50',
            'interval_unit' => 'nullable|in:hourly,daily,weekly,monthly',
            'run_at_time' => 'nullable|string|max:10',
            'drip_interval_minutes' => 'nullable|integer|min:1|max:1440',
        ]);

        // Resolve account from site
        if (!empty($validated['publish_site_id'])) {
            $site = PublishSite::find($validated['publish_site_id']);
            $validated['publish_account_id'] = $site ? ($site->publish_account_id ?: null) : null;
        }

        if (array_key_exists('delivery_mode', $validated)) {
            $validated['delivery_mode'] = $this->modeResolver->normalizeDeliveryMode($validated['delivery_mode']);
            $validated['auto_publish'] = $validated['delivery_mode'] === 'auto-publish';
        }

        $validated['timezone'] = $this->resolveCampaignTimezone(
            $validated['user_id'] ?? $campaign->user_id,
            $campaign->timezone
        );

        $campaign->update(array_filter($validated, fn($value, $key) => $value !== null || $request->exists($key), ARRAY_FILTER_USE_BOTH));

        hexaLog('campaigns', 'campaign_updated', "Campaign updated: {$campaign->name}");

        return response()->json([
            'success' => true,
            'message' => "Campaign '{$campaign->name}' updated successfully.",
        ]);
    }

    /**
     * Activate a campaign (start scheduling).
     *
     * @param int $id
     * @return JsonResponse
     */
    public function activate(int $id): JsonResponse
    {
        $campaign = PublishCampaign::findOrFail($id);

        $campaign->update([
            'status' => 'active',
            'next_run_at' => $this->scheduleService->initialRunAt($campaign),
        ]);

        hexaLog('publish', 'campaign_activated', "Campaign activated: {$campaign->name} ({$campaign->campaign_id})");

        return response()->json([
            'success' => true,
            'message' => "Campaign '{$campaign->name}' is now active.",
        ]);
    }

    /**
     * Pause a campaign.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function pause(int $id): JsonResponse
    {
        $campaign = PublishCampaign::findOrFail($id);

        $campaign->update([
            'status' => 'paused',
            'next_run_at' => null,
        ]);

        hexaLog('publish', 'campaign_paused', "Campaign paused: {$campaign->name} ({$campaign->campaign_id})");

        return response()->json([
            'success' => true,
            'message' => "Campaign '{$campaign->name}' is now paused.",
        ]);
    }

    /**
     * Duplicate a campaign (copy rules to a new draft campaign).
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function duplicate(Request $request, int $id): JsonResponse
    {
        $source = PublishCampaign::findOrFail($id);

        $newSiteId = $request->input('publish_site_id', $source->publish_site_id);

        $duplicate = $source->replicate([
            'campaign_id',
            'status',
            'last_run_at',
            'next_run_at',
            'created_by',
        ]);

        $duplicate->campaign_id = PublishCampaign::generateCampaignId();
        $duplicate->name = $source->name . ' (Copy)';
        $duplicate->status = 'draft';
        $duplicate->publish_site_id = $newSiteId;
        $duplicate->created_by = auth()->id();
        $duplicate->save();

        hexaLog('publish', 'campaign_duplicated', "Campaign duplicated: {$source->name} → {$duplicate->name}");

        return response()->json([
            'success' => true,
            'message' => "Campaign duplicated successfully.",
            'redirect' => route('campaigns.show', $duplicate->id),
        ]);
    }

    private function normalizeNullableFields(Request $request): void
    {
        foreach ([
            'user_id',
            'publish_site_id',
            'publish_template_id',
            'campaign_preset_id',
            'description',
            'ai_instructions',
            'topic',
            'article_type',
            'ai_engine',
            'author',
            'post_status',
            'delivery_mode',
            'run_at_time',
        ] as $field) {
            if ($request->exists($field) && $request->input($field) === '') {
                $request->merge([$field => null]);
            }
        }
    }

    private function resolveCampaignTimezone(?int $userId, ?string $fallback = null): string
    {
        if ($userId) {
            $user = \hexa_core\Models\User::find($userId);
            if ($user && !empty($user->timezone)) {
                return (string) $user->timezone;
            }
        }

        return $fallback ?: config('hws.timezone', 'America/New_York');
    }
}
