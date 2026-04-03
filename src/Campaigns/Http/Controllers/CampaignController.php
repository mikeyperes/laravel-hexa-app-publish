<?php

namespace hexa_app_publish\Campaigns\Http\Controllers;

use hexa_core\Http\Controllers\Controller;
use hexa_core\Services\GenericService;
use hexa_app_publish\Models\PublishAccount;
use hexa_app_publish\Models\PublishCampaign;
use hexa_app_publish\Models\PublishSite;
use hexa_app_publish\Models\PublishTemplate;
use hexa_app_publish\Models\PublishPreset;
use hexa_app_publish\Services\PublishService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CampaignController extends Controller
{
    protected GenericService $generic;
    protected PublishService $publishService;

    /**
     * @param GenericService $generic
     * @param PublishService $publishService
     */
    public function __construct(GenericService $generic, PublishService $publishService)
    {
        $this->generic = $generic;
        $this->publishService = $publishService;
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

        return view('app-publish::campaigns.index', [
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
    public function create(Request $request): View
    {
        $sites = PublishSite::where('status', 'connected')->orderBy('name')->get();
        $campaignPresets = \hexa_app_publish\Campaigns\Models\CampaignPreset::orderBy('name')->get();
        $aiTemplates = PublishTemplate::orderBy('name')->get();
        $wpPresets = PublishPreset::orderBy('name')->get();
        $editCampaign = $request->filled('id') ? PublishCampaign::find($request->input('id')) : null;
        $timezones = \DateTimeZone::listIdentifiers(\DateTimeZone::ALL);

        return view('app-publish::campaigns.create', [
            'sites' => $sites,
            'campaignPresets' => $campaignPresets,
            'aiTemplates' => $aiTemplates,
            'wpPresets' => $wpPresets,
            'editCampaign' => $editCampaign,
            'timezones' => $timezones,
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
        $validated = $request->validate([
            'user_id' => 'nullable|integer|exists:users,id',
            'publish_site_id' => 'required|exists:publish_sites,id',
            'publish_template_id' => 'nullable|exists:publish_templates,id',
            'campaign_preset_id' => 'nullable|integer',
            'preset_id' => 'nullable|integer',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'topic' => 'nullable|string|max:1000',
            'keywords' => 'nullable|array',
            'auto_publish' => 'nullable|boolean',
            'author' => 'nullable|string|max:255',
            'post_status' => 'nullable|in:publish,draft,pending',
            'delivery_mode' => 'nullable|in:draft-local,draft-wordpress,auto-publish,review,notify',
            'articles_per_interval' => 'required|integer|min:1|max:50',
            'interval_unit' => 'required|in:hourly,daily,weekly,monthly',
            'timezone' => 'nullable|string|max:50',
            'run_at_time' => 'nullable|string|max:10',
            'drip_interval_minutes' => 'nullable|integer|min:1|max:1440',
            'notes' => 'nullable|string',
        ]);

        $validated['campaign_id'] = PublishCampaign::generateCampaignId();
        $validated['status'] = 'draft';
        $validated['created_by'] = auth()->id();
        $validated['auto_publish'] = $validated['auto_publish'] ?? false;
        $validated['drip_interval_minutes'] = $validated['drip_interval_minutes'] ?? 60;

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
            'account', 'site', 'template', 'creator', 'user', 'campaignPreset', 'wpPreset',
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

        return view('app-publish::campaigns.show', [
            'campaign' => $campaign,
            'runLogs' => $runLogs,
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
        $mode = $request->input('mode', 'draft');

        $runService = app(\hexa_app_publish\Campaigns\Services\CampaignRunService::class);
        $result = $runService->run($campaign, $mode);

        return response()->json([
            'success' => $result['success'],
            'message' => $result['success']
                ? "Campaign ran successfully. Article: " . ($result['article']->article_id ?? '—')
                : "Campaign run failed.",
            'log' => $result['log'],
            'article_id' => $result['article']->id ?? null,
            'article_url' => $result['article'] ? route('publish.articles.show', $result['article']->id) : null,
        ]);
    }

    /**
     * Show edit form for a campaign.
     *
     * @param int $id
     * @return View
     */
    public function edit(int $id): View
    {
        $campaign = PublishCampaign::with(['account', 'site', 'template'])->findOrFail($id);
        $accounts = PublishAccount::where('status', 'active')->orderBy('name')->get();
        $sites = PublishSite::where('publish_account_id', $campaign->publish_account_id)->orderBy('name')->get();
        $templates = PublishTemplate::where('publish_account_id', $campaign->publish_account_id)->orderBy('name')->get();

        return view('app-publish::campaigns.edit', [
            'campaign' => $campaign,
            'accounts' => $accounts,
            'sites' => $sites,
            'templates' => $templates,
            'deliveryModes' => config('hws-publish.campaign_modes', []),
            'intervalUnits' => config('hws-publish.campaign_intervals', []),
            'articleTypes' => config('hws-publish.article_types', []),
            'aiEngines' => config('hws-publish.ai_engines', []),
            'articleSources' => config('hws-publish.article_sources', []),
            'photoSources' => config('hws-publish.photo_sources', []),
        ]);
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

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'topic' => 'nullable|string|max:1000',
            'keywords' => 'nullable|array',
            'article_type' => 'nullable|string|max:50',
            'ai_engine' => 'nullable|in:anthropic,chatgpt',
            'delivery_mode' => 'required|in:draft-local,draft-wordpress,auto-publish,review,notify',
            'articles_per_interval' => 'required|integer|min:1|max:50',
            'interval_unit' => 'required|in:hourly,daily,weekly,monthly',
            'article_sources' => 'nullable|array',
            'photo_sources' => 'nullable|array',
            'link_list' => 'nullable|array',
            'sitemap_urls' => 'nullable|array',
            'max_links_per_article' => 'nullable|integer|min:0',
            'notes' => 'nullable|string',
        ]);

        $campaign->update($validated);

        hexaLog('publish', 'campaign_updated', "Campaign updated: {$campaign->name} ({$campaign->campaign_id})");

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
            'next_run_at' => now(),
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
            'redirect' => route('publish.campaigns.show', $duplicate->id),
        ]);
    }
}
