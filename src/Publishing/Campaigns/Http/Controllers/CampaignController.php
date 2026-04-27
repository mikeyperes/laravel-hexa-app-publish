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
use hexa_package_whm\Models\HostingAccount;
use hexa_package_whm\Models\WhmServer;
use hexa_package_wptoolkit\Services\WpToolkitService;
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
                'article_type' => 'editorial',
                'created_by' => auth()->id(),
            ]);
        }

        $sites = PublishSite::where('status', 'connected')->orderBy('name')->get();
        $campaignPresets = \hexa_app_publish\Publishing\Campaigns\Models\CampaignPreset::orderBy('name')->get();
        $aiTemplates = $this->editorialCampaignTemplates()->orderBy('name')->get();

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
            'publish_template_id' => ['nullable', Rule::exists('publish_templates', 'id')->where(fn ($query) => $query->where('article_type', 'editorial'))],
            'campaign_preset_id' => 'nullable|integer',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'ai_instructions' => 'nullable|string',
            'topic' => 'nullable|string|max:1000',
            'keywords' => 'nullable|array',
            'link_list' => 'nullable|array',
            'link_list.*' => 'nullable|url|max:2000',
            'article_sources' => 'nullable|array',
            'article_sources.*' => 'nullable|string|max:100',
            'photo_sources' => 'nullable|array',
            'photo_sources.*' => 'nullable|string|max:100',
            'max_links_per_article' => 'nullable|integer|min:0|max:25',
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
        $validated['article_type'] = 'editorial';
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
        $aiTemplates = $this->editorialCampaignTemplates()->with('account')->orderBy('name')->get();
        $timezone = $campaign->timezone ?: config('app.timezone', 'America/New_York');
        $cronJobs = \hexa_core\CronManager\Models\CronJob::query()
            ->where('package_name', 'app-publish')
            ->orderBy('name')
            ->get();
        $primaryCronJob = $cronJobs->firstWhere('command', 'publish:run-campaigns') ?: $cronJobs->first();
        $schedulerHealth = $this->buildSchedulerHealth($this->readSchedulerCrontab(), base_path());
        $cronJobsData = $cronJobs->map(fn (\hexa_core\CronManager\Models\CronJob $job) => $this->serializeCronJobSummary($job, $timezone))->values();
        $recentOperations = PublishPipelineOperation::query()
            ->with(['article' => function ($query) {
                $query->select([
                    'id',
                    'article_id',
                    'title',
                    'status',
                    'wp_status',
                    'wp_post_url',
                    'publish_campaign_id',
                    'created_at',
                    'updated_at',
                    'ai_provider',
                    'ai_engine_used',
                    'word_count',
                    'source_articles',
                    'wp_images',
                ]);
            }])
            ->where('request_summary->campaign_id', $campaign->id)
            ->latest('id')
            ->limit(8)
            ->get();
        $activeOperation = $recentOperations->first(fn (PublishPipelineOperation $operation) => $operation->isActive() && !$operation->isStale());
        $staleOperation = $recentOperations->first(fn (PublishPipelineOperation $operation) => $operation->isActive() && $operation->isStale());
        $activeOperationArticle = $activeOperation?->article
            ? $this->serializeCampaignArticleSummary($activeOperation->article, $campaign->id)
            : null;
        $staleOperationArticle = $staleOperation?->article
            ? $this->serializeCampaignArticleSummary($staleOperation->article, $campaign->id)
            : null;
        $recentOperationSummaries = $recentOperations->map(fn (PublishPipelineOperation $operation) => $this->serializeCampaignOperationSummary($operation, $timezone))->values();
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
            'link_list' => array_values((array) ($campaign->link_list ?? [])),
            'article_sources' => array_values((array) ($campaign->article_sources ?? [])),
            'photo_sources' => array_values((array) ($campaign->photo_sources ?? [])),
            'max_links_per_article' => $campaign->max_links_per_article ?? null,
            'delivery_mode' => $this->modeResolver->normalizeDeliveryMode($campaign->delivery_mode ?? 'draft-wordpress'),
            'article_type' => $campaign->article_type ?? '',
            'author' => $campaign->author ?? '',
            'articles_per_interval' => $campaign->articles_per_interval ?? 1,
            'interval_unit' => $campaign->interval_unit ?? 'daily',
            'run_at_time' => $campaign->run_at_time ?? '09:00',
            'drip_interval_minutes' => $campaign->drip_interval_minutes ?? 60,
        ];

        $initialSiteAuthors = $this->loadInitialSiteAuthors($campaign->site, $campaign->author);

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
            'campaignPresetOverrides' => (array) ($campaign->campaign_preset_overrides ?? []),
            'articlePresetFields' => $articlePresetForm->toClientPayload('edit', [
                'mode' => 'edit',
                'context' => 'edit',
                'record' => $campaign->template,
            ]),
            'articlePresetValues' => \hexa_app_publish\Publishing\Templates\Forms\ArticlePresetForm::values($campaign->template),
            'articlePresetOverrides' => (array) ($campaign->article_preset_overrides ?? []),
            'campaignPresetSchema' => \hexa_app_publish\Publishing\Campaigns\Models\CampaignPreset::getFieldSchema('edit'),
            'articlePresetSchema' => \hexa_app_publish\Publishing\Templates\Models\PublishTemplate::getFieldSchema('edit'),
            'cronJobsData' => $cronJobsData,
            'primaryCronRunUrl' => $primaryCronJob ? route('tools.cron-manager.run', ['cronJob' => $primaryCronJob->id]) : null,
            'cronManagerUrl' => route('tools.cron-manager'),
            'schedulerHealth' => $schedulerHealth,
            'recentOperations' => $recentOperationSummaries,
            'activeOperation' => $activeOperation ? $this->serializeOperation($activeOperation) : null,
            'staleOperation' => $staleOperation ? $this->serializeOperation($staleOperation) : null,
            'activeOperationArticle' => $activeOperationArticle,
            'staleOperationArticle' => $staleOperationArticle,
            'displayTimezone' => $timezone,
            'lastArticle' => $lastArticle,
            'checklistDefinitions' => [
                'draft-local' => $this->checklistService->definitions('draft-local'),
                'draft-wordpress' => $this->checklistService->definitions('draft-wordpress'),
                'auto-publish' => $this->checklistService->definitions('auto-publish'),
            ],
            'initialSiteAuthors' => $initialSiteAuthors,
            'initialSiteDefaultAuthor' => $campaign->site?->default_author,
        ]);
    }

    private function readSchedulerCrontab(): ?string
    {
        try {
            $process = \Illuminate\Support\Facades\Process::run('crontab -l 2>/dev/null');
            if (!$process->successful()) {
                return null;
            }

            $output = trim($process->output());

            return $output !== '' ? $output : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function buildSchedulerHealth(?string $crontab, string $appPath): array
    {
        $lines = collect(preg_split('/\r?\n/', (string) $crontab))
            ->map(fn ($line) => trim((string) $line))
            ->filter(fn ($line) => $line !== '' && !str_starts_with($line, '#'))
            ->values();

        $appMarker = basename($appPath);
        $scheduleEntry = $lines->first(function (string $line) use ($appMarker) {
            return str_contains($line, 'artisan schedule:run')
                && (str_contains($line, 'publish.scalemypublication.com') || str_contains($line, $appMarker));
        });

        return [
            'installed' => $scheduleEntry !== null,
            'entry' => $scheduleEntry,
            'line_count' => $lines->count(),
            'message' => $scheduleEntry
                ? 'Laravel scheduler entry is installed for this publish app.'
                : 'Laravel scheduler entry is missing for this publish app.',
        ];
    }

    private function serializeCronJobSummary(\hexa_core\CronManager\Models\CronJob $job, string $timezone): array
    {
        $nextRun = null;

        try {
            $nextRun = \Carbon\Carbon::instance(
                \Cron\CronExpression::factory($job->schedule)->getNextRunDate('now', 0, false, $timezone)
            );
        } catch (\Throwable $e) {
            $nextRun = null;
        }

        return [
            'id' => $job->id,
            'name' => $job->name,
            'command' => $job->command,
            'description' => $job->description,
            'package_name' => $job->package_name,
            'enabled' => (bool) $job->is_enabled,
            'schedule' => $job->schedule,
            'schedule_label' => $job->getScheduleLabel(),
            'last_run' => $this->formatAuditTimestamp($job->last_run_at, $timezone, 'Never'),
            'next_run' => $this->formatAuditTimestamp($nextRun, $timezone, 'Pending'),
            'last_status' => $job->last_status ?: 'never',
            'run_count' => (int) ($job->run_count ?: 0),
            'created_at' => $this->formatAuditTimestamp($job->created_at, $timezone, '—'),
            'updated_at' => $this->formatAuditTimestamp($job->updated_at, $timezone, '—'),
            'last_output' => $job->last_output ? trim((string) $job->last_output) : null,
            'last_output_preview' => $this->truncateCronOutput($job->last_output),
            'output_url' => route('tools.cron-manager.output', ['cronJob' => $job->id]),
            'run_url' => route('tools.cron-manager.run', ['cronJob' => $job->id]),
        ];
    }

    private function serializeCampaignOperationSummary(PublishPipelineOperation $operation, string $timezone): array
    {
        $article = $operation->article;
        $campaignId = (int) ($operation->request_summary['campaign_id'] ?? ($article->publish_campaign_id ?? 0));

        return [
            'id' => $operation->id,
            'status' => $operation->status,
            'mode' => $operation->request_summary['mode'] ?? null,
            'last_stage' => $operation->last_stage,
            'last_message' => $operation->error_message ?: $operation->last_message,
            'created_at' => $this->formatAuditTimestamp($operation->created_at, $timezone, '—'),
            'completed_at' => $this->formatAuditTimestamp($operation->completed_at, $timezone, '—'),
            'article' => $article ? [
                'id' => $article->id,
                'article_id' => $article->article_id,
                'title' => $article->title ?: 'Untitled Article',
                'status' => $article->status,
                'wp_status' => $article->wp_status,
                'show_url' => $campaignId > 0 ? $this->buildCampaignArticleUrl($article->id, $campaignId) : route('publish.articles.show', $article->id),
                'wp_post_url' => $article->wp_post_url,
                'ai_provider' => $article->ai_provider,
                'ai_engine_used' => $article->ai_engine_used,
                'word_count' => $article->word_count,
                'source_count' => count((array) ($article->source_articles ?? [])),
                'source_domains' => collect((array) ($article->source_articles ?? []))
                    ->map(fn ($source) => parse_url((string) ($source['url'] ?? ''), PHP_URL_HOST))
                    ->filter()
                    ->unique()
                    ->values()
                    ->all(),
                'thumbnail_url' => $this->resolveArticleThumbnail($article->wp_images),
            ] : null,
        ];
    }

    private function resolveArticleThumbnail($images): ?string
    {
        if (!is_array($images) || empty($images)) {
            return null;
        }

        $featured = collect($images)->firstWhere('is_featured', true);
        if (!$featured) {
            $featured = collect($images)->first();
        }

        if (!is_array($featured)) {
            return null;
        }

        return $featured['sizes']['thumbnail']
            ?? $featured['sizes']['medium']
            ?? $featured['inline_url']
            ?? $featured['media_url']
            ?? null;
    }

    private function formatAuditTimestamp($date, string $timezone, string $fallback = '—'): array
    {
        if (!$date) {
            return [
                'iso' => null,
                'display' => $fallback,
                'relative' => null,
            ];
        }

        $local = $date instanceof \Carbon\CarbonInterface
            ? $date->copy()->setTimezone($timezone)
            : \Carbon\Carbon::parse($date)->setTimezone($timezone);

        return [
            'iso' => $local->toIso8601String(),
            'display' => $local->format('M j, Y g:i A T'),
            'relative' => $local->diffForHumans(),
        ];
    }

    private function truncateCronOutput(?string $output, int $limit = 280): ?string
    {
        $output = trim((string) $output);
        if ($output === '' || $output === '(no output)') {
            return null;
        }

        if (mb_strlen($output) <= $limit) {
            return $output;
        }

        return mb_substr($output, 0, $limit - 1) . '…';
    }

    private function loadInitialSiteAuthors(?PublishSite $site, ?string $selectedAuthor = null): array
    {
        $authors = [];

        if ($site && $site->isWpToolkit() && $site->hosting_account_id && $site->wordpress_install_id) {
            try {
                $account = HostingAccount::find($site->hosting_account_id);
                $server = $account ? WhmServer::find($account->whm_server_id) : null;

                if ($server) {
                    $result = app(WpToolkitService::class)->wpCliListAdminUsers($server, (int) $site->wordpress_install_id);
                    $authors = array_values(array_filter((array) ($result['authors'] ?? []), fn ($author) => is_array($author) && filled($author['user_login'] ?? null)));
                }
            } catch (\Throwable $e) {
                $authors = [];
            }
        }

        return $this->appendMissingAuthorOptions($authors, array_filter([
            $selectedAuthor,
            $site?->default_author,
        ]));
    }

    private function appendMissingAuthorOptions(array $authors, array $preferredLogins): array
    {
        $known = [];

        foreach ($authors as $author) {
            $login = strtolower((string) ($author['user_login'] ?? ''));
            if ($login !== '') {
                $known[$login] = true;
            }
        }

        foreach ($preferredLogins as $login) {
            $login = trim((string) $login);
            if ($login === '') {
                continue;
            }

            $key = strtolower($login);
            if (!isset($known[$key])) {
                $authors[] = [
                    'id' => null,
                    'user_login' => $login,
                    'display_name' => $login,
                    'roles' => ['saved'],
                ];
                $known[$key] = true;
            }
        }

        usort($authors, function (array $left, array $right): int {
            $leftLabel = (string) ($left['display_name'] ?? $left['user_login'] ?? '');
            $rightLabel = (string) ($right['display_name'] ?? $right['user_login'] ?? '');

            return strcasecmp($leftLabel, $rightLabel);
        });

        return array_values($authors);
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
            'article_url' => $this->buildCampaignArticleUrl($started['article']->id, $campaign->id),
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
    public function searchAuthors(int $id, Request $request): JsonResponse
    {
        $campaign = PublishCampaign::findOrFail($id);
        $site = $campaign->publish_site_id ? PublishSite::find($campaign->publish_site_id) : null;

        if (!$site || !$site->wordpress_install_id) {
            return response()->json(['success' => true, 'data' => []]);
        }

        $cacheKey = 'campaigns:' . $campaign->id . ':site:' . $site->id . ':authors';
        $authors = \Illuminate\Support\Facades\Cache::remember($cacheKey, 600, function () use ($site) {
            $server = $site->publish_account_id
                ? WhmServer::whereHas('accounts', fn ($q) => $q->where('id', $site->publish_account_id))->first()
                : null;
            if (!$server) {
                $hosting = HostingAccount::find($site->publish_account_id);
                $server = $hosting?->whm_server;
            }
            if (!$server) {
                return [];
            }
            $result = app(WpToolkitService::class)->wpCliListAdminUsers($server, (int) $site->wordpress_install_id);
            return $result['authors'] ?? [];
        });

        $q = trim((string) $request->input('q', ''));
        $limit = (int) ($request->input('limit', 15) ?: 15);
        $limit = max(1, min(50, $limit));

        $filtered = $q === ''
            ? array_slice($authors, 0, $limit)
            : array_values(array_filter($authors, function ($a) use ($q) {
                $hay = strtolower(
                    ($a['display_name'] ?? '') . ' '
                    . ($a['username'] ?? '') . ' '
                    . ($a['email'] ?? '') . ' '
                    . ($a['slug'] ?? '')
                );
                return str_contains($hay, strtolower($q));
            }));

        $filtered = array_slice($filtered, 0, $limit);

        $data = array_map(function ($a) {
            $name = $a['display_name'] ?? $a['name'] ?? $a['username'] ?? $a['slug'] ?? '';
            return [
                'id' => $a['id'] ?? $a['ID'] ?? $name,
                'name' => $name,
                'display_name' => $name,
                'username' => $a['username'] ?? $a['slug'] ?? $name,
                'email' => $a['email'] ?? '',
                'slug' => $a['slug'] ?? \Illuminate\Support\Str::slug($name),
            ];
        }, $filtered);

        return response()->json(['success' => true, 'data' => $data]);
    }

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
            'article_url' => $this->buildCampaignArticleUrl($started['article']->id, $campaign->id),
            'operation' => $this->serializeOperation($started['operation']),
        ]);
    }

    public function stopOperation(int $id, PublishPipelineOperation $operation): JsonResponse
    {
        $campaign = PublishCampaign::findOrFail($id);

        abort_unless(
            (int) ($operation->request_summary['campaign_id'] ?? 0) === (int) $campaign->id
            || (int) ($operation->article?->publish_campaign_id ?? 0) === (int) $campaign->id,
            404
        );

        $operation = $this->pipelineOperationService->requestCancellation($operation, auth()->id());
        if ($operation->article) {
            $payload = (array) ($operation->result_payload ?? []);
            $payload['article_id'] = $operation->article->id;
            $payload['article_url'] = $this->buildCampaignArticleUrl($operation->article->id, $campaign->id);
            $operation->forceFill(['result_payload' => $payload])->save();
            $operation = $operation->fresh();
        }

        if ($operation->article && !in_array((string) $operation->article->status, ['completed', 'failed'], true)) {
            $operation->article->update(['status' => 'failed']);
        }

        return response()->json([
            'success' => true,
            'message' => 'Run stop requested.',
            'operation' => $this->serializeOperation($operation),
        ]);
    }

    private function serializeCampaignArticleSummary(\hexa_app_publish\Publishing\Articles\Models\PublishArticle $article, int $campaignId): array
    {
        return [
            'show_url' => $this->buildCampaignArticleUrl($article->id, $campaignId),
            'article_id' => $article->article_id,
            'article_record_id' => $article->id,
            'title' => $article->title ?: 'Untitled Article',
            'wp_post_url' => $article->wp_post_url,
            'thumbnail_url' => $this->resolveArticleThumbnail($article->wp_images),
            'ai_provider' => $article->ai_provider,
            'ai_engine_used' => $article->ai_engine_used,
            'word_count' => $article->word_count,
            'source_count' => count((array) ($article->source_articles ?? [])),
            'source_domains' => collect((array) ($article->source_articles ?? []))
                ->map(fn ($source) => parse_url((string) ($source['url'] ?? ''), PHP_URL_HOST))
                ->filter()
                ->unique()
                ->values()
                ->all(),
        ];
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
            'is_stale' => $operation->isStale(),
            'is_cancelled' => $operation->isCancelled(),
            'stream_supported' => $this->pipelineOperationService->supportsLiveStream(),
            'show_url' => route('publish.pipeline.operations.show', ['operation' => $operation->id]),
            'stream_url' => route('publish.pipeline.operations.stream', ['operation' => $operation->id]),
        ];
    }

    private function buildCampaignArticleUrl(int $articleId, int $campaignId): string
    {
        return route('publish.articles.show', [
            'id' => $articleId,
            'campaign_id' => $campaignId,
            'article_id' => $articleId,
        ]);
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
            'publish_template_id' => ['nullable', Rule::exists('publish_templates', 'id')->where(fn ($query) => $query->where('article_type', 'editorial'))],
            'campaign_preset_id' => 'nullable|integer',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'ai_instructions' => 'nullable|string',
            'topic' => 'nullable|string|max:1000',
            'keywords' => 'nullable|array',
            'link_list' => 'nullable|array',
            'link_list.*' => 'nullable|url|max:2000',
            'article_sources' => 'nullable|array',
            'article_sources.*' => 'nullable|string|max:100',
            'photo_sources' => 'nullable|array',
            'photo_sources.*' => 'nullable|string|max:100',
            'max_links_per_article' => 'nullable|integer|min:0|max:25',
            'article_type' => ['nullable', 'string', Rule::in($this->eligibility->supportedArticleTypes())],
            'ai_engine' => 'nullable|string|max:100',
            'author' => 'nullable|string|max:255',
            'post_status' => 'nullable|in:publish,draft,pending',
            'delivery_mode' => ['nullable', 'string', Rule::in($this->eligibility->supportedDeliveryModes())],
            'articles_per_interval' => 'nullable|integer|min:1|max:50',
            'interval_unit' => 'nullable|in:hourly,daily,weekly,monthly',
            'run_at_time' => 'nullable|string|max:10',
            'drip_interval_minutes' => 'nullable|integer|min:1|max:1440',
            'campaign_preset_overrides' => 'nullable|array',
            'article_preset_overrides' => 'nullable|array',
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

        $validated['article_type'] = 'editorial';

        $validated['timezone'] = $this->resolveCampaignTimezone(
            $validated['user_id'] ?? $campaign->user_id,
            $campaign->timezone
        );

        if ($request->exists('campaign_preset_overrides')) {
            $validated['campaign_preset_overrides'] = $this->resolveCampaignPresetOverrides(
                $campaign,
                (array) ($validated['campaign_preset_overrides'] ?? [])
            );
        }

        if ($request->exists('article_preset_overrides')) {
            $validated['article_preset_overrides'] = $this->resolveArticlePresetOverrides(
                $campaign,
                (array) ($validated['article_preset_overrides'] ?? [])
            );
        }

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
            'max_links_per_article',
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

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>|null
     */
    private function resolveCampaignPresetOverrides(PublishCampaign $campaign, array $payload): ?array
    {
        if ($payload === []) {
            return null;
        }

        $runtime = app(\hexa_core\Forms\Runtime\FormRuntimeService::class);
        $context = [
            'context' => 'pipeline',
            'mode' => 'pipeline',
            'record' => $campaign->campaignPreset,
        ];

        $normalized = $runtime->normalizeSubmission(\hexa_app_publish\Publishing\Campaigns\Forms\CampaignPresetForm::FORM_KEY, $payload, $context);
        $defaults = $runtime->hydrate(\hexa_app_publish\Publishing\Campaigns\Forms\CampaignPresetForm::FORM_KEY, $campaign->campaignPreset, [], $context);
        $dirty = [];

        foreach (array_keys($payload) as $key) {
            $value = $normalized[$key] ?? null;
            if (!$this->valuesDiffer($value, $defaults[$key] ?? null)) {
                continue;
            }

            $dirty[$key] = $value;
        }

        $dehydrated = $runtime->dehydrate(\hexa_app_publish\Publishing\Campaigns\Forms\CampaignPresetForm::FORM_KEY, $dirty, $context);
        unset($dehydrated['keywords'], $dehydrated['ai_instructions']);

        return !empty($dehydrated) ? $dehydrated : null;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>|null
     */
    private function resolveArticlePresetOverrides(PublishCampaign $campaign, array $payload): ?array
    {
        if ($payload === []) {
            return null;
        }

        $runtime = app(\hexa_core\Forms\Runtime\FormRuntimeService::class);
        $context = [
            'context' => 'pipeline',
            'mode' => 'pipeline',
            'record' => $campaign->template,
        ];

        $normalized = $runtime->normalizeSubmission(\hexa_app_publish\Publishing\Templates\Forms\ArticlePresetForm::FORM_KEY, $payload, $context);
        $defaults = $runtime->hydrate(\hexa_app_publish\Publishing\Templates\Forms\ArticlePresetForm::FORM_KEY, $campaign->template, [], $context);
        $dirty = [];

        foreach (array_keys($payload) as $key) {
            $value = $normalized[$key] ?? null;
            if (!$this->valuesDiffer($value, $defaults[$key] ?? null)) {
                continue;
            }

            $dirty[$key] = $value;
        }

        $dehydrated = $runtime->dehydrate(\hexa_app_publish\Publishing\Templates\Forms\ArticlePresetForm::FORM_KEY, $dirty, $context);
        unset($dehydrated['article_type']);

        return !empty($dehydrated) ? $dehydrated : null;
    }

    private function editorialCampaignTemplates()
    {
        return PublishTemplate::query()->where('article_type', 'editorial');
    }

    private function valuesDiffer(mixed $left, mixed $right): bool
    {
        if (is_array($left) || is_array($right)) {
            return json_encode($left) !== json_encode($right);
        }

        return $left !== $right;
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
