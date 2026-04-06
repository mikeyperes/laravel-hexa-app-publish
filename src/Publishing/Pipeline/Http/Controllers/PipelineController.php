<?php

namespace hexa_app_publish\Publishing\Pipeline\Http\Controllers;

use hexa_core\Http\Controllers\Controller;
use hexa_core\Models\Setting;
use hexa_core\Models\User;
use hexa_app_publish\Models\PublishArticle;
use hexa_app_publish\Models\PublishMasterSetting;
use hexa_app_publish\Models\PublishPreset;
use hexa_app_publish\Models\PublishPrompt;
use hexa_app_publish\Models\AiActivityLog;
use hexa_app_publish\Models\PublishTemplate;
use hexa_app_publish\Models\PublishSite;
use hexa_app_publish\Discovery\Sources\Services\SourceExtractionService;
use hexa_app_publish\Publishing\Articles\Services\ArticleGenerationService;
use hexa_app_publish\Publishing\Articles\Services\MetadataGenerationService;
use hexa_app_publish\Publishing\Articles\Services\ArticlePersistenceService;
use hexa_app_publish\Publishing\Delivery\Services\WordPressDeliveryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
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

    /**
     * @param SourceExtractionService $sourceExtraction
     */
    public function __construct(SourceExtractionService $sourceExtraction)
    {
        $this->sourceExtraction = $sourceExtraction;
    }

    /**
     * Resolve the WHM server for a WP Toolkit site.
    /**
     * Show the pipeline page.
     *
     * @return View
     */
    public function index(Request $request)
    {
        // If no ?id= in URL, reuse an existing empty draft or create one
        if (!$request->has('id')) {
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

            if (!$draft) {
                $draft = PublishArticle::create([
                    'article_id' => PublishArticle::generateArticleId(),
                    'title'      => 'Untitled',
                    'status'     => 'drafting',
                    'created_by' => auth()->id(),
                    'user_id'    => auth()->id(),
                ]);
            }

            return redirect()->route('publish.pipeline', ['id' => $draft->id]);
        }

        // Load existing draft
        $draftId = (int) $request->input('id');
        $draft = PublishArticle::find($draftId);
        if (!$draft) {
            return redirect()->route('publish.pipeline');
        }

        $sites = PublishSite::where('status', 'connected')->orderBy('name')->get();
        $draftSite = $draft->publish_site_id ? PublishSite::find($draft->publish_site_id) : null;
        if ($draftSite && !$sites->contains('id', $draftSite->id)) {
            $sites->push($draftSite);
            $sites = $sites->sortBy('name')->values();
        }
        $newsCategories = \DB::table('lists')->where('list_key', 'news_categories')->where('is_active', true)->orderBy('sort_order')->pluck('list_value');

        $draftUserId = $draft->user_id ?: $draft->created_by;
        $draftUser = $draftUserId ? \hexa_core\Models\User::find($draftUserId) : null;
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

        return view('app-publish::publishing.pipeline.index', [
            'sites'             => $sites,
            'draftId'           => $draft->id,
            'newsCategories'    => $newsCategories,
            'draftUser'         => $draftUser,
            'draftState'        => $draftState,
            'templateSchema'    => \hexa_app_publish\Publishing\Templates\Models\PublishTemplate::getFieldSchema(),
            'presetSchema'      => \hexa_app_publish\Publishing\Presets\Models\PublishPreset::getFieldSchema(),
            'articlePresetForm' => $articlePresetForm,
            'wpPresetForm'      => $wpPresetForm,
            'filenamePattern'   => \hexa_core\Models\Setting::getValue('wp_photo_filename_pattern', 'hexa_{draft_id}_{seo_name}'),
        ]);
    }

    /**
     * Preview the resolved prompt without spinning.
     *
     * @param Request $request
     * @return JsonResponse
     */
    /**
     * Generate contextual photo metadata via AI (Haiku).
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function generatePhotoMeta(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search_term'    => 'required|string|max:200',
            'article_title'  => 'nullable|string|max:500',
            'article_text'   => 'nullable|string|max:2000',
        ]);

        $anthropic = app(\hexa_package_anthropic\Services\AnthropicService::class);
        $prompt = "You are a photo metadata expert. Generate contextual metadata for a stock photo used in an article.\n\n"
            . "Photo search term: " . $validated['search_term'] . "\n"
            . "Article title: " . ($validated['article_title'] ?? '') . "\n"
            . "Article excerpt: " . mb_substr($validated['article_text'] ?? '', 0, 1000) . "\n\n"
            . "Respond ONLY with JSON, no other text:\n"
            . '{"alt":"contextual alt text describing what this photo represents in the article context (under 125 chars)","caption":"one sentence explaining why this photo is relevant to the article","filename":"seo-friendly-lowercase-hyphenated-name-no-extension"}';

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

    public function previewPrompt(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'source_texts'   => 'nullable|array',
            'source_texts.*' => 'nullable|string',
            'template_id'    => 'nullable|integer',
            'preset_id'      => 'nullable|integer',
            'custom_prompt'  => 'nullable|string|max:5000',
        ]);

        $prompt = app(ArticleGenerationService::class)->buildPrompt(
            $validated['source_texts'] ?? ['[Source articles will be inserted here]'],
            $validated['template_id'] ?? null,
            $validated['preset_id'] ?? null,
            $validated['custom_prompt'] ?? null,
            null
        );

        return response()->json(['success' => true, 'prompt' => $prompt]);
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
     * Check source URLs by extracting article content from each.
     *
     * Accepts an array of URLs, runs ArticleExtractorService::extract() on each,
     * and returns per-URL pass/fail with word count.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function checkSources(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'urls'        => 'required|array|min:1',
            'urls.*'      => 'required|url|max:2048',
            'user_agent'  => 'nullable|string|max:100',
            'method'      => 'nullable|in:auto,readability,css,regex',
            'retries'     => 'nullable|integer|min:0|max:5',
            'timeout'     => 'nullable|integer|min:5|max:60',
            'min_words'   => 'nullable|integer|min:10|max:1000',
            'auto_fallback' => 'nullable|boolean',
        ]);

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
     * Spin article content using AI.
     *
     * Builds a full prompt stack from master settings + preset config + prompt template + source texts,
     * then calls AnthropicService::chat().
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function spin(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'source_texts'       => 'required|array|min:1',
            'source_texts.*'     => 'required|string',
            'template_id'        => 'nullable|integer|exists:publish_templates,id',
            'preset_id'          => 'nullable|integer|exists:publish_presets,id',
            'model'              => 'required|string|max:100',
            'change_request'     => 'nullable|string|max:2000',
            'custom_prompt'      => 'nullable|string|max:5000',
            'master_setting_ids' => 'nullable|array',
            'master_setting_ids.*' => 'integer|exists:publish_master_settings,id',
        ]);

        $result = app(ArticleGenerationService::class)->generate(
            $validated['source_texts'],
            [
                'model'          => $validated['model'],
                'template_id'    => $validated['template_id'] ?? null,
                'preset_id'      => $validated['preset_id'] ?? null,
                'custom_prompt'  => $validated['custom_prompt'] ?? null,
                'change_request' => $validated['change_request'] ?? null,
                'agent'          => !empty($validated['change_request']) ? 'pipeline-revise' : 'pipeline-spin',
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
    public function generateMetadata(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'article_html' => 'required|string',
        ]);

        $result = app(MetadataGenerationService::class)->generate($validated['article_html']);

        return response()->json($result);
    }

    /**
     * Prepare content for WordPress: upload images, create categories/tags, validate HTML.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function prepareForWordpress(Request $request): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $validated = $request->validate([
            'html'                => 'required|string',
            'title'               => 'nullable|string|max:500',
            'site_id'             => 'required|integer|exists:publish_sites,id',
            'categories'          => 'nullable|array',
            'tags'                => 'nullable|array',
            'pipeline_session_id' => 'nullable|string|max:100',
            'draft_id'            => 'nullable|integer',
            'photo_suggestions'   => 'nullable|array',
            'photo_meta'          => 'nullable|array',
            'photo_meta.*.alt_text'  => 'nullable|string',
            'photo_meta.*.caption'   => 'nullable|string',
            'photo_meta.*.filename'  => 'nullable|string',
            'featured_meta'       => 'nullable|array',
            'featured_meta.alt_text' => 'nullable|string',
            'featured_meta.caption'  => 'nullable|string',
            'featured_meta.filename' => 'nullable|string',
        ]);

        $site = PublishSite::findOrFail($validated['site_id']);
        $prepService = app(\hexa_app_publish\Publishing\Delivery\Services\WordPressPreparationService::class);

        return response()->stream(function () use ($validated, $site, $prepService) {
            $send = function (string $type, string $message, array $extra = []) {
                $event = array_merge(['type' => $type, 'message' => $message, 'time' => now()->format('H:i:s')], $extra);
                echo "data: " . json_encode($event) . "\n\n";
                if (ob_get_level()) ob_flush();
                flush();
            };

            $result = $prepService->prepare($site, $validated['html'], [
                'title'             => $validated['title'] ?? null,
                'categories'        => $validated['categories'] ?? [],
                'tags'              => $validated['tags'] ?? [],
                'photo_suggestions' => $validated['photo_suggestions'] ?? [],
                'photo_meta'        => $validated['photo_meta'] ?? [],
                'featured_meta'     => $validated['featured_meta'] ?? null,
                'draft_id'          => $validated['draft_id'] ?? 0,
            ], $send);

            $send('done', $result['success'] ? 'Preparation complete' : 'Preparation failed', $result);

        }, 200, [
            'Content-Type'  => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection'    => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    /**
     * Publish article to WordPress.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function publishToWordpress(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'html'                => 'required|string',
            'title'               => 'required|string|max:500',
            'site_id'             => 'required|integer|exists:publish_sites,id',
            'category_ids'        => 'nullable|array',
            'tag_ids'             => 'nullable|array',
            'status'              => 'required|in:publish,draft,future',
            'date'                => 'nullable|date',
            'pipeline_session_id' => 'nullable|string|max:100',
            'categories'          => 'nullable|array',
            'tags'                => 'nullable|array',
            'wp_images'           => 'nullable|array',
            'word_count'          => 'nullable|integer',
            'ai_model'            => 'nullable|string|max:100',
            'ai_cost'             => 'nullable|numeric',
            'ai_provider'         => 'nullable|string|max:50',
            'ai_tokens_input'     => 'nullable|integer',
            'ai_tokens_output'    => 'nullable|integer',
            'resolved_prompt'     => 'nullable|string',
            'photo_suggestions'   => 'nullable|array',
            'featured_image_search' => 'nullable|string|max:500',
            'author'              => 'nullable|string|max:255',
            'sources'             => 'nullable|array',
            'template_id'         => 'nullable|integer',
            'preset_id'           => 'nullable|integer',
            'user_id'             => 'nullable|integer',
            'draft_id'     => 'nullable|integer|exists:publish_articles,id',
        ]);

        $site = PublishSite::findOrFail($validated['site_id']);

        // Deliver to WordPress via shared service
        $delivery = app(WordPressDeliveryService::class);
        $result = $delivery->createPost($site, $validated['title'], $validated['html'], $validated['status'], [
            'category_ids' => $validated['category_ids'] ?? [],
            'tag_ids'      => $validated['tag_ids'] ?? [],
            'date'         => ($validated['status'] === 'future' && !empty($validated['date'])) ? $validated['date'] : null,
        ]);

        if (!$result['success']) {
            hexaLog('publish', 'pipeline_publish_failed', "Pipeline publish failed to {$site->name}: {$result['message']}");
            return response()->json($result);
        }

        // Persist article via shared service
        $persistence = app(ArticlePersistenceService::class);
        $article = $persistence->createOrUpdate([
            'pipeline_session_id' => $validated['pipeline_session_id'] ?? null,
            'user_id'             => $validated['user_id'] ?? auth()->id(),
            'publish_site_id'     => $site->id,
            'publish_template_id' => $validated['template_id'] ?? null,
            'preset_id'           => $validated['preset_id'] ?? null,
            'title'               => $validated['title'],
            'body'                => $validated['html'],
            'word_count'          => $validated['word_count'] ?? str_word_count(strip_tags($validated['html'])),
            'ai_engine_used'      => $validated['ai_model'] ?? null,
            'ai_cost'             => $validated['ai_cost'] ?? null,
            'ai_provider'         => $validated['ai_provider'] ?? 'anthropic',
            'ai_tokens_input'     => $validated['ai_tokens_input'] ?? null,
            'ai_tokens_output'    => $validated['ai_tokens_output'] ?? null,
            'resolved_prompt'     => $validated['resolved_prompt'] ?? null,
            'photo_suggestions'   => $validated['photo_suggestions'] ?? null,
            'featured_image_search' => $validated['featured_image_search'] ?? null,
            'user_ip'             => request()->ip(),
            'author'              => $validated['author'] ?? $site->default_author ?? null,
            'status'              => 'completed',
            'wp_post_id'          => $result['post_id'],
            'wp_post_url'         => $result['post_url'],
            'wp_status'           => $validated['status'],
            'published_at'        => now(),
            'source_articles'     => $validated['sources'] ?? null,
            'categories'          => $validated['categories'] ?? null,
            'tags'                => $validated['tags'] ?? null,
            'wp_images'           => $validated['wp_images'] ?? null,
            'links_injected'      => null,
            'created_by'          => auth()->id(),
        ], $validated['draft_id'] ?? null);

        hexaLog('publish', 'pipeline_published', "Pipeline published to {$site->name}: {$validated['title']} (WP ID: {$result['post_id']}, Article: {$article->article_id})");

        // Clean up temp uploads for this article (publish/draft_wp only, not future)
        if (in_array($validated['status'], ['publish', 'draft'], true) && !empty($validated['draft_id'])) {
            try {
                $uploadCleanup = app(\hexa_app_publish\Publishing\Uploads\Services\ArticleUploadService::class);
                $uploadCleanup->cleanupAfterPublish((int) $validated['draft_id']);
            } catch (\Throwable $e) {
                // Non-critical — log but don't fail the publish
                hexaLog('publish', 'upload_cleanup_error', "Upload cleanup failed for draft #{$validated['draft_id']}: {$e->getMessage()}");
            }
        }

        return response()->json([
            'success'    => true,
            'message'    => "Article published to {$site->name}. WP Post ID: {$result['post_id']}.",
            'post_id'    => $result['post_id'],
            'post_url'   => $result['post_url'],
            'article_id' => $article->id,
            'article_url' => route('publish.articles.show', $article->id),
        ]);
    }

    /**
     * Save current pipeline state as a draft article.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function saveDraft(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'draft_id'    => 'nullable|integer|exists:publish_articles,id',
            'title'       => 'nullable|string|max:500',
            'body'        => 'nullable|string',
            'excerpt'     => 'nullable|string|max:1000',
            'user_id'     => 'nullable|integer|exists:users,id',
            'site_id'     => 'nullable|integer|exists:publish_sites,id',
            'preset_id'   => 'nullable|integer',
            'prompt_id'   => 'nullable|integer',
            'ai_model'    => 'nullable|string|max:100',
            'author'      => 'nullable|string|max:255',
            'sources'     => 'nullable|array',
            'tags'        => 'nullable|array',
            'categories'  => 'nullable|array',
            'notes'       => 'nullable|string',
            'template_id' => 'nullable|integer',
            'photo_suggestions' => 'nullable|array',
            'featured_image_search' => 'nullable|string|max:500',
        ]);

        $data = [
            'title'            => $validated['title'] ?? 'Untitled Pipeline Draft',
            'body'             => $validated['body'] ?? null,
            'excerpt'          => $validated['excerpt'] ?? null,
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
            'photo_suggestions' => $validated['photo_suggestions'] ?? null,
            'featured_image_search' => $validated['featured_image_search'] ?? null,
            'notes'            => $validated['notes'] ?? null,
        ];

        if (!empty($validated['draft_id'])) {
            $draft = PublishArticle::findOrFail($validated['draft_id']);
            $draft->update($data);
            $message = "Draft updated: {$draft->title}";
        } else {
            $data['article_id'] = PublishArticle::generateArticleId();
            $draft = PublishArticle::create($data);
            $message = "Draft created: {$draft->title}";
        }

        hexaLog('publish', 'pipeline_draft_saved', $message);

        return response()->json([
            'success'  => true,
            'message'  => $message,
            'draft_id' => $draft->id,
        ]);
    }

    /**
     * Create a taxonomy term (category or tag) on a WordPress site.
     *
     * @param string $siteUrl
     * @param string $username
     * @param string $password
     * @param string $taxonomy  Either 'categories' or 'tags'
     * @param string $name      The term name
    /**
    /**
     * Run AI detection on article text using all enabled detectors.
     * Returns results per detector with scores and flagged sentences.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function detectAi(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'text' => 'required|string|min:10',
            'article_id' => 'nullable|integer',
        ]);

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
}
