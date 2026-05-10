<?php

namespace hexa_app_publish\Publishing\Pipeline\Http\Controllers;

use hexa_core\Forms\Runtime\FormRuntimeService;
use hexa_core\Http\Controllers\Controller;
use hexa_core\Models\Setting;
use hexa_core\Models\User;
use hexa_app_publish\Models\PublishArticle;
use hexa_app_publish\Models\PublishSite;
use hexa_app_publish\Discovery\Links\Health\Services\LinkHealthService;
use hexa_app_publish\Discovery\Sources\Services\SourceDiscoveryService;
use hexa_app_publish\Discovery\Sources\Services\SourceExtractionService;
use hexa_app_publish\Discovery\Sources\Health\Services\SourceAccessStrategyService;
use hexa_app_publish\Publishing\Campaigns\Services\NewsDiscoveryOptionsService;
use hexa_app_publish\Publishing\Articles\Services\ArticleGenerationService;
use hexa_app_publish\Publishing\Articles\Services\ArticleActivityService;
use hexa_app_publish\Publishing\Articles\Services\MetadataGenerationService;
use hexa_app_publish\Publishing\Presets\Models\PublishPreset;
use hexa_app_publish\Publishing\Pipeline\Jobs\PreparePipelineOperationJob;
use hexa_app_publish\Publishing\Pipeline\Jobs\PublishPipelineOperationJob;
use hexa_app_publish\Publishing\Pipeline\Services\PipelineStateService;
use hexa_app_publish\Publishing\Pipeline\Services\PipelineHtmlSanitizer;
use hexa_app_publish\Publishing\Pipeline\Services\PipelineDraftSessionService;
use hexa_app_publish\Publishing\Pipeline\Services\PipelineOperationExecutor;
use hexa_app_publish\Publishing\Pipeline\Services\PipelineOperationService;
use hexa_app_publish\Publishing\Pipeline\Services\PrArticleWorkflowService;
use hexa_app_publish\Publishing\Pipeline\Services\PipelineWorkflowRegistry;
use hexa_app_publish\Publishing\Pipeline\Models\PublishPipelineOperation;
use hexa_app_publish\Publishing\Templates\Models\PublishTemplate;
use hexa_app_publish\Publishing\Pipeline\Http\Requests\CheckSourcesRequest;
use hexa_app_publish\Publishing\Pipeline\Http\Requests\DetectAiRequest;
use hexa_app_publish\Publishing\Pipeline\Http\Requests\GenerateMetadataRequest;
use hexa_app_publish\Publishing\Pipeline\Http\Requests\GeneratePhotoMetaRequest;
use hexa_app_publish\Publishing\Pipeline\Http\Requests\PrepareForWordpressRequest;
use hexa_app_publish\Publishing\Pipeline\Http\Requests\PreviewPromptRequest;
use hexa_app_publish\Publishing\Pipeline\Http\Requests\PublishToWordpressRequest;
use hexa_app_publish\Publishing\Pipeline\Http\Requests\SaveDraftRequest;
use hexa_app_publish\Publishing\Pipeline\Http\Requests\SpinRequest;
use hexa_app_publish\Support\AiModelCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * PublishPipelineController — 11-step article publishing pipeline.
 *
 * Handles: user search, source checking, AI spinning, WordPress
 * preparation/publishing, and draft persistence.
 */
class PipelineController extends Controller
{
    protected SourceExtractionService $sourceExtraction;
    protected NewsDiscoveryOptionsService $newsOptions;
    protected PipelineStateService $pipelineState;
    protected PipelineHtmlSanitizer $htmlSanitizer;
    protected PipelineDraftSessionService $draftSession;
    protected PipelineWorkflowRegistry $workflowRegistry;
    protected ArticleActivityService $articleActivity;
    protected FormRuntimeService $formRuntime;

    /**
     * @param SourceExtractionService $sourceExtraction
     */
    public function __construct(
        SourceExtractionService $sourceExtraction,
        NewsDiscoveryOptionsService $newsOptions,
        PipelineStateService $pipelineState,
        PipelineHtmlSanitizer $htmlSanitizer,
        PipelineDraftSessionService $draftSession,
        PipelineWorkflowRegistry $workflowRegistry,
        ArticleActivityService $articleActivity,
        FormRuntimeService $formRuntime
    )
    {
        $this->sourceExtraction = $sourceExtraction;
        $this->newsOptions = $newsOptions;
        $this->pipelineState = $pipelineState;
        $this->htmlSanitizer = $htmlSanitizer;
        $this->draftSession = $draftSession;
        $this->workflowRegistry = $workflowRegistry;
        $this->articleActivity = $articleActivity;
        $this->formRuntime = $formRuntime;
    }

    public function index(Request $request)
    {
        $forceFreshDraft = $request->boolean('spawn') || $request->boolean('fresh');

        // If no ?id= in URL, reuse an existing empty draft or create one
        if (!$request->has('id')) {
            $draft = null;

            if (!$forceFreshDraft) {
                $draft = PublishArticle::where('created_by', auth()->id())
                    ->where('status', 'drafting')
                    ->where(function ($q) {
                        $q->whereNull('article_type')
                            ->orWhere('article_type', '')
                            ->orWhere('article_type', 'editorial');
                    })
                    ->where(function ($q) {
                        $q->whereNull('body')->orWhere('body', '');
                    })
                    ->where(function ($q) {
                        $q->where('title', 'Untitled')->orWhereNull('title')->orWhere('title', '');
                    })
                    ->orderByDesc('id')
                    ->first();
            }

            if (!$draft) {
                $draft = $this->createFreshPipelineDraft();
            }

            return redirect()->route('publish.pipeline', ['id' => $draft->id]);
        }

        // Load existing draft
        $draftId = (int) $request->input('id');
        $draft = PublishArticle::with(['pipelineState', 'site', 'creator'])->find($draftId);
        if (!$draft) {
            return redirect()->route('publish.pipeline');
        }

        $sites = PublishSite::where('status', 'connected')
            ->orderBy('name')
            ->get(['id', 'name', 'url', 'status', 'default_author', 'is_press_release_source', 'last_connected_at', 'wp_username', 'connection_type', 'user_id']);
        $prSourceSites = $sites->where('is_press_release_source', true)->values();
        $draftSite = $draft->site;
        if ($draftSite && !$sites->contains('id', $draftSite->id)) {
            $sites->push($draftSite);
            $sites = $sites->sortBy('name')->values();
        }
        $newsCategories = $this->newsOptions->newsCategories();
        $aiCatalog = app(AiModelCatalog::class);

        $draftUserId = $draft->user_id ?: $draft->created_by;
        $draftUser = null;
        if ($draftUserId) {
            $draftUser = $draft->creator && (int) $draft->creator->id === (int) $draftUserId
                ? $draft->creator
                : User::find($draftUserId);
        }
        $initialUserPresets = $draftUserId ? $this->pipelineBootstrapPresetsForUser((int) $draftUserId) : [];
        $initialUserTemplates = $draftUserId ? $this->pipelineBootstrapTemplatesForUser((int) $draftUserId) : [];
        $pipelinePayload = $this->pipelineState->payload($draft);
        $bootArticleType = trim((string) (
            data_get($pipelinePayload, 'template_overrides.article_type')
            ?? data_get($pipelinePayload, 'article_type')
            ?? data_get($pipelinePayload, 'currentArticleType')
            ?? data_get($pipelinePayload, 'selectedTemplate.article_type')
            ?? $draft->article_type
            ?? 'editorial'
        )) ?: 'editorial';
        $bootSiteId = (string) (
            data_get($pipelinePayload, 'selectedSiteId')
            ?? data_get($pipelinePayload, 'selectedSite.id')
            ?? $draft->publish_site_id
            ?? ''
        );
        $bootSite = $bootSiteId !== ''
            ? ($sites->firstWhere('id', (int) $bootSiteId) ?: (($draftSite && (string) $draftSite->id === $bootSiteId) ? $draftSite : null))
            : $draftSite;
        if ($bootArticleType === 'press-release' && !($bootSite?->is_press_release_source)) {
            $fallbackPrSite = $prSourceSites->first();
            if ($fallbackPrSite) {
                $bootSite = $fallbackPrSite;
                $bootSiteId = (string) $fallbackPrSite->id;
                $pipelinePayload['selectedSiteId'] = $bootSiteId;
                $pipelinePayload['selectedSite'] = [
                    'id' => $fallbackPrSite->id,
                    'name' => $fallbackPrSite->name,
                    'url' => $fallbackPrSite->url,
                    'status' => $fallbackPrSite->status,
                    'default_author' => $fallbackPrSite->default_author,
                    'is_press_release_source' => (bool) $fallbackPrSite->is_press_release_source,
                    'wp_username' => $fallbackPrSite->wp_username,
                    'connection_type' => $fallbackPrSite->connection_type,
                ];
                $pipelinePayload = $this->pipelineState->clearPublishContextState($pipelinePayload, true, 'draft_wp');
            }
        }
        $canReuseExistingWpBinding = $draft->wp_post_id
            && (string) ($draft->publish_site_id ?: '') !== ''
            && (string) $draft->publish_site_id === $bootSiteId
            && !($bootArticleType === 'press-release' && !($bootSite?->is_press_release_source));
        if (!$canReuseExistingWpBinding) {
            $pipelinePayload = $this->pipelineState->clearPublishContextState(
                $pipelinePayload,
                true,
                $bootSiteId !== '' ? 'draft_wp' : 'draft_local'
            );
        }
        $latestCompletedPrepareHtml = PublishPipelineOperation::query()
            ->where('publish_article_id', $draft->id)
            ->where('operation_type', PublishPipelineOperation::TYPE_PREPARE)
            ->where('status', PublishPipelineOperation::STATUS_COMPLETED)
            ->latest('id')
            ->value('result_payload->html');
        $selectedBootSite = $bootSite ?: $draftSite;

        $draftState = [
            'article_type' => $draft->article_type ?: 'editorial',
            'selectedUser' => $draftUser ? [
                'id' => $draftUser->id,
                'name' => $draftUser->name,
                'email' => $draftUser->email,
            ] : null,
            'selectedPresetId' => $draft->preset_id ? (string) $draft->preset_id : '',
            'selectedTemplateId' => $draft->publish_template_id ? (string) $draft->publish_template_id : '',
            'selectedSiteId' => $selectedBootSite?->id ? (string) $selectedBootSite->id : '',
            'selectedSite' => $selectedBootSite ? [
                'id' => $selectedBootSite->id,
                'name' => $selectedBootSite->name,
                'url' => $selectedBootSite->url,
                'status' => $selectedBootSite->status,
                'default_author' => $selectedBootSite->default_author,
                'is_press_release_source' => (bool) $selectedBootSite->is_press_release_source,
                'wp_username' => $selectedBootSite->wp_username,
                'connection_type' => $selectedBootSite->connection_type,
            ] : null,
            'publishAuthor' => $draft->author ?: ($selectedBootSite?->default_author ?? ''),
            'siteConnStatus' => $selectedBootSite
                ? match ($selectedBootSite->status) {
                    'connected' => true,
                    'error' => false,
                    default => null,
                }
                : null,
            'articleTitle' => $draft->title ?: '',
            'articleDescription' => $draft->excerpt ?: '',
            'body' => $draft->body,
            'wordCount' => $draft->word_count ?: 0,
            'categories' => is_array($draft->categories) ? array_values(array_filter($draft->categories, fn ($value) => filled($value))) : [],
            'tags' => is_array($draft->tags) ? array_values(array_filter($draft->tags, fn ($value) => filled($value))) : [],
            'publishAction' => $draft->scheduled_for
                ? 'future'
                : match ($draft->delivery_mode) {
                    'auto-publish' => 'publish',
                    'draft-wordpress' => 'draft_wp',
                    default => 'draft_local',
                },
            'scheduleDate' => $draft->scheduled_for?->format('Y-m-d\TH:i') ?: '',
            'existingWpPostId' => $canReuseExistingWpBinding ? ($draft->wp_post_id ?: null) : null,
            'existingWpStatus' => $canReuseExistingWpBinding ? ($draft->wp_status ?: '') : '',
            'existingWpPostUrl' => $canReuseExistingWpBinding ? ($draft->wp_post_url ?: '') : '',
            'existingWpAdminUrl' => ($canReuseExistingWpBinding && $selectedBootSite && $draft->wp_post_id)
                ? rtrim((string) $selectedBootSite->url, '/') . '/wp-admin/post.php?post=' . $draft->wp_post_id . '&action=edit'
                : '',
            'photoSuggestions' => $draft->photo_suggestions ?? [],
            'featuredImageSearch' => $draft->featured_image_search ?: '',
            'aiModel' => $draft->ai_engine_used ?: '',
        ];

        // Resolve form definitions for pipeline context
        $formRegistry = app(\hexa_core\Forms\Services\FormRegistryService::class);
        $articlePresetForm = $formRegistry->resolve(
            \hexa_app_publish\Publishing\Templates\Forms\ArticlePresetForm::FORM_KEY,
            ['mode' => 'pipeline', 'context' => 'pipeline']
        );
        $wpPresetForm = $formRegistry->resolve(
            \hexa_app_publish\Publishing\Presets\Forms\WordPressPresetForm::FORM_KEY,
            ['mode' => 'pipeline', 'context' => 'pipeline']
        );

        // AI detection packages status for the detector panel
        $aiDetectors = [];
        $detectorMap = [
            'gptzero'     => ['class' => 'hexa_package_gptzero\\Services\\GptZeroService',     'name' => 'GPTZero',     'key' => 'gptzero_api_key'],
            'copyleaks'   => ['class' => 'hexa_package_copyleaks\\Services\\CopyleaksService', 'name' => 'Copyleaks',   'key' => 'copyleaks_api_key'],
            'originality' => ['class' => 'hexa_package_originality\\Services\\OriginalityService', 'name' => 'Originality.ai', 'key' => 'originality_api_key'],
            'sapling'     => ['class' => 'hexa_package_sapling\\Services\\SaplingService',     'name' => 'Sapling',     'key' => 'sapling_api_key'],
            'zerogpt'     => ['class' => 'hexa_package_zerogpt\\Services\\ZeroGptService',     'name' => 'ZeroGPT',     'key' => 'zerogpt_api_key'],
        ];
        foreach ($detectorMap as $key => $det) {
            $installed = class_exists($det['class']);
            $apiKey = Setting::getValue($det['key']);
            $aiDetectors[$key] = [
                'name'       => $det['name'],
                'installed'  => $installed,
                'enabled'    => $installed && Setting::getValue($key . '_enabled', '1') === '1',
                'has_key'    => !empty($apiKey),
                'debug_mode' => Setting::getValue($key . '_debug', '0') === '1',
            ];
        }

        return view('app-publish::publishing.pipeline.index', [
            'sites'             => $sites,
            'prSourceSites'     => $prSourceSites,
            'draftId'           => $draft->id,
            'newsCategories'    => $newsCategories,
            'draftUser'         => $draftUser,
            'draftState'        => $draftState,
            'templateSchema'    => \hexa_app_publish\Publishing\Templates\Models\PublishTemplate::getFieldSchema(),
            'presetSchema'      => \hexa_app_publish\Publishing\Presets\Models\PublishPreset::getFieldSchema(),
            'articlePresetForm' => $articlePresetForm,
            'wpPresetForm'      => $wpPresetForm,
            'articlePresetFields' => $this->formRuntime->clientPayload($articlePresetForm, 'pipeline', ['mode' => 'pipeline', 'context' => 'pipeline']),
            'wpPresetFields' => $this->formRuntime->clientPayload($wpPresetForm, 'pipeline', ['mode' => 'pipeline', 'context' => 'pipeline']),
            'filenamePattern'   => \hexa_core\Models\Setting::getValue('wp_photo_filename_pattern', 'hexa_{draft_id}_{seo_name}'),
            'aiDetectors'       => $aiDetectors,
            'aiModelGroups'     => $aiCatalog->groupedSelectOptions(),
            'aiSearchGroups'    => $aiCatalog->groupedSearchSelectOptions(),
            'aiSearchOptionLabels' => $aiCatalog->searchOptionLabels(),
            'pipelineDefaults'  => [
                'search_model' => $aiCatalog->defaultSearchSelection(),
                'spin_model' => $aiCatalog->defaultSpinModel(),
            ],
            'pipelinePayload'   => $pipelinePayload,
            'latestCompletedPrepareHtml' => $latestCompletedPrepareHtml ?: '',
            'initialUserPresets' => $initialUserPresets,
            'initialUserTemplates' => $initialUserTemplates,
            'workflowDefinitions' => $this->workflowRegistry->definitions(),
            'pressReleaseDefaultState' => app(\hexa_app_publish\Publishing\Pipeline\Services\PressReleaseWorkflowService::class)->defaultState(),
            'prArticleDefaultState' => app(PrArticleWorkflowService::class)->defaultState(),
        ]);
    }

    public function generatePhotoMeta(GeneratePhotoMetaRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $strategy = (string) Setting::getValue('publish_photo_meta_strategy', 'local_deterministic_first');
        $local = $this->generatePhotoMetaDeterministically($validated);

        if ($strategy === 'local_only') {
            return response()->json(array_merge(['success' => true, 'generator' => 'local', 'strategy' => $strategy], $local));
        }

        if ($strategy === 'local_deterministic_first' && $this->photoMetaPayloadIsUsable($local)) {
            return response()->json(array_merge(['success' => true, 'generator' => 'local', 'strategy' => $strategy], $local));
        }

        $ai = $this->generatePhotoMetaWithAi($validated);
        if ($ai['success']) {
            return response()->json(array_merge($ai, ['generator' => 'ai', 'strategy' => $strategy]));
        }

        if ($strategy !== 'ai_only' && $this->photoMetaPayloadIsUsable($local)) {
            return response()->json(array_merge([
                'success' => true,
                'generator' => 'local',
                'strategy' => $strategy,
                'fallback_message' => $ai['message'] ?? null,
            ], $local));
        }

        return response()->json(['success' => false, 'message' => $ai['message'] ?? 'AI call failed']);
    }

    public function previewPrompt(PreviewPromptRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $result = app(ArticleGenerationService::class)->buildPrompt(
            $validated['source_texts'] ?? ['[Source articles will be inserted here]'],
            $validated['template_id'] ?? null,
            $validated['preset_id'] ?? null,
            $validated['custom_prompt'] ?? null,
            null,
            $validated['pr_subject_context'] ?? null,
            true,
            $validated['prompt_slug'] ?? null,
            $validated['article_type'] ?? null
        );

        $prompt = $result['prompt'];
        if ($request->boolean('web_research', false)) {
            $prompt .= "\n\nWEB RESEARCH: Before writing, search the web for current data, statistics, expert opinions, and recent developments related to this topic. Incorporate real, verifiable facts and supporting points from your research into the article. Cite specific sources where possible.";
        }
        $supportingUrlInstruction = app(ArticleGenerationService::class)->supportingUrlTypeInstruction(
            $validated['supporting_url_type'] ?? 'matching_content_type'
        );
        if ($supportingUrlInstruction !== '') {
            $prompt .= "\n\n" . $supportingUrlInstruction;
            $result['log'][] = [
                'shortcode' => '(supporting url type)',
                'source' => 'Pipeline setting',
                'value' => $validated['supporting_url_type'] ?? 'matching_content_type',
            ];
        }

        return response()->json([
            'success' => true,
            'prompt'  => $prompt,
            'log'     => $result['log'],
        ]);
    }

    /**
     * Search users by name or email for type-ahead selectors.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function searchUsers(Request $request): JsonResponse
    {
        $query = $request->input('q', '');

        if (strlen($query) < 2) {
            return response()->json([]);
        }

        $users = User::where('name', 'like', "%{$query}%")
            ->orWhere('email', 'like', "%{$query}%")
            ->limit(15)
            ->get(['id', 'name', 'email']);

        return response()->json($users);
    }

    /**
     * Search local profiles for the PR subject picker.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function searchProfiles(Request $request): JsonResponse
    {
        $query = trim((string) $request->input('q', ''));
        $types = $request->input('types', $request->filled('type') ? [$request->input('type')] : ['person', 'company']);
        $types = collect(is_array($types) ? $types : [$types])
            ->map(function ($value) {
                $slug = Str::slug((string) $value);
                return $slug === 'company' ? 'organization' : $slug;
            })
            ->filter(fn ($value) => in_array($value, ['person', 'organization'], true))
            ->values()
            ->all();

        if ($types === []) {
            $types = ['person', 'organization'];
        }

        $notionImporterClass = 'hexa_package_notion\\Services\\NotionProfileImporter';
        $bridgeClass = 'hexa_package_notion\\Models\\NotionProfileBridge';
        $profileClass = 'hexa_package_profiles\\Models\\Profile';

        if (!class_exists($notionImporterClass) || !class_exists($bridgeClass) || !class_exists($profileClass)) {
            return $this->searchLocalProfilesFallback($query, $request->input('type'));
        }

        $bridges = $bridgeClass::query()
            ->with('profileType')
            ->where('is_active', true)
            ->get()
            ->keyBy('id');

        $profileRows = $profileClass::query()
            ->where('external_source', 'notion')
            ->whereNotNull('external_id')
            ->get(['id', 'name', 'slug', 'description', 'photo_url', 'profile_type_id', 'external_id'])
            ->keyBy(fn ($profile) => (string) $profile->external_id);

        $importer = app($notionImporterClass);
        $results = [];

        foreach ($types as $typeSlug) {
            foreach ($importer->search($query, $typeSlug) as $result) {
                $externalId = (string) ($result['external_id'] ?? '');
                if ($externalId === '' || isset($results[$externalId])) {
                    continue;
                }

                $bridge = $bridges->get((int) ($result['bridge_id'] ?? 0));
                $existing = $profileRows->get($externalId);
                $preview = is_array($result['preview'] ?? null) ? $result['preview'] : [];

                $results[$externalId] = [
                    'id' => $existing?->id,
                    'local_profile_id' => $existing?->id,
                    'bridge_id' => $bridge?->id,
                    'name' => (string) ($result['name'] ?? $existing?->name ?? 'Untitled'),
                    'slug' => $existing?->slug,
                    'description' => $existing?->description ?: $this->previewDescription($preview),
                    'photo_url' => $existing?->photo_url ?: '',
                    'type' => $bridge?->profileType?->name ?? Str::headline($typeSlug),
                    'type_slug' => $bridge?->profileType?->slug ?? $typeSlug,
                    'external_source' => 'notion',
                    'external_id' => $externalId,
                    'fields' => $preview,
                ];
            }
        }

        return response()->json(array_values(array_slice($results, 0, 20)));
    }

    public function resolveNotionSubject(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'bridge_id' => 'required|integer',
            'notion_page_id' => 'required|string',
            'name' => 'required|string|max:255',
        ]);

        $bridgeClass = 'hexa_package_notion\\Models\\NotionProfileBridge';
        $profileClass = 'hexa_package_profiles\\Models\\Profile';

        if (!class_exists($bridgeClass) || !class_exists($profileClass)) {
            return response()->json(['success' => false, 'message' => 'Notion profile bridge is unavailable.'], 422);
        }

        $bridge = $bridgeClass::query()->with('profileType')->find($validated['bridge_id']);
        if (!$bridge || !$bridge->is_active) {
            return response()->json(['success' => false, 'message' => 'Notion bridge not found.'], 404);
        }

        $profile = $profileClass::query()
            ->with(['type', 'customFields'])
            ->where('external_source', 'notion')
            ->where('external_id', $validated['notion_page_id'])
            ->first();

        if (!$profile) {
            $baseSlug = Str::slug($validated['name']) ?: 'notion-profile';
            $slug = $baseSlug;
            $suffix = 2;

            while ($profileClass::query()->where('slug', $slug)->exists()) {
                $slug = $baseSlug . '-' . $suffix;
                $suffix++;
            }

            $profile = $profileClass::query()->create([
                'name' => $validated['name'],
                'slug' => $slug,
                'profile_type_id' => $bridge->profile_type_id,
                'external_id' => $validated['notion_page_id'],
                'external_source' => 'notion',
                'is_active' => true,
                'created_by' => auth()->id(),
            ]);

            $profile->load(['type', 'customFields']);
        }

        return response()->json([
            'success' => true,
            'profile' => $this->formatLocalProfileSearchPayload($profile),
        ]);
    }

    protected function searchLocalProfilesFallback(string $query, ?string $type): JsonResponse
    {
        $profileClass = 'hexa_package_profiles\\Models\\Profile';
        if (!class_exists($profileClass)) {
            return response()->json([]);
        }

        if (strlen($query) < 1) {
            $profiles = $profileClass::with(['type', 'customFields'])
                ->where('is_active', true)
                ->when($type, fn ($q) => $q->whereHas('type', fn ($tq) => $tq->where('slug', $type)))
                ->orderBy('name')
                ->limit(20)
                ->get();
        } else {
            $serviceClass = 'hexa_package_profiles\\Services\\ProfileService';
            if (!class_exists($serviceClass)) {
                return response()->json([]);
            }
            $profiles = app($serviceClass)->searchProfiles($query, $type);
        }

        return response()->json($profiles->map(fn ($profile) => $this->formatLocalProfileSearchPayload($profile))->values());
    }

    protected function formatLocalProfileSearchPayload(object $profile): array
    {
        return [
            'id'              => $profile->id,
            'name'            => $profile->name,
            'slug'            => $profile->slug,
            'description'     => $profile->description,
            'photo_url'       => $profile->photo_url,
            'type'            => $profile->type?->name ?? '—',
            'type_slug'       => $profile->type?->slug ?? '',
            'external_source' => $profile->external_source ?? null,
            'external_id'     => $profile->external_id ?? null,
            'fields'          => method_exists($profile, 'customFields') ? $profile->customFields->pluck('field_value', 'field_key')->toArray() : [],
        ];
    }

    protected function previewDescription(array $preview): string
    {
        foreach ($preview as $value) {
            $text = trim((string) $value);
            if ($text === '' || Str::startsWith($text, ['http://', 'https://'])) {
                continue;
            }

            return Str::limit($text, 120);
        }

        return '';
    }

    /**
     * Check source URLs by extracting article content from each.
     *
     * Accepts an array of URLs, runs ArticleExtractorService::extract() on each,
     * and returns per-URL pass/fail with word count.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function checkSources(CheckSourcesRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $extraction = $this->sourceExtraction->extractMultiple($validated['urls'], [
            'method'        => $validated['method'] ?? 'auto',
            'user_agent'    => $validated['user_agent'] ?? 'chrome',
            'retries'       => $validated['retries'] ?? 1,
            'timeout'       => $validated['timeout'] ?? 20,
            'min_words'     => $validated['min_words'] ?? 50,
            'auto_fallback' => $validated['auto_fallback'] ?? true,
        ]);

        return response()->json([
            'success' => true,
            'message' => "{$extraction['pass_count']} of {$extraction['total']} sources verified.",
            'results' => $extraction['results'],
        ]);
    }

    public function checkLinkStatus(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'url' => 'required|string|max:2000',
        ]);

        $status = app(LinkHealthService::class)->probe((string) $validated['url'], 'pipeline');

        return response()->json([
            'success' => !$status['probe_failed'],
            'message' => $status['status_text'],
            'data' => $status,
        ], $status['probe_failed'] ? 422 : 200);
    }

    /**
     * AI Article Search — use the selected provider to find recent articles on a topic.
     */
    public function aiSearchArticles(Request $request): JsonResponse
    {
        $request->validate([
            'topic' => 'required|string|min:3|max:500',
            'count' => 'nullable|integer|min:2|max:10',
            'model' => 'nullable|string|max:100',
            'draft_id' => 'nullable|integer|exists:publish_articles,id',
            'exclude_urls' => 'nullable|array|max:50',
            'exclude_urls.*' => 'string|max:2000',
        ]);

        $catalog = app(AiModelCatalog::class);
        $selection = (string) $request->input('model', ($catalog->defaultSearchSelection() ?: ''));
        $requestedSelection = $selection;
        $resolvedSelection = $catalog->resolveSearchSelection($selection);
        $model = (string) ($resolvedSelection['model'] ?? '');
        $topic = $request->input('topic');
        $count = min((int) $request->input('count', 10), 10);
        $provider = $resolvedSelection['provider'] ?? null;
        $excludeUrls = array_filter((array) $request->input('exclude_urls', []));
        $draft = $request->filled('draft_id')
            ? $this->resolveAuthorizedDraft((int) $request->input('draft_id'))
            : null;

        $searchPrompt = "Search the web for {$count} recent news articles about: {$topic}. "
            . "Return only LIVE, canonical article pages from reputable publishers. "
            . "Do NOT guess URL slugs. Do NOT return homepages, search pages, tag pages, category pages, topic pages, author pages, archive pages, AMP pages, cached pages, redirect links, or Google/AI intermediary links. "
            . "If you are not confident a direct article URL currently resolves, omit it. "
            . "For each article return the exact canonical URL, the article title, and a brief description under 20 words. "
            . "Return ONLY a JSON array of objects with keys: url, title, description. No other text.";

        if (!empty($excludeUrls)) {
            $excludeList = implode("\n", array_slice($excludeUrls, 0, 20));
            $searchPrompt .= "\n\nDo NOT include any of these URLs or articles from the same pages — they were already found:\n{$excludeList}";
        }

        $searchService = app(\hexa_app_publish\Discovery\Sources\Services\AiOptimizedArticleSearchService::class);
        $result = $searchService->search($topic, $count, $selection);
        $aiFallbackUsed = false;
        $aiFallbackFrom = null;

        if (!(bool) ($result['success'] ?? false) || empty((array) data_get($result, 'data.articles', []))) {
            foreach (['optimized:grok', 'optimized:openai', 'grok-3-mini', 'gpt-4o-mini'] as $fallbackSelection) {
                if ($fallbackSelection === $selection) {
                    continue;
                }

                $candidateResult = $searchService->search($topic, $count, $fallbackSelection);
                if ((bool) ($candidateResult['success'] ?? false) && !empty((array) data_get($candidateResult, 'data.articles', []))) {
                    $aiFallbackUsed = true;
                    $aiFallbackFrom = $selection;
                    $selection = $fallbackSelection;
                    $resolvedSelection = $catalog->resolveSearchSelection($selection);
                    $provider = $resolvedSelection['provider'] ?? $provider;
                    $model = (string) ($resolvedSelection['model'] ?? $model);
                    $result = $candidateResult;
                    break;
                }
            }
        }

        if ($result['success'] && !empty($result['data']['usage'])) {
            $usedModel = $result['data']['model'] ?? $model;
            if ($usedModel) {
                $result['data']['cost'] = round($catalog->calculateCost($usedModel, (array) $result['data']['usage']), 6);
            }
        }

        $verifiedPrimary = $this->verifyArticleCandidates((array) data_get($result, 'data.articles', []), $count, [], $topic);
        $articles = $verifiedPrimary['articles'];

        if (empty($articles) && !$aiFallbackUsed) {
            foreach (['optimized:grok', 'optimized:openai', 'grok-3-mini', 'gpt-4o-mini'] as $fallbackSelection) {
                if ($fallbackSelection === $selection) {
                    continue;
                }

                $candidateResult = $searchService->search($topic, $count, $fallbackSelection);
                if (!(bool) ($candidateResult['success'] ?? false) || empty((array) data_get($candidateResult, 'data.articles', []))) {
                    continue;
                }

                $candidateVerified = $this->verifyArticleCandidates((array) data_get($candidateResult, 'data.articles', []), $count, [], $topic);
                if (empty($candidateVerified['articles'])) {
                    continue;
                }

                $aiFallbackUsed = true;
                $aiFallbackFrom = $requestedSelection;
                $selection = $fallbackSelection;
                $resolvedSelection = $catalog->resolveSearchSelection($selection);
                $provider = $resolvedSelection['provider'] ?? $provider;
                $model = (string) ($resolvedSelection['model'] ?? $model);
                $result = $candidateResult;
                $verifiedPrimary = $candidateVerified;
                $articles = $candidateVerified['articles'];

                if ($result['success'] && !empty($result['data']['usage'])) {
                    $usedModel = $result['data']['model'] ?? $model;
                    if ($usedModel) {
                        $result['data']['cost'] = round($catalog->calculateCost($usedModel, (array) $result['data']['usage']), 6);
                    }
                }

                break;
            }
        }

        $fallbackStats = ['checked' => 0, 'kept' => 0, 'discarded' => 0];
        $fallbackResult = null;
        $fallbackUsed = false;

        if (count($articles) < $count) {
            $fallbackReason = (!$result['success'] || empty(data_get($result, 'data.articles')))
                ? (string) ($result['message'] ?? 'AI article search failed.')
                : 'AI article search returned dead, duplicate, or non-canonical URLs.';

            $fallbackResult = $this->fallbackArticleSearch($topic, (int) $count, $fallbackReason);

            if ($fallbackResult['success']) {
                $verifiedFallback = $this->verifyArticleCandidates(
                    (array) data_get($fallbackResult, 'data.articles', []),
                    $count - count($articles),
                    array_column($articles, 'url'),
                    $topic
                );

                $fallbackStats = $verifiedFallback['stats'];
                if (!empty($verifiedFallback['articles'])) {
                    $articles = array_merge($articles, $verifiedFallback['articles']);
                    $fallbackUsed = true;
                }
            }
        }

        if (empty($articles)) {
            $this->articleActivity->record($draft, [
                'activity_group' => 'search:' . md5($topic),
                'activity_type' => 'search',
                'stage' => 'discovery',
                'substage' => 'failed',
                'status' => 'failed',
                'provider' => $provider,
                'model' => $model ?: $selection,
                'agent' => 'pipeline-search',
                'method' => 'ai_search_articles',
                'success' => false,
                'message' => (string) (($fallbackResult['message'] ?? null) ?: ($result['message'] ?? 'No live articles found.')),
                'request_payload' => [
                    'topic' => $topic,
                    'count' => $count,
                    'selection' => $selection,
                    'exclude_urls' => array_values($excludeUrls),
                    'prompt' => $searchPrompt,
                ],
                'response_payload' => [
                    'verification' => [
                        'primary' => $verifiedPrimary['stats'],
                        'fallback' => $fallbackStats,
                    ],
                    'backend' => data_get($result, 'data.search_backend', $provider),
                ],
            ]);
            $failureMessage = 'Search found candidates, but none verified as live, on-topic article URLs.';
            if (!$result['success']) {
                $failureMessage = (string) ($result['message'] ?? $failureMessage);
            }
            if ($fallbackResult && (bool) ($fallbackResult['success'] ?? false)) {
                $failureMessage .= ' Deterministic news fallback also found candidates, but none survived live verification.';
            } elseif ($fallbackResult) {
                $failureMessage = (string) (($fallbackResult['message'] ?? null) ?: $failureMessage);
            }

            return response()->json([
                'success' => false,
                'message' => $failureMessage,
                'data' => [
                    'verification' => [
                        'primary' => $verifiedPrimary['stats'],
                        'fallback' => $fallbackStats,
                    ],
                ],
            ]);
        }

        $backendLabels = array_values(array_filter([
            data_get($result, 'data.search_backend_label') ?: ucfirst($provider),
            $fallbackUsed ? (data_get($fallbackResult, 'data.search_backend_label') ?: 'News providers') : null,
        ]));

        $this->articleActivity->record($draft, [
            'activity_group' => 'search:' . md5($topic),
            'activity_type' => 'search',
            'stage' => 'discovery',
            'substage' => $fallbackUsed ? 'complete_with_fallback' : 'complete',
            'status' => 'success',
            'provider' => $provider,
            'model' => $model ?: $selection,
            'agent' => 'pipeline-search',
            'method' => 'ai_search_articles',
            'success' => true,
            'message' => count($articles) . ' live article(s) verified.',
            'request_payload' => [
                'topic' => $topic,
                'count' => $count,
                'exclude_urls' => array_values($excludeUrls),
                'prompt' => $searchPrompt,
            ],
            'response_payload' => [
                'articles' => array_slice($articles, 0, $count),
                'search_backend' => $fallbackUsed ? 'hybrid_live_verification' : (data_get($result, 'data.search_backend') ?: $provider),
                'search_backend_label' => !empty($backendLabels) ? implode(' + ', $backendLabels) : 'Live article verification',
                'fallback_reason' => $fallbackUsed ? data_get($fallbackResult, 'data.fallback_reason') : null,
                'ai_fallback_used' => $aiFallbackUsed,
                'ai_fallback_from' => $aiFallbackFrom,
                'verification' => [
                    'primary' => $verifiedPrimary['stats'],
                    'fallback' => $fallbackStats,
                ],
            ],
        ]);

        return response()->json([
            'success' => true,
            'message' => count($articles) . ' live article(s) verified.',
            'data' => [
                'articles' => array_slice($articles, 0, $count),
                'model' => data_get($result, 'data.model', $model),
                'usage' => (array) data_get($result, 'data.usage', []),
                'cost' => data_get($result, 'data.cost'),
                'search_backend' => $fallbackUsed ? 'hybrid_live_verification' : (data_get($result, 'data.search_backend') ?: $provider),
                'search_backend_label' => !empty($backendLabels) ? implode(' + ', $backendLabels) : 'Live article verification',
                'fallback_reason' => $fallbackUsed ? data_get($fallbackResult, 'data.fallback_reason') : null,
                'ai_fallback_used' => $aiFallbackUsed,
                'ai_fallback_from' => $aiFallbackFrom,
                'verification' => [
                    'primary' => $verifiedPrimary['stats'],
                    'fallback' => $fallbackStats,
                ],
            ],
        ]);
    }

    /**
     * Deterministic fallback for article discovery using configured news providers.
     *
     * @return array{success: bool, message: string, data: array|null}
     */
    private function fallbackArticleSearch(string $topic, int $count, string $reason): array
    {
        $fallback = app(SourceDiscoveryService::class)->searchArticles($topic, [
            'per_page' => max(4, min(12, $count * 2)),
            'sources' => ['google-news-rss', 'gnews', 'newsdata', 'currents_news'],
        ]);

        if (!(bool) data_get($fallback, 'success', false) || empty(data_get($fallback, 'data.articles', []))) {
            $errors = (array) data_get($fallback, 'data.errors', []);
            $suffix = !empty($errors) ? ' Fallback search errors: ' . implode(' | ', $errors) : '';

            return [
                'success' => false,
                'message' => $reason . $suffix,
                'data' => null,
            ];
        }

        $articles = array_slice((array) ($fallback['data']['articles'] ?? []), 0, $count);
        $providers = array_keys(array_filter((array) ($fallback['data']['totals'] ?? [])));

        return [
            'success' => true,
            'message' => count($articles) . ' article(s) found via reliable news search fallback.',
            'data' => [
                'articles' => $articles,
                'search_backend' => 'deterministic_news_fallback',
                'search_backend_label' => $this->formatSearchProviderLabels($providers),
                'fallback_reason' => $reason,
                'fallback_from_model' => null,
            ],
        ];
    }

    /**
     * @param array<int, string> $providers
     */
    private function formatSearchProviderLabels(array $providers): string
    {
        $labels = [
            'google-news-rss' => 'Google News RSS',
            'gnews' => 'GNews',
            'newsdata' => 'NewsData',
            'currents_news' => 'Currents',
        ];

        $mapped = array_values(array_filter(array_map(static fn (string $provider): ?string => $labels[$provider] ?? null, $providers)));

        return empty($mapped) ? 'News providers' : implode(', ', $mapped);
    }

    /**
     * @param array<int, mixed> $articles
     * @param array<int, string> $excludeUrls
     * @return array{articles: array<int, array<string, mixed>>, stats: array{checked: int, kept: int, discarded: int}}
     */
    private function verifyArticleCandidates(array $articles, int $limit, array $excludeUrls = [], ?string $topic = null): array
    {
        return app(LinkHealthService::class)->verifyArticleCandidates(
            $articles,
            $limit,
            $excludeUrls,
            fn (array $candidate): bool => $this->matchesSearchTopic($candidate, $topic)
                && !app(SourceAccessStrategyService::class)->shouldBlockDiscoveryCandidate((string) ($candidate['url'] ?? '')),
            'pipeline'
        );
    }

    /**
     * @param array<string, mixed> $candidate
     */
    private function matchesSearchTopic(array $candidate, ?string $topic): bool
    {
        if ($this->isLowValueSearchCandidate($candidate)) {
            return false;
        }

        if (!$topic) {
            return true;
        }

        $requiredTerms = array_slice($this->searchTopicTokens($topic), 0, 3);
        $minimumRequiredHits = count($requiredTerms) >= 3 ? 2 : 1;
        $textTokens = $this->searchTopicTokens(trim((string) (($candidate['title'] ?? '') . ' ' . ($candidate['description'] ?? ''))));
        $topicHits = count(array_intersect($textTokens, $this->searchTopicTokens($topic)));
        $requiredHits = count(array_intersect($textTokens, $requiredTerms));

        if ($requiredTerms !== [] && $requiredHits < $minimumRequiredHits) {
            return false;
        }

        return $topicHits >= $minimumRequiredHits || $requiredHits >= $minimumRequiredHits;
    }

    /**
     * @param array<string, mixed> $candidate
     */
    private function isLowValueSearchCandidate(array $candidate): bool
    {
        $text = Str::lower(trim((string) (($candidate['title'] ?? '') . ' ' . ($candidate['description'] ?? '') . ' ' . ($candidate['url'] ?? ''))));

        return (bool) preg_match('/(\btop\s+\d+\b|\bpower\s+list\b|\bblog\s+posts\b|\bhow\s+to\b|\bguide\b|\btips\b|\broundup\b|\bsponsored\b|\badvertorial\b|\baward\b|\bawards\b|\/awards?\/|\/lists?\/)/', $text);
    }

    /**
     * @return array<int, string>
     */
    private function searchTopicTokens(string $text): array
    {
        $text = Str::lower($text);
        $text = preg_replace('/[^a-z0-9\s]+/', ' ', $text);
        $parts = preg_split('/\s+/', (string) $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $stopWords = [
            'about', 'after', 'amid', 'analysis', 'article', 'articles', 'because', 'before', 'between', 'breaking',
            'commentary', 'could', 'daily', 'editorial', 'feature', 'from', 'have', 'into', 'latest', 'more', 'most',
            'news', 'over', 'reuters', 'says', 'show', 'story', 'than', 'that', 'their', 'them', 'these', 'they',
            'this', 'those', 'today', 'under', 'update', 'updates', 'what', 'when', 'where', 'which', 'while', 'with',
        ];

        return array_values(array_unique(array_filter($parts, static function ($part) use ($stopWords) {
            return strlen($part) >= 3 && !in_array($part, $stopWords, true);
        })));
    }

    /**
     * @param mixed $article
     * @return array<string, mixed>|null
     */
    private function normalizeArticleCandidate(mixed $article): ?array
    {
        if (!is_array($article)) {
            return null;
        }

        $url = $this->normalizeArticleUrlCandidate((string) ($article['url'] ?? ''));
        if (!$url || !$this->looksLikeCanonicalArticleUrl($url)) {
            return null;
        }

        return [
            'url' => $url,
            'title' => trim((string) ($article['title'] ?? $url)),
            'description' => trim((string) ($article['description'] ?? '')),
        ];
    }

    /**
     * @return array{url: string, status_code: int|null, status_text: string, status_tone: string, checked_via: string, final_url: string, is_broken: bool, probe_failed: bool}
     */
    private function probeArticleUrl(string $url): array
    {
        $normalized = $this->normalizeArticleUrlCandidate($url);
        if (!$normalized) {
            return [
                'url' => $url,
                'status_code' => null,
                'status_text' => 'Invalid URL',
                'status_tone' => 'red',
                'checked_via' => 'validation',
                'final_url' => '',
                'is_broken' => true,
                'probe_failed' => true,
            ];
        }

        $cacheKey = 'publish:link-status:' . md5($normalized);
        $cached = Cache::get($cacheKey);
        if (is_array($cached)) {
            return $cached;
        }

        $result = $this->probeArticleUrlUncached($normalized);
        if (!$result['probe_failed']) {
            Cache::put($cacheKey, $result, now()->addMinutes(15));
        }

        return $result;
    }

    /**
     * @return array{url: string, status_code: int|null, status_text: string, status_tone: string, checked_via: string, final_url: string, is_broken: bool, probe_failed: bool}
     */
    private function probeArticleUrlUncached(string $url): array
    {
        if (!$this->looksLikeCanonicalArticleUrl($url)) {
            return [
                'url' => $url,
                'status_code' => null,
                'status_text' => 'Non-article URL',
                'status_tone' => 'red',
                'checked_via' => 'validation',
                'final_url' => $url,
                'is_broken' => true,
                'probe_failed' => true,
            ];
        }

        try {
            $response = Http::withHeaders([
                'User-Agent' => 'Hexa Publish Link Checker/1.0',
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            ])
                ->connectTimeout(5)
                ->timeout(10)
                ->withOptions([
                    'allow_redirects' => ['max' => 5, 'track_redirects' => true],
                    'http_errors' => false,
                ])
                ->head($url);

            $checkedVia = 'HEAD';
            $statusCode = $response->status();

            if ($this->shouldRetryWithGet($statusCode)) {
                $response = Http::withHeaders([
                    'User-Agent' => 'Hexa Publish Link Checker/1.0',
                    'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                ])
                    ->connectTimeout(5)
                    ->timeout(10)
                    ->withOptions([
                        'allow_redirects' => ['max' => 5, 'track_redirects' => true],
                        'http_errors' => false,
                    ])
                    ->get($url);
                $checkedVia = 'GET';
                $statusCode = $response->status();
            }

            $finalUrl = $this->resolveFinalUrlFromResponse($url, $response);
            $isBroken = !($statusCode >= 200 && $statusCode < 400);
            $statusText = $this->articleStatusLabel($statusCode);

            if (!$isBroken && !$this->looksLikeCanonicalArticleUrl($finalUrl)) {
                $isBroken = true;
                $statusText = 'Redirected to non-article page';
            }

            return [
                'url' => $url,
                'status_code' => $statusCode,
                'status_text' => $statusText,
                'status_tone' => $this->articleStatusTone($statusCode, $isBroken),
                'checked_via' => $checkedVia,
                'final_url' => $finalUrl,
                'is_broken' => $isBroken,
                'probe_failed' => false,
            ];
        } catch (\Throwable $e) {
            return [
                'url' => $url,
                'status_code' => null,
                'status_text' => 'Check failed: ' . Str::limit($e->getMessage(), 120, ''),
                'status_tone' => 'amber',
                'checked_via' => 'error',
                'final_url' => $url,
                'is_broken' => false,
                'probe_failed' => true,
            ];
        }
    }

    private function shouldRetryWithGet(?int $statusCode): bool
    {
        return in_array((int) $statusCode, [0, 403, 405, 406], true);
    }

    private function resolveFinalUrlFromResponse(string $url, $response): string
    {
        $history = $response->header('X-Guzzle-Redirect-History');

        if (is_array($history) && !empty($history)) {
            $last = end($history);
            return is_string($last) && $last !== '' ? $last : $url;
        }

        if (is_string($history) && trim($history) !== '') {
            $parts = array_values(array_filter(array_map('trim', explode(',', $history))));
            if (!empty($parts)) {
                return $parts[count($parts) - 1];
            }
        }

        return $url;
    }

    private function normalizeArticleUrlCandidate(string $url): ?string
    {
        $url = html_entity_decode(trim($url), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $url = trim($url, " \t\n\r\0\x0B<>\"'");
        $url = rtrim($url, '.,;)]}');

        if (preg_match('/^www\./i', $url)) {
            $url = 'https://' . $url;
        }

        if ($url === '' || !filter_var($url, FILTER_VALIDATE_URL)) {
            return null;
        }

        $parts = parse_url($url);
        if (!is_array($parts)) {
            return null;
        }

        $scheme = Str::lower((string) ($parts['scheme'] ?? ''));
        if (!in_array($scheme, ['http', 'https'], true)) {
            return null;
        }

        return $url;
    }

    private function looksLikeCanonicalArticleUrl(string $url): bool
    {
        $parts = parse_url($url);
        if (!is_array($parts)) {
            return false;
        }

        $host = Str::lower((string) ($parts['host'] ?? ''));
        if ($host === '') {
            return false;
        }

        foreach ([
            'google.com',
            'www.google.com',
            'news.google.com',
            'webcache.googleusercontent.com',
            'vertexaisearch.cloud.google.com',
        ] as $blockedHost) {
            if ($host === $blockedHost || Str::endsWith($host, '.' . $blockedHost)) {
                return false;
            }
        }

        $path = trim((string) ($parts['path'] ?? ''), '/');
        if ($path === '') {
            return false;
        }

        $segments = array_values(array_filter(explode('/', Str::lower($path))));
        $first = $segments[0] ?? '';

        if (in_array($first, ['search', 'tag', 'tags', 'category', 'categories', 'topic', 'topics', 'author', 'authors', 'archive', 'archives'], true)) {
            return false;
        }

        if (count($segments) === 1 && in_array($first, ['news', 'latest', 'live', 'video', 'videos', 'photos'], true)) {
            return false;
        }

        parse_str((string) ($parts['query'] ?? ''), $query);
        if (isset($query['s']) || isset($query['search'])) {
            return false;
        }

        return true;
    }

    private function articleStatusTone(?int $statusCode, bool $isBroken): string
    {
        if (!$isBroken && $statusCode !== null && $statusCode >= 200 && $statusCode < 400) {
            return 'green';
        }

        if ($statusCode !== null && in_array($statusCode, [403, 405, 406, 429, 500, 502, 503, 504], true)) {
            return 'amber';
        }

        return 'red';
    }

    private function articleStatusLabel(?int $statusCode): string
    {
        return match ((int) $statusCode) {
            200 => '200 OK',
            301 => '301 Redirect',
            302 => '302 Redirect',
            307 => '307 Redirect',
            308 => '308 Redirect',
            403 => '403 Forbidden',
            404 => '404 Not Found',
            405 => '405 Method Not Allowed',
            406 => '406 Not Acceptable',
            410 => '410 Gone',
            429 => '429 Rate Limited',
            500 => '500 Server Error',
            502 => '502 Bad Gateway',
            503 => '503 Service Unavailable',
            504 => '504 Gateway Timeout',
            default => $statusCode ? ($statusCode . ' Response') : 'No response',
        };
    }

    /**
     * Parse a raw chat response into the article search result format.
     */
    private function parseSearchResult(array $raw, string $model): array
    {
        if (!$raw['success']) {
            return ['success' => false, 'message' => $raw['message'] ?? 'AI call failed', 'data' => null];
        }

        $content = $raw['data']['content'] ?? '';
        // Strip markdown code fences if present
        $content = preg_replace('/^```(?:json)?\s*/i', '', trim($content));
        $content = preg_replace('/\s*```$/', '', $content);

        $articles = json_decode($content, true);
        if (!is_array($articles)) {
            return ['success' => false, 'message' => 'Could not parse article results from AI response.', 'data' => null];
        }

        return [
            'success' => true,
            'data' => [
                'articles' => $articles,
                'model' => $raw['data']['model'] ?? $model,
                'usage' => $raw['data']['usage'] ?? [],
            ],
        ];
    }

    /**
     * Spin article content using AI.
     *
     * Builds a full prompt stack from master settings + preset config + prompt template + source texts,
     * then calls AnthropicService::chat().
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function spin(SpinRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $startedAt = microtime(true);

        $result = app(ArticleGenerationService::class)->generate(
            $validated['source_texts'],
            [
                'article_id'         => $validated['draft_id'] ?? null,
                'model'              => $validated['model'],
                'template_id'        => $validated['template_id'] ?? null,
                'preset_id'          => $validated['preset_id'] ?? null,
                'prompt_slug'        => $validated['prompt_slug'] ?? null,
                'custom_prompt'      => $validated['custom_prompt'] ?? null,
                'supporting_url_type'=> $validated['supporting_url_type'] ?? 'matching_content_type',
                'change_request'     => $validated['change_request'] ?? null,
                'pr_subject_context' => $validated['pr_subject_context'] ?? null,
                'article_type'       => $validated['article_type'] ?? null,
                'web_research'       => $request->boolean('web_research', false),
                'agent'              => !empty($validated['change_request']) ? 'pipeline-revise' : 'pipeline-spin',
            ]
        );

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['message'],
                'diagnostics' => array_merge((array) ($result['diagnostics'] ?? []), [
                    'controller_total_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                ]),
                'attempted_models' => $result['attempted_models'] ?? [],
                'timestamp_utc' => now()->utc()->format('Y-m-d H:i:s'),
            ]);
        }

        return response()->json(array_merge($result, [
            'user_name'     => auth()->user()?->name ?? 'System',
            'ip'            => request()->ip(),
            'timestamp_utc' => now()->utc()->format('Y-m-d H:i:s'),
            'diagnostics'   => array_merge((array) ($result['diagnostics'] ?? []), [
                'controller_total_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            ]),
        ]));
    }

    /**
     * Generate article metadata: 10 title options, 10 categories, 10 tags.
     * Uses Haiku for speed and cost efficiency.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function generateMetadata(GenerateMetadataRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $draft = !empty($validated['draft_id'])
            ? $this->resolveAuthorizedDraft((int) $validated['draft_id'])
            : null;

        $result = app(MetadataGenerationService::class)->generate($validated['article_html'], 'claude-haiku-4-5-20251001', $draft?->id);

        return response()->json($result);
    }

    /**
     * Prepare content for WordPress: upload images, create categories/tags, validate HTML.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function prepareForWordpress(PrepareForWordpressRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $draft = $this->resolveAuthorizedDraft((int) $validated['draft_id']);
        $site = PublishSite::findOrFail($validated['site_id']);
        $operationService = app(PipelineOperationService::class);
        $active = $operationService->activeForArticle($draft, PublishPipelineOperation::TYPE_PREPARE);
        if ($active) {
            return response()->json([
                'success' => true,
                'message' => 'A prepare operation is already in progress for this draft.',
                'operation' => $this->serializePipelineOperation($active),
            ], 202);
        }

        $strategy = $operationService->detectExecutionStrategy();
        $clientTrace = $this->pipelineClientTrace($request) ?: ('prepare-' . $draft->id . '-' . Str::lower(Str::random(8)));
        $traceId = (string) Str::uuid();
        $requestSummary = [
            'trace_id' => $traceId,
            'client_trace' => $clientTrace,
            'debug_mode' => $this->pipelineDebugEnabled($request),
            'user_id' => auth()->id(),
            'site_id' => $site->id,
            'site_name' => $site->name,
            'draft_id' => (int) ($validated['draft_id'] ?? 0),
            'image_count' => substr_count($validated['html'] ?? '', '<img'),
            'category_count' => count($validated['categories'] ?? []),
            'tag_count' => count($validated['tags'] ?? []),
            'has_featured' => !empty($validated['featured_url']),
            'transport' => $strategy['transport'],
        ];

        $operation = $operationService->create($draft, PublishPipelineOperation::TYPE_PREPARE, $requestSummary, [
            'publish_site_id' => $site->id,
            'created_by' => auth()->id(),
            'workflow_type' => $validated['article_type'] ?? ($draft->article_type ?: null),
            'transport' => $strategy['transport'],
            'queue_connection' => $strategy['queue_connection'],
            'queue_name' => $strategy['queue_name'],
            'client_trace' => $clientTrace,
            'trace_id' => $traceId,
            'debug_enabled' => $this->pipelineDebugEnabled($request),
        ]);

        $payload = array_merge($validated, [
            'client_trace' => $clientTrace,
            'trace_id' => $traceId,
            'debug_mode' => $this->pipelineDebugEnabled($request),
            'user_id' => auth()->id(),
            'user_ip' => $request->ip(),
        ]);

        $this->dispatchPipelineOperation($strategy, $operation, $payload, PublishPipelineOperation::TYPE_PREPARE);

        return response()->json([
            'success' => true,
            'message' => $strategy['transport'] === 'sync'
                ? 'Prepare operation completed.'
                : 'Prepare operation started.',
            'operation' => $this->serializePipelineOperation($operation->fresh()),
        ], $strategy['transport'] === 'sync' ? 200 : 202);
    }

    /**
     * Delete orphaned media from WordPress.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function deleteMedia(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'site_id' => 'required|integer|exists:publish_sites,id',
            'media_id' => 'required|integer',
        ]);

        $site = PublishSite::findOrFail($validated['site_id']);
        $mediaId = (int) $validated['media_id'];

        if ($site->connection_type === 'wptoolkit') {
            $account = \hexa_package_whm\Models\HostingAccount::find($site->hosting_account_id);
            $server = $account ? \hexa_package_whm\Models\WhmServer::find($account->whm_server_id) : null;
            if ($server && $site->wordpress_install_id) {
                $wptoolkit = app(\hexa_package_wptoolkit\Services\WpToolkitService::class);
                $result = $wptoolkit->wpCliDeleteMedia($server, (int) $site->wordpress_install_id, $mediaId);
                if ($result['success']) {
                    return response()->json(['success' => true, 'message' => "Media #{$mediaId} deleted"]);
                }
                return response()->json(['success' => false, 'message' => $result['message'] ?? 'WP Toolkit connection failed']);
            }
            return response()->json(['success' => false, 'message' => 'WP Toolkit connection failed']);
        }

        // REST mode
        try {
            $resp = \Illuminate\Support\Facades\Http::withBasicAuth($site->wp_username, $site->wp_application_password)
                ->timeout(15)
                ->delete(rtrim($site->url, '/') . '/wp-json/wp/v2/media/' . $mediaId . '?force=true');
            return response()->json(['success' => $resp->successful(), 'message' => $resp->successful() ? "Media #{$mediaId} deleted" : 'Delete failed']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    /**
     * Publish article to WordPress.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function publishToWordpress(PublishToWordpressRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $draft = $this->resolveAuthorizedDraft((int) $validated['draft_id']);
        $site = PublishSite::findOrFail($validated['site_id']);
        $operationService = app(PipelineOperationService::class);
        $active = $operationService->activeForArticle($draft, PublishPipelineOperation::TYPE_PUBLISH);
        if ($active) {
            return response()->json([
                'success' => true,
                'message' => 'A publish operation is already in progress for this draft.',
                'operation' => $this->serializePipelineOperation($active),
            ], 202);
        }

        $activePrepare = $operationService->activeForArticle($draft, PublishPipelineOperation::TYPE_PREPARE);
        if ($activePrepare) {
            return response()->json([
                'success' => false,
                'message' => 'Prepare is still running for this draft. Wait for it to finish before publishing.',
                'operation' => $this->serializePipelineOperation($activePrepare),
            ], 409);
        }

        $strategy = $operationService->detectExecutionStrategy();
        $clientTrace = $this->pipelineClientTrace($request) ?: ('publish-' . $draft->id . '-' . Str::lower(Str::random(8)));
        $traceId = (string) Str::uuid();
        $requestSummary = [
            'trace_id' => $traceId,
            'client_trace' => $clientTrace,
            'debug_mode' => $this->pipelineDebugEnabled($request),
            'user_id' => auth()->id(),
            'draft_id' => (int) ($validated['draft_id'] ?? 0),
            'site_id' => $site->id,
            'site_name' => $site->name,
            'status' => $validated['status'],
            'category_count' => count($validated['category_ids'] ?? []),
            'tag_count' => count($validated['tag_ids'] ?? []),
            'wp_image_count' => count($validated['wp_images'] ?? []),
            'has_featured' => !empty($validated['featured_media_id']),
            'transport' => $strategy['transport'],
        ];

        $operation = $operationService->create($draft, PublishPipelineOperation::TYPE_PUBLISH, $requestSummary, [
            'publish_site_id' => $site->id,
            'created_by' => auth()->id(),
            'workflow_type' => $validated['article_type'] ?? ($draft->article_type ?: null),
            'transport' => $strategy['transport'],
            'queue_connection' => $strategy['queue_connection'],
            'queue_name' => $strategy['queue_name'],
            'client_trace' => $clientTrace,
            'trace_id' => $traceId,
            'debug_enabled' => $this->pipelineDebugEnabled($request),
        ]);

        $payload = array_merge($validated, [
            'client_trace' => $clientTrace,
            'trace_id' => $traceId,
            'debug_mode' => $this->pipelineDebugEnabled($request),
            'user_id' => auth()->id(),
            'user_ip' => $request->ip(),
        ]);

        $this->dispatchPipelineOperation($strategy, $operation, $payload, PublishPipelineOperation::TYPE_PUBLISH);

        return response()->json([
            'success' => true,
            'message' => $strategy['transport'] === 'sync'
                ? 'Publish operation completed.'
                : 'Publish operation started.',
            'operation' => $this->serializePipelineOperation($operation->fresh()),
        ], $strategy['transport'] === 'sync' ? 200 : 202);
    }

    /**
     * Save current pipeline state as a draft article.
     *
     * @param Request $request
     * @return JsonResponse
     */
    /**
     * Upload a source document (DOCX/DOC/PDF) and extract text.
     */
    public function uploadSourceDocument(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:doc,docx,pdf|max:20480',
            'draft_id' => 'nullable|integer',
        ]);

        $file = $request->file('file');
        $ext = strtolower($file->getClientOriginalExtension());
        $text = '';

        try {
            if ($ext === 'pdf') {
                // Use pdftotext if available, otherwise basic extraction
                $tmpPath = $file->getPathname();
                $output = shell_exec("pdftotext " . escapeshellarg($tmpPath) . " - 2>/dev/null");
                $text = $output ?: '';
                if (empty(trim($text))) {
                    // Fallback: try php-based extraction
                    $text = file_get_contents($tmpPath);
                    $text = preg_replace('/[^\x20-\x7E\n\r\t]/', ' ', $text);
                    $text = preg_replace('/\s+/', ' ', $text);
                }
            } elseif (in_array($ext, ['doc', 'docx'])) {
                $tmpPath = $file->getPathname();
                // Try antiword for .doc, or unzip for .docx
                if ($ext === 'docx') {
                    $zip = new \ZipArchive();
                    if ($zip->open($tmpPath) === true) {
                        $xml = $zip->getFromName('word/document.xml');
                        $zip->close();
                        if ($xml) {
                            $text = strip_tags(str_replace('<', ' <', $xml));
                            $text = preg_replace('/\s+/', ' ', $text);
                        }
                    }
                } else {
                    $output = shell_exec("antiword " . escapeshellarg($tmpPath) . " 2>/dev/null");
                    $text = $output ?: file_get_contents($tmpPath);
                    $text = preg_replace('/[^\x20-\x7E\n\r\t]/', ' ', $text);
                }
            }

            $text = trim($text);
            if (empty($text)) {
                return response()->json(['success' => false, 'message' => 'Could not extract text from this document.']);
            }

            $wordCount = str_word_count($text);
            return response()->json(['success' => true, 'text' => $text, 'word_count' => $wordCount]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error processing document: ' . $e->getMessage()]);
        }
    }

    /**
     * Upload a photo for use in the pipeline (featured or inner).
     */
    public function uploadPhoto(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|image|max:20480',
            'draft_id' => 'nullable|integer',
            'type' => 'nullable|string|in:featured,inner',
        ]);

        $file = $request->file('file');
        $draftId = $request->input('draft_id', 0);
        $dir = "pipeline-uploads/{$draftId}";
        $path = $file->store($dir, 'public');

        $dimensions = @getimagesize($file->getPathname());

        return response()->json([
            'success' => true,
            'url' => asset('storage/' . $path),
            'path' => $path,
            'width' => $dimensions[0] ?? 0,
            'height' => $dimensions[1] ?? 0,
            'size' => $file->getSize(),
            'filename' => $file->getClientOriginalName(),
        ]);
    }

    public function saveDraft(SaveDraftRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $editorReady = $request->boolean('editor_ready');
        $debugMode = $this->pipelineDebugEnabled($request);
        $clientTrace = $this->pipelineClientTrace($request);
        $startedAt = microtime(true);
        $tabId = trim((string) $request->headers->get('X-Pipeline-Tab-Id', ''));
        $requestSummary = [
            'client_trace' => $clientTrace,
            'debug_mode' => $debugMode,
            'draft_id' => (int) ($validated['draft_id'] ?? 0),
            'user_id' => (int) ($validated['user_id'] ?? auth()->id()),
            'site_id' => (int) ($validated['site_id'] ?? 0),
            'tab_id' => $tabId,
            'editor_ready' => $editorReady,
            'body_length' => strlen((string) ($validated['body'] ?? '')),
            'title_length' => strlen((string) ($validated['title'] ?? '')),
        ];
        $this->logPipelineDebug('[Draft] Save requested', $requestSummary, $debugMode);

        $publishAction = (string) ($validated['publish_action'] ?? '');
        $deliveryMode = match ($publishAction) {
            'publish' => 'auto-publish',
            'draft_wp', 'future' => 'draft-wordpress',
            'draft_local' => 'draft-local',
            default => null,
        };
        $scheduledFor = ($publishAction === 'future' && !empty($validated['schedule_date']))
            ? $validated['schedule_date']
            : null;

        $data = [
            'title'            => $validated['title'] ?? 'Untitled Pipeline Draft',
            'body'             => $validated['body'] ?? null,
            'excerpt'          => $validated['excerpt'] ?? null,
            'article_type'     => $validated['article_type'] ?? null,
            'status'           => 'drafting',
            'delivery_mode'    => $deliveryMode,
            'scheduled_for'    => $scheduledFor,
            'user_id'          => $validated['user_id'] ?? auth()->id(),
            'created_by'       => $validated['user_id'] ?? auth()->id(),
            'publish_site_id'  => $validated['site_id'] ?? null,
            'publish_template_id' => $validated['template_id'] ?? null,
            'preset_id'        => $validated['preset_id'] ?? null,
            'ai_engine_used'   => $validated['ai_model'] ?? null,
            'author'           => $validated['author'] ?? null,
            'source_articles'  => $validated['sources'] ?? null,
            'categories'       => isset($validated['categories']) ? array_values(array_filter(array_map(fn ($value) => trim((string) $value), (array) $validated['categories']), fn ($value) => $value !== '')) : null,
            'tags'             => isset($validated['tags']) ? array_values(array_filter(array_map(fn ($value) => trim((string) $value), (array) $validated['tags']), fn ($value) => $value !== '')) : null,
            'word_count'       => isset($validated['body']) ? str_word_count(strip_tags($validated['body'])) : 0,
            'photo_suggestions' => $this->sanitizePhotoSuggestionsForPersistence($validated['photo_suggestions'] ?? null),
            'featured_image_search' => $validated['featured_image_search'] ?? null,
            'notes'            => $validated['notes'] ?? null,
        ];

        if (!empty($validated['draft_id'])) {
            $draft = PublishArticle::findOrFail($validated['draft_id']);
            if ($conflict = $this->draftSession->conflictFor($draft, $tabId, auth()->id())) {
                return $this->draftSessionConflictResponse($draft, $conflict, 'draft save');
            }
            $incomingBody = $validated['body'] ?? null;
            if (
                !$editorReady
                && ($incomingBody === null || trim((string) $incomingBody) === '')
                && !empty($draft->body)
            ) {
                $data['body'] = $draft->body;
                $data['word_count'] = $draft->word_count;
            }
            $draft->update($data);
            $this->draftSession->claim($draft, $tabId, auth()->id(), [
                'source' => 'draft_save',
                'client_trace' => $clientTrace,
            ]);
            $message = "Draft updated: {$draft->title}";
        } else {
            $data['article_id'] = PublishArticle::generateArticleId();
            $draft = PublishArticle::create($data);
            $this->draftSession->claim($draft, $tabId, auth()->id(), [
                'source' => 'draft_create',
                'client_trace' => $clientTrace,
            ]);
            $message = "Draft created: {$draft->title}";
        }

        hexaLog('publish', 'pipeline_draft_saved', $message);

        $response = [
            'success'  => true,
            'message'  => $message,
            'draft_id' => $draft->id,
        ];

        if ($debugMode) {
            $response['debug'] = [
                'client_trace' => $clientTrace,
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                'word_count' => $draft->word_count,
            ];
            $this->logPipelineDebug('[Draft] Save completed', array_merge($requestSummary, [
                'duration_ms' => $response['debug']['duration_ms'],
                'saved_draft_id' => $draft->id,
                'word_count' => $draft->word_count,
            ]), true);
        }

        return response()->json($response);
    }

    private function draftSessionConflictResponse(PublishArticle $draft, array $conflict, string $scope): JsonResponse
    {
        return response()->json([
            'success' => false,
            'code' => 'draft_session_conflict',
            'message' => 'Another tab is actively editing this draft. Saves are paused in this tab to avoid overwriting draft #' . $draft->id . '.',
            'conflict' => array_merge($conflict, [
                'scope' => $scope,
                'draft_id' => $draft->id,
            ]),
        ], 409);
    }

    private function sanitizePhotoSuggestionsForPersistence(?array $suggestions): ?array
    {
        if (!is_array($suggestions)) {
            return null;
        }

        return array_map(function ($suggestion, $index) {
            if (!is_array($suggestion)) {
                return [
                    'position' => $index,
                    'search_term' => '',
                    'alt_text' => '',
                    'caption' => '',
                    'suggestedFilename' => '',
                    'autoPhoto' => null,
                    'confirmed' => false,
                    'removed' => false,
                ];
            }

            $autoPhoto = $this->sanitizePhotoAssetForPersistence($suggestion['autoPhoto'] ?? null);

            return [
                'position' => (int) ($suggestion['position'] ?? $index),
                'search_term' => trim((string) ($suggestion['search_term'] ?? '')),
                'alt_text' => (string) ($suggestion['alt_text'] ?? ''),
                'caption' => (string) ($suggestion['caption'] ?? ''),
                'suggestedFilename' => (string) ($suggestion['suggestedFilename'] ?? ''),
                'autoPhoto' => $autoPhoto,
                'confirmed' => $autoPhoto !== null && !empty($suggestion['confirmed']),
                'removed' => !empty($suggestion['removed']),
            ];
        }, $suggestions, array_keys($suggestions));
    }

    private function sanitizePhotoAssetForPersistence(mixed $photo): ?array
    {
        if (!is_array($photo)) {
            return null;
        }

        $normalized = [
            'id' => $photo['id'] ?? null,
            'source' => (string) ($photo['source'] ?? ''),
            'source_url' => (string) ($photo['source_url'] ?? $photo['pexels_url'] ?? $photo['unsplash_url'] ?? $photo['pixabay_url'] ?? $photo['url'] ?? ''),
            'url' => (string) ($photo['url'] ?? $photo['url_large'] ?? $photo['url_full'] ?? $photo['url_thumb'] ?? ''),
            'url_thumb' => (string) ($photo['url_thumb'] ?? $photo['url_large'] ?? $photo['url_full'] ?? $photo['url'] ?? ''),
            'url_large' => (string) ($photo['url_large'] ?? $photo['url_full'] ?? $photo['url_thumb'] ?? $photo['url'] ?? ''),
            'url_full' => (string) ($photo['url_full'] ?? $photo['url_large'] ?? $photo['url_thumb'] ?? $photo['url'] ?? ''),
            'alt' => (string) ($photo['alt'] ?? ''),
            'photographer' => (string) ($photo['photographer'] ?? ''),
            'photographer_url' => (string) ($photo['photographer_url'] ?? ''),
            'width' => (int) ($photo['width'] ?? 0),
            'height' => (int) ($photo['height'] ?? 0),
        ];

        if (
            $normalized['url'] === ''
            && $normalized['url_thumb'] === ''
            && $normalized['url_large'] === ''
            && $normalized['url_full'] === ''
        ) {
            return null;
        }

        return $normalized;
    }

    public function detectAi(DetectAiRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $text = strip_tags($validated['text']);
        $results = [];
        // Threshold = max AI % allowed (e.g. 10 = up to 10% AI is OK)
        $threshold = (float) Setting::getValue('ai_detection_threshold', 10);

        // Run each enabled detector
        $detectors = [
            'gptzero' => ['class' => \hexa_package_gptzero\Services\GptZeroService::class, 'name' => 'GPTZero'],
            'copyleaks' => ['class' => \hexa_package_copyleaks\Services\CopyleaksService::class, 'name' => 'Copyleaks'],
            'zerogpt' => ['class' => \hexa_package_zerogpt\Services\ZeroGptService::class, 'name' => 'ZeroGPT'],
            'originality' => ['class' => \hexa_package_originality\Services\OriginalityService::class, 'name' => 'Originality'],
        ];

        foreach ($detectors as $key => $info) {
            try {
                if (!class_exists($info['class'])) continue;
                $service = app($info['class']);
                if (!$service->isEnabled() || !$service->getApiKey()) continue;

                $result = $service->detect($text);

                // Normalize score to 0-100 AI percentage (higher = more AI)
                $aiScore = null;
                $debugMode = $service->isDebugMode();
                if ($result['success']) {
                    if ($key === 'gptzero') {
                        $aiScore = round(($result['data']['completely_generated_prob'] ?? 0) * 100, 1);
                    } elseif ($key === 'copyleaks') {
                        $aiScore = round((float) ($result['data']['ai_score'] ?? 0), 1);
                    } elseif ($key === 'zerogpt') {
                        $aiScore = round((float) ($result['data']['fake_percentage'] ?? 0), 1);
                    } elseif ($key === 'originality') {
                        $aiScore = round((float) ($result['data']['ai_score'] ?? 0) * 100, 1);
                    }
                }

                // Log the call
                \hexa_app_publish\Models\AiDetectionLog::logCall([
                    'detector' => $key,
                    'article_id' => $validated['article_id'] ?? null,
                    'text' => $text,
                    'response' => $result['data']['raw'] ?? $result,
                    'score' => $aiScore,
                    'cost' => $result['data']['cost'] ?? null,
                    'debug_mode' => $debugMode,
                    'success' => $result['success'],
                    'error' => $result['message'] ?? null,
                ]);

                $results[$key] = [
                    'name' => $info['name'],
                    'success' => $result['success'],
                    'ai_score' => $aiScore,
                    'passes' => $aiScore !== null && $aiScore <= $threshold,
                    'debug_mode' => $debugMode,
                    'message' => $result['message'] ?? '',
                    'sentences' => $result['data']['sentences'] ?? [],
                    'raw' => $result['data']['raw'] ?? null,
                ];
            } catch (\Exception $e) {
                $results[$key] = [
                    'name' => $info['name'],
                    'success' => false,
                    'ai_score' => null,
                    'passes' => false,
                    'debug_mode' => false,
                    'message' => 'Error: ' . $e->getMessage(),
                    'sentences' => [],
                    'raw' => null,
                ];
            }
        }

        $allPass = !empty($results) && collect($results)->where('success', true)->every(fn($r) => $r['passes']);

        return response()->json([
            'success' => true,
            'threshold' => $threshold,
            'all_pass' => $allPass,
            'results' => $results,
        ]);
    }

    private function pipelineDebugEnabled(Request $request): bool
    {
        return $request->boolean('debug_mode')
            || $request->headers->get('X-Pipeline-Debug') === '1';
    }

    private function dispatchPipelineOperation(array $strategy, PublishPipelineOperation $operation, array $payload, string $type): void
    {
        if ($strategy['transport'] === 'sync') {
            $executor = app(PipelineOperationExecutor::class);
            if ($type === PublishPipelineOperation::TYPE_PREPARE) {
                $executor->runPrepare($operation->id, $payload);
            } else {
                $executor->runPublish($operation->id, $payload);
            }

            return;
        }

        if ($strategy['transport'] === 'queue') {
            if ($type === PublishPipelineOperation::TYPE_PREPARE) {
                PreparePipelineOperationJob::dispatch($operation->id, $payload)
                    ->onConnection($strategy['queue_connection'])
                    ->onQueue($strategy['queue_name']);
            } else {
                PublishPipelineOperationJob::dispatch($operation->id, $payload)
                    ->onConnection($strategy['queue_connection'])
                    ->onQueue($strategy['queue_name']);
            }

            return;
        }

        if ($strategy['transport'] === 'queue_once') {
            if ($type === PublishPipelineOperation::TYPE_PREPARE) {
                PreparePipelineOperationJob::dispatch($operation->id, $payload)
                    ->onConnection($strategy['queue_connection'])
                    ->onQueue($strategy['queue_name']);
            } else {
                PublishPipelineOperationJob::dispatch($operation->id, $payload)
                    ->onConnection($strategy['queue_connection'])
                    ->onQueue($strategy['queue_name']);
            }

            app(PipelineOperationService::class)->spawnTransientQueueWorker(
                (string) $strategy['queue_connection'],
                (string) $strategy['queue_name']
            );

            return;
        }

        app()->terminating(function () use ($operation, $payload, $type) {
            $executor = app(PipelineOperationExecutor::class);
            if ($type === PublishPipelineOperation::TYPE_PREPARE) {
                $executor->runPrepare($operation->id, $payload);
            } else {
                $executor->runPublish($operation->id, $payload);
            }
        });
    }

    private function pipelineClientTrace(Request $request): string
    {
        return (string) ($request->headers->get('X-Pipeline-Run-Trace')
            ?: $request->headers->get('X-Pipeline-Client-Trace')
            ?: '');
    }

    private function pipelineBootstrapPresetsForUser(int $userId): array
    {
        $cacheKey = implode(':', [
            'publish',
            'presets',
            'json',
            'v' . ((int) Cache::get('publish:presets:json:version', 1)),
            'user',
            $userId,
        ]);

        return Cache::remember($cacheKey, now()->addMinutes(10), function () use ($userId) {
            return PublishPreset::query()
                ->select([
                    'id',
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
                    'created_at',
                    'updated_at',
                ])
                ->where('user_id', $userId)
                ->orderByDesc('updated_at')
                ->get()
                ->map(fn (PublishPreset $preset) => $preset->toArray())
                ->all();
        });
    }

    private function pipelineBootstrapTemplatesForUser(int $userId): array
    {
        $cacheKey = implode(':', [
            'publish',
            'templates',
            'json',
            'v' . ((int) Cache::get('publish:templates:json:version', 1)),
            'user',
            $userId,
            'account',
            'all',
            'type',
            'all',
        ]);

        return Cache::remember($cacheKey, now()->addMinutes(10), function () use ($userId) {
            return PublishTemplate::query()
                ->select([
                    'id',
                    'publish_account_id',
                    'name',
                    'status',
                    'is_default',
                    'article_type',
                    'description',
                    'ai_prompt',
                    'ai_engine',
                    'tone',
                    'word_count_min',
                    'word_count_max',
                    'photos_per_article',
                    'photo_sources',
                    'max_links',
                    'structure',
                    'rules',
                    'searching_agent',
                    'scraping_agent',
                    'spinning_agent',
                    'created_at',
                    'updated_at',
                ])
                ->with(['account:id,name,owner_user_id'])
                ->where(function ($accountScope) use ($userId) {
                    $accountScope->whereNull('publish_account_id')
                        ->orWhereHas('account', function ($query) use ($userId) {
                            $query->where('owner_user_id', $userId)
                                ->orWhereHas('users', fn ($users) => $users->where('user_id', $userId));
                        });
                })
                ->orderByDesc('updated_at')
                ->get()
                ->map(fn (PublishTemplate $template) => $template->toArray())
                ->all();
        });
    }

    private function photoMetaPayloadIsUsable(array $payload): bool
    {
        return !empty($payload['alt']) && !empty($payload['caption']) && !empty($payload['filename']);
    }

    /**
     * @param array<string, mixed> $validated
     * @return array{alt: string, caption: string, filename: string}
     */
    private function generatePhotoMetaDeterministically(array $validated): array
    {
        $photoSource = Str::lower((string) ($validated['photo_source'] ?? ''));
        $photoAlt = $this->cleanPhotoMetaText((string) ($validated['photo_alt'] ?? ''));
        $searchTerm = $this->cleanPhotoMetaText((string) ($validated['search_term'] ?? ''));
        $articleTitle = $this->cleanPhotoMetaText((string) ($validated['article_title'] ?? ''));
        $isGoogle = in_array($photoSource, ['google', 'google-cse'], true);
        $usablePhotoAlt = $this->photoMetaTextLooksUnusable($photoAlt) ? '' : $photoAlt;
        $personSpecificSearch = $this->searchTermLooksPersonSpecific($searchTerm);

        if ($isGoogle) {
            $visualSubject = $photoAlt !== '' ? $photoAlt : ($searchTerm !== '' ? $searchTerm : ($articleTitle !== '' ? $articleTitle : 'featured image'));
        } else {
            $visualSubject = $usablePhotoAlt !== ''
                ? $usablePhotoAlt
                : ($personSpecificSearch ? 'generic stock photo related to the topic' : ($searchTerm !== '' ? $searchTerm : 'general topic illustration'));
        }

        if ($isGoogle && $photoAlt !== '') {
            $alt = Str::limit(rtrim($photoAlt, '.!?'), 125, '');
            $caption = $articleTitle !== ''
                ? $this->ensureSentence($photoAlt . ' related to ' . $articleTitle)
                : $this->ensureSentence($photoAlt);
        } else {
            $alt = Str::limit(rtrim($visualSubject, '.!?'), 125, '');
            $caption = $usablePhotoAlt !== ''
                ? $this->ensureSentence('Stock photo showing ' . $usablePhotoAlt)
                : $this->ensureSentence('Stock photo illustrating a general concept related to the article topic');
        }

        $filenameSource = $isGoogle
            ? ($searchTerm !== '' ? $searchTerm : ($articleTitle !== '' ? $articleTitle : ($photoAlt !== '' ? $photoAlt : 'featured-image')))
            : ($usablePhotoAlt !== ''
                ? $usablePhotoAlt
                : ($personSpecificSearch ? 'stock-photo' : ($searchTerm !== '' ? $searchTerm : 'featured-image')));
        $filename = Str::slug($filenameSource);

        return [
            'alt' => $alt,
            'caption' => $caption,
            'filename' => $filename !== '' ? Str::limit($filename, 80, '') : 'featured-image',
        ];
    }

    /**
     * @param array<string, mixed> $validated
     * @return array{success: bool, message?: string, alt?: string, caption?: string, filename?: string}
     */
    private function generatePhotoMetaWithAi(array $validated): array
    {
        $catalog = app(AiModelCatalog::class);
        $model = $catalog->defaultPhotoMetaModel() ?: 'claude-haiku-4-5-20251001';
        $result = $this->dispatchAiChat(
            $model,
            'You are a photo metadata expert. Output ONLY valid JSON.',
            $this->buildPhotoMetaPrompt($validated),
            256,
            0.2
        );

        if (!$result['success']) {
            return ['success' => false, 'message' => $result['message'] ?? 'AI call failed'];
        }

        $parsed = $this->parseJsonPayload((string) ($result['data']['content'] ?? ''));
        if (!is_array($parsed) || empty($parsed['alt'])) {
            return ['success' => false, 'message' => 'Failed to parse AI response'];
        }

        return [
            'success' => true,
            'alt' => (string) ($parsed['alt'] ?? ''),
            'caption' => (string) ($parsed['caption'] ?? ''),
            'filename' => (string) ($parsed['filename'] ?? ''),
        ];
    }

    /**
     * @param array<string, mixed> $validated
     */
    private function buildPhotoMetaPrompt(array $validated): string
    {
        $photoSource = (string) ($validated['photo_source'] ?? '');
        $photoAlt = $this->cleanPhotoMetaText((string) ($validated['photo_alt'] ?? ''));
        $isGoogle = in_array($photoSource, ['google', 'google-cse'], true);

        if ($isGoogle && $photoAlt !== '') {
            return "You are a photo metadata expert. Generate metadata for a REAL PHOTO found via Google Image Search.\n\n"
                . "The original image caption/alt from the source is: \"{$photoAlt}\"\n"
                . "Use the source caption as the primary clue. Only name a specific person when the source caption clearly identifies that real person. Otherwise describe the visible scene without guessing identities.\n\n"
                . "Photo search term: " . ($validated['search_term'] ?? '') . "\n"
                . "Original image caption: {$photoAlt}\n"
                . "Article title: " . ($validated['article_title'] ?? '') . "\n"
                . "Article excerpt: " . mb_substr((string) ($validated['article_text'] ?? ''), 0, 1000) . "\n\n"
                . "Respond ONLY with JSON, no other text:\n"
                . '{"alt":"describe who/what is in the photo using the original caption for context (under 125 chars)","caption":"one sentence about the person/scene in the photo and their relevance to the article","filename":"seo-friendly-lowercase-hyphenated-name-no-extension"}';
        }

        return "You are a photo metadata expert. Generate metadata for a STOCK PHOTO used in an article.\n\n"
            . "CRITICAL: This is a stock photo, not a real photo of the people in the article. The alt text and caption must describe only what is visually shown in the stock photo. Treat the search term and article context as loose topic hints only. Never name or reference article subjects unless the stock provider caption explicitly identifies that exact real person in the image.\n\n"
            . "Photo search term: " . ($validated['search_term'] ?? '') . "\n"
            . "Stock provider alt/description: " . ($photoAlt !== '' ? $photoAlt : '(none provided)') . "\n"
            . "Article title: " . ($validated['article_title'] ?? '') . "\n"
            . "Article excerpt: " . mb_substr((string) ($validated['article_text'] ?? ''), 0, 1000) . "\n\n"
            . "Respond ONLY with JSON, no other text:\n"
            . '{"alt":"describe what the stock photo visibly shows using the provider alt first when available (under 125 chars, never guess article people names)","caption":"one sentence describing the visible stock photo scene in generic topical terms (never invent or name article subjects)","filename":"seo-friendly-lowercase-hyphenated-name-no-extension"}';
    }

    /**
     * @return array<string, mixed>|null
     */
    private function parseJsonPayload(string $content): ?array
    {
        $content = preg_replace('/^```(?:json)?\s*/i', '', trim($content));
        $content = preg_replace('/\s*```$/', '', $content);

        $parsed = json_decode(trim($content), true);
        if (is_array($parsed)) {
            return $parsed;
        }

        if (preg_match('/\{.*\}/s', $content, $matches)) {
            $parsed = json_decode($matches[0], true);
            if (is_array($parsed)) {
                return $parsed;
            }
        }

        return null;
    }

    /**
     * @return array{success: bool, message: string, data: array|null}
     */
    private function dispatchAiChat(
        string $model,
        string $systemPrompt,
        string $userMessage,
        int $maxTokens = 1024,
        float $temperature = 0.3,
        bool $withGoogleSearch = false
    ): array
    {
        $provider = app(AiModelCatalog::class)->providerForModel($model);

        return match ($provider) {
            'grok' => class_exists(\hexa_package_grok\Services\GrokService::class)
                ? app(\hexa_package_grok\Services\GrokService::class)->chat($systemPrompt, $userMessage, $model, $temperature, $maxTokens)
                : ['success' => false, 'message' => 'Grok package not available.', 'data' => null],
            'openai' => class_exists(\hexa_package_chatgpt\Services\ChatGptService::class)
                ? app(\hexa_package_chatgpt\Services\ChatGptService::class)->chat($systemPrompt, $userMessage, $model, $temperature, $maxTokens)
                : ['success' => false, 'message' => 'ChatGPT package not available.', 'data' => null],
            'gemini' => class_exists(\hexa_package_gemini\Services\GeminiService::class)
                ? ($withGoogleSearch
                    ? app(\hexa_package_gemini\Services\GeminiService::class)->chatWithGoogleSearch($systemPrompt, $userMessage, $model, $temperature, $maxTokens)
                    : app(\hexa_package_gemini\Services\GeminiService::class)->chat($systemPrompt, $userMessage, $model, $temperature, $maxTokens))
                : ['success' => false, 'message' => 'Gemini package not available.', 'data' => null],
            default => class_exists(\hexa_package_anthropic\Services\AnthropicService::class)
                ? app(\hexa_package_anthropic\Services\AnthropicService::class)->chat($systemPrompt, $userMessage, $model, $maxTokens)
                : ['success' => false, 'message' => 'Anthropic package not available.', 'data' => null],
        };
    }

    private function cleanPhotoMetaText(string $value): string
    {
        $value = html_entity_decode(strip_tags($value), ENT_QUOTES, 'UTF-8');
        $value = preg_replace('/\s+/u', ' ', $value);

        return trim((string) $value, " \t\n\r\0\x0B\"'");
    }

    private function photoMetaTextLooksUnusable(string $value): bool
    {
        if ($value === '') {
            return true;
        }

        return (bool) preg_match('/wikipedia|pexels|unsplash|pixabay|shutterstock|dreamstime|alamy|flickr|wikimedia|photo by|download/i', $value);
    }

    private function searchTermLooksPersonSpecific(string $value): bool
    {
        if ($value === '') {
            return false;
        }

        if (preg_match('/\b(smiling|speaking|arriving|posing|performing|walking|looking|portrait|headshot)\b/i', $value)) {
            return true;
        }

        $words = preg_split('/\s+/', trim($value)) ?: [];
        $capitalizedWords = array_filter($words, static fn (string $word): bool => (bool) preg_match('/^[A-Z][a-z]+$/', $word));

        return count($capitalizedWords) >= 2;
    }

    private function ensureSentence(string $value): string
    {
        $value = trim($this->cleanPhotoMetaText($value));

        return $value === '' ? '' : rtrim($value, '.!?') . '.';
    }

    private function logPipelineDebug(string $message, array $context, bool $enabled): void
    {
        if (!$enabled) {
            return;
        }

        hexaLogDebug('publish.pipeline', $message, $context);
    }

    private function resolveAuthorizedDraft(int $draftId): PublishArticle
    {
        $draft = PublishArticle::findOrFail($draftId);
        $user = auth()->user();

        abort_unless(
            $user && ($user->isAdmin() || $draft->created_by === $user->id || $draft->user_id === $user->id),
            403
        );

        return $draft;
    }

    private function createFreshPipelineDraft(): PublishArticle
    {
        return PublishArticle::create([
            'article_id' => PublishArticle::generateArticleId(),
            'title' => 'Untitled',
            'article_type' => 'editorial',
            'status' => 'drafting',
            'created_by' => auth()->id(),
            'user_id' => auth()->id(),
        ]);
    }

    private function serializePipelineOperation(?PublishPipelineOperation $operation): ?array
    {
        if (!$operation) {
            return null;
        }

        return [
            'id' => $operation->id,
            'draft_id' => $operation->publish_article_id,
            'site_id' => $operation->publish_site_id,
            'operation_type' => $operation->operation_type,
            'status' => $operation->status,
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
        ];
    }
}
