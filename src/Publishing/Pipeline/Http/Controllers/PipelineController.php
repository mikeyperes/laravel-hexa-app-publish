<?php

namespace hexa_app_publish\Publishing\Pipeline\Http\Controllers;

use hexa_core\Http\Controllers\Controller;
use hexa_core\Models\Setting;
use hexa_core\Models\User;
use hexa_app_publish\Models\PublishArticle;
use hexa_app_publish\Models\PublishSite;
use hexa_app_publish\Discovery\Sources\Services\SourceExtractionService;
use hexa_app_publish\Publishing\Campaigns\Services\NewsDiscoveryOptionsService;
use hexa_app_publish\Publishing\Articles\Services\ArticleGenerationService;
use hexa_app_publish\Publishing\Articles\Services\MetadataGenerationService;
use hexa_app_publish\Publishing\Presets\Models\PublishPreset;
use hexa_app_publish\Publishing\Pipeline\Jobs\PreparePipelineOperationJob;
use hexa_app_publish\Publishing\Pipeline\Jobs\PublishPipelineOperationJob;
use hexa_app_publish\Publishing\Pipeline\Services\PipelineStateService;
use hexa_app_publish\Publishing\Pipeline\Services\PipelineDraftSessionService;
use hexa_app_publish\Publishing\Pipeline\Services\PipelineOperationExecutor;
use hexa_app_publish\Publishing\Pipeline\Services\PipelineOperationService;
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
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
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
    protected PipelineDraftSessionService $draftSession;
    protected PipelineWorkflowRegistry $workflowRegistry;

    /**
     * @param SourceExtractionService $sourceExtraction
     */
    public function __construct(
        SourceExtractionService $sourceExtraction,
        NewsDiscoveryOptionsService $newsOptions,
        PipelineStateService $pipelineState,
        PipelineDraftSessionService $draftSession,
        PipelineWorkflowRegistry $workflowRegistry
    )
    {
        $this->sourceExtraction = $sourceExtraction;
        $this->newsOptions = $newsOptions;
        $this->pipelineState = $pipelineState;
        $this->draftSession = $draftSession;
        $this->workflowRegistry = $workflowRegistry;
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
        $latestCompletedPrepareHtml = PublishPipelineOperation::query()
            ->where('publish_article_id', $draft->id)
            ->where('operation_type', PublishPipelineOperation::TYPE_PREPARE)
            ->where('status', PublishPipelineOperation::STATUS_COMPLETED)
            ->latest('id')
            ->value('result_payload->html');
        $draftState = [
            'selectedUser' => $draftUser ? [
                'id' => $draftUser->id,
                'name' => $draftUser->name,
                'email' => $draftUser->email,
            ] : null,
            'selectedPresetId' => $draft->preset_id ? (string) $draft->preset_id : '',
            'selectedTemplateId' => $draft->publish_template_id ? (string) $draft->publish_template_id : '',
            'selectedSiteId' => $draftSite?->id ? (string) $draftSite->id : '',
            'selectedSite' => $draftSite ? [
                'id' => $draftSite->id,
                'name' => $draftSite->name,
                'url' => $draftSite->url,
            ] : null,
            'publishAuthor' => $draft->author ?: ($draftSite?->default_author ?? ''),
            'siteConnStatus' => $draftSite
                ? match ($draftSite->status) {
                    'connected' => true,
                    'error' => false,
                    default => null,
                }
                : null,
            'articleTitle' => $draft->title ?: '',
            'body' => $draft->body,
            'wordCount' => $draft->word_count ?: 0,
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
            'filenamePattern'   => \hexa_core\Models\Setting::getValue('wp_photo_filename_pattern', 'hexa_{draft_id}_{seo_name}'),
            'aiDetectors'       => $aiDetectors,
            'grokModels'        => class_exists(\hexa_package_grok\Services\GrokService::class) ? app(\hexa_package_grok\Services\GrokService::class)->listModels() : [],
            'pipelinePayload'   => $pipelinePayload,
            'latestCompletedPrepareHtml' => $latestCompletedPrepareHtml ?: '',
            'initialUserPresets' => $initialUserPresets,
            'initialUserTemplates' => $initialUserTemplates,
            'workflowDefinitions' => $this->workflowRegistry->definitions(),
            'pressReleaseDefaultState' => app(\hexa_app_publish\Publishing\Pipeline\Services\PressReleaseWorkflowService::class)->defaultState(),
        ]);
    }

    public function generatePhotoMeta(GeneratePhotoMetaRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $anthropic = app(\hexa_package_anthropic\Services\AnthropicService::class);
        $photoSource = $validated['photo_source'] ?? '';
        $photoAlt = $validated['photo_alt'] ?? '';
        $photoUrl = $validated['photo_url'] ?? '';
        $isStock = in_array($photoSource, ['pexels', 'unsplash', 'pixabay', '']);
        $isGoogle = in_array($photoSource, ['google', 'google-cse']);

        if ($isGoogle && $photoAlt) {
            // Real photo from Google — use the original alt/caption context
            $prompt = "You are a photo metadata expert. Generate metadata for a REAL PHOTO found via Google Image Search.\n\n"
                . "The original image caption/alt from the source is: \"{$photoAlt}\"\n"
                . "If this identifies a specific person, USE their name in the alt and caption. Describe the actual person and scene.\n\n"
                . "Photo search term: " . $validated['search_term'] . "\n"
                . "Original image caption: {$photoAlt}\n"
                . "Article title: " . ($validated['article_title'] ?? '') . "\n"
                . "Article excerpt: " . mb_substr($validated['article_text'] ?? '', 0, 1000) . "\n\n"
                . "Respond ONLY with JSON, no other text:\n"
                . '{"alt":"describe who/what is in the photo using the original caption for context (under 125 chars)","caption":"one sentence about the person/scene in the photo and their relevance to the article","filename":"seo-friendly-lowercase-hyphenated-name-no-extension"}';
        } else {
            // Stock photo — generic description, no person names
            $prompt = "You are a photo metadata expert. Generate metadata for a STOCK PHOTO used in an article.\n\n"
                . "CRITICAL: This is a stock photo, NOT a real photo of the people in the article. The alt text and caption must describe what is visually in the stock photo based on the search term — NOT name or reference specific people from the article. Never put a person's name in the alt or caption unless the search term itself is that person's name.\n\n"
                . "Photo search term: " . $validated['search_term'] . "\n"
                . "Article title: " . ($validated['article_title'] ?? '') . "\n"
                . "Article excerpt: " . mb_substr($validated['article_text'] ?? '', 0, 1000) . "\n\n"
                . "Respond ONLY with JSON, no other text:\n"
                . '{"alt":"describe what the stock photo visually shows based on the search term (under 125 chars, do NOT name article subjects)","caption":"one sentence describing the stock photo and how the visual theme relates to the article topic (do NOT name article subjects unless the search term is their name)","filename":"seo-friendly-lowercase-hyphenated-name-no-extension"}';
        }

        $result = $anthropic->chat(
            'You are a photo metadata expert. Output ONLY valid JSON.',
            $prompt,
            'claude-haiku-4-5-20251001',
            256
        );

        if (!$result['success']) {
            return response()->json(['success' => false, 'message' => $result['message'] ?? 'AI call failed']);
        }

        $content = $result['data']['content'] ?? '';
        $content = preg_replace('/^```json\s*/i', '', $content);
        $content = preg_replace('/\s*```$/', '', $content);
        $parsed = json_decode(trim($content), true);

        if (!$parsed || !isset($parsed['alt'])) {
            return response()->json(['success' => false, 'message' => 'Failed to parse AI response']);
        }

        return response()->json([
            'success'  => true,
            'alt'      => $parsed['alt'] ?? '',
            'caption'  => $parsed['caption'] ?? '',
            'filename' => $parsed['filename'] ?? '',
        ]);
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
            null,
            true,
            $validated['prompt_slug'] ?? null
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
        $query = $request->input('q', '');
        $type = $request->input('type');

        if (strlen($query) < 1) {
            $profileClass = 'hexa_package_profiles\\Models\\Profile';
            if (!class_exists($profileClass)) {
                return response()->json([]);
            }
            $profiles = $profileClass::with(['type', 'customFields'])
                ->where('is_active', true)
                ->when($type, fn($q) => $q->whereHas('type', fn($tq) => $tq->where('slug', $type)))
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

        return response()->json($profiles->map(fn($p) => [
            'id'              => $p->id,
            'name'            => $p->name,
            'slug'            => $p->slug,
            'description'     => $p->description,
            'photo_url'       => $p->photo_url,
            'type'            => $p->type?->name ?? '—',
            'type_slug'       => $p->type?->slug ?? '',
            'external_source' => $p->external_source ?? null,
            'external_id'     => $p->external_id ?? null,
            'fields'          => $p->customFields->pluck('field_value', 'field_key')->toArray(),
        ])->values());
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

    /**
     * AI Article Search — use Claude with web search to find articles on a topic.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function aiSearchArticles(Request $request): JsonResponse
    {
        $request->validate([
            'topic' => 'required|string|min:3|max:500',
            'count' => 'nullable|integer|min:2|max:10',
            'model' => 'nullable|string|max:100',
        ]);

        $model = $request->input('model', '');
        $topic = $request->input('topic');
        $count = $request->input('count', 4);
        $isGrok = str_starts_with($model, 'grok-');
        $isOpenAI = str_starts_with($model, 'gpt-');

        $searchPrompt = "Search the web for {$count} recent news articles about: {$topic}. "
            . "Find real, published articles from different reputable news sources. "
            . "For each article return the exact URL, the article title, and a 1-2 sentence description. "
            . "Return ONLY a JSON array of objects with keys: url, title, description. No other text.";

        if ($isGrok) {
            if (!class_exists(\hexa_package_grok\Services\GrokService::class)) {
                return response()->json(['success' => false, 'message' => 'Grok package not available.'], 400);
            }
            $grok = app(\hexa_package_grok\Services\GrokService::class);
            $raw = $grok->chat(
                "You are a research assistant with web access. Find real, recent news articles. Output ONLY valid JSON.",
                $searchPrompt,
                $model,
                0.3,
                2048
            );
            $result = $this->parseSearchResult($raw, $model);
        } elseif ($isOpenAI) {
            if (!class_exists(\hexa_package_chatgpt\Services\ChatGptService::class)) {
                return response()->json(['success' => false, 'message' => 'ChatGPT package not available.'], 400);
            }
            $chatgpt = app(\hexa_package_chatgpt\Services\ChatGptService::class);
            $raw = $chatgpt->chat(
                "You are a research assistant with web access. Find real, recent news articles. Output ONLY valid JSON.",
                $searchPrompt,
                $model,
                0.3,
                2048
            );
            $result = $this->parseSearchResult($raw, $model);
        } else {
            // Claude — use web search tool
            if (!class_exists(\hexa_package_anthropic\Services\AnthropicService::class)) {
                return response()->json(['success' => false, 'message' => 'Anthropic package not available.'], 400);
            }
            $anthropic = app(\hexa_package_anthropic\Services\AnthropicService::class);
            $result = $anthropic->searchArticles($topic, $count, $model ?: null);
        }

        // Calculate cost if successful
        if ($result['success'] && !empty($result['data']['usage'])) {
            $pricing = [
                'claude-haiku-4-5-20251001' => ['input' => 0.80, 'output' => 4.0],
                'claude-sonnet-4-20250514'  => ['input' => 3.0,  'output' => 15.0],
            ];
            $usedModel = $result['data']['model'] ?? $model;
            $rates = $pricing[$usedModel] ?? ['input' => 1.0, 'output' => 5.0];
            $usage = $result['data']['usage'];
            $cost = ($usage['input_tokens'] * $rates['input'] / 1_000_000)
                  + ($usage['output_tokens'] * $rates['output'] / 1_000_000);
            $result['data']['cost'] = round($cost, 6);
        }

        return response()->json($result);
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

        $result = app(ArticleGenerationService::class)->generate(
            $validated['source_texts'],
            [
                'model'              => $validated['model'],
                'template_id'        => $validated['template_id'] ?? null,
                'preset_id'          => $validated['preset_id'] ?? null,
                'prompt_slug'        => $validated['prompt_slug'] ?? null,
                'custom_prompt'      => $validated['custom_prompt'] ?? null,
                'supporting_url_type'=> $validated['supporting_url_type'] ?? 'matching_content_type',
                'change_request'     => $validated['change_request'] ?? null,
                'pr_subject_context' => $validated['pr_subject_context'] ?? null,
                'web_research'       => $request->boolean('web_research', false),
                'agent'              => !empty($validated['change_request']) ? 'pipeline-revise' : 'pipeline-spin',
            ]
        );

        if (!$result['success']) {
            return response()->json(['success' => false, 'message' => $result['message']]);
        }

        return response()->json(array_merge($result, [
            'user_name'     => auth()->user()?->name ?? 'System',
            'ip'            => request()->ip(),
            'timestamp_utc' => now()->utc()->format('Y-m-d H:i:s'),
        ]));
    }

    /**
     * Generate article metadata: 10 title options, 15 categories, 15 tags.
     * Uses Haiku for speed and cost efficiency.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function generateMetadata(GenerateMetadataRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $result = app(MetadataGenerationService::class)->generate($validated['article_html']);

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

        $data = [
            'title'            => $validated['title'] ?? 'Untitled Pipeline Draft',
            'body'             => $validated['body'] ?? null,
            'excerpt'          => $validated['excerpt'] ?? null,
            'article_type'     => $validated['article_type'] ?? null,
            'status'           => 'drafting',
            'user_id'          => $validated['user_id'] ?? auth()->id(),
            'created_by'       => $validated['user_id'] ?? auth()->id(),
            'publish_site_id'  => $validated['site_id'] ?? null,
            'publish_template_id' => $validated['template_id'] ?? null,
            'preset_id'        => $validated['preset_id'] ?? null,
            'ai_engine_used'   => $validated['ai_model'] ?? null,
            'author'           => $validated['author'] ?? null,
            'source_articles'  => $validated['sources'] ?? null,
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
        return (string) ($request->headers->get('X-Pipeline-Client-Trace') ?: '');
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

    private function logPipelineDebug(string $message, array $context, bool $enabled): void
    {
        if (!$enabled) {
            return;
        }

        \Log::info($message, $context);
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
