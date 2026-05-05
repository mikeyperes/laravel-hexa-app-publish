<?php

namespace hexa_app_publish\Publishing\Articles\Http\Controllers;

use hexa_core\Http\Controllers\Controller;
use hexa_app_publish\Models\PublishAccount;
use hexa_app_publish\Models\PublishArticle;
use hexa_app_publish\Models\PublishSite;
use hexa_app_publish\Models\PublishTemplate;
use hexa_app_publish\Publishing\Articles\Http\Requests\InsertLinksRequest;
use hexa_app_publish\Publishing\Articles\Http\Requests\ScrapeUrlRequest;
use hexa_app_publish\Publishing\Articles\Http\Requests\SearchDiscoveryRequest;
use hexa_app_publish\Publishing\Articles\Http\Requests\SpinArticleRequest;
use hexa_app_publish\Publishing\Articles\Http\Requests\StoreArticleRequest;
use hexa_app_publish\Publishing\Articles\Http\Requests\UpdateArticleRequest;
use hexa_app_publish\Publishing\Articles\Services\ArticleActivityService;
use hexa_app_publish\Publishing\Articles\Services\ArticlePersistenceService;
use hexa_app_publish\Discovery\Sources\Services\SourceDiscoveryService;
use hexa_app_publish\Discovery\Sources\Services\SourceExtractionService;
use hexa_app_publish\Discovery\Media\Services\MediaSearchService;
use hexa_app_publish\Discovery\Links\Services\LinkInsertionService;
use hexa_app_publish\Quality\Detection\Services\SeoAnalysisService;
use hexa_package_anthropic\Services\AnthropicService;
use hexa_package_chatgpt\Services\ChatGptService;
use hexa_package_grok\Services\GrokService;
use hexa_package_gemini\Services\GeminiService;
use hexa_package_sapling\Services\SaplingService;
use hexa_package_telegram\Services\TelegramService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ArticleController extends Controller
{
    /**
     * List all articles with filters.
     *
     * @param Request $request
     * @return View
     */
    public function index(Request $request): View|JsonResponse
    {
        $query = PublishArticle::with(['account', 'site', 'campaign', 'template', 'creator']);

        if ($request->filled('account_id')) {
            $query->where('publish_account_id', $request->input('account_id'));
        }

        if ($request->filled('site_id')) {
            $query->where('publish_site_id', $request->input('site_id'));
        }

        if ($request->filled('campaign_id')) {
            $query->where('publish_campaign_id', $request->input('campaign_id'));
        }

        $tab = $request->input('tab', 'active');

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        } elseif ($tab === 'deleted') {
            $query->where('status', 'deleted');
        } else {
            $query->where('status', '!=', 'deleted');
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('article_id', 'like', "%{$search}%");
            });
        }

        $articles = $query->orderByDesc('created_at')->paginate(25);

        if ($request->wantsJson() || ($request->filled('page') && $request->header('X-Requested-With') === 'XMLHttpRequest')) {
            return response()->json([
                'html'         => view('app-publish::publishing.articles.partials.article-rows', ['articles' => $articles])->render(),
                'next_page'    => $articles->currentPage() < $articles->lastPage() ? $articles->currentPage() + 1 : null,
                'total'        => $articles->total(),
                'current_page' => $articles->currentPage(),
                'last_page'    => $articles->lastPage(),
            ]);
        }

        $accounts = PublishAccount::orderBy('name')->get();
        $deletedCount = PublishArticle::where('status', 'deleted')->count();

        return view('app-publish::publishing.articles.index', [
            'articles'     => $articles,
            'accounts'     => $accounts,
            'deletedCount' => $deletedCount,
            'statuses' => config('hws-publish.article_statuses', []),
        ]);
    }

    /**
     * Show create article form (standalone one-off).
     *
     * @param Request $request
     * @return View
     */
    public function editor(Request $request, ?int $id = null): View
    {
        $article = $id ? PublishArticle::find($id) : null;
        $drafts = PublishArticle::whereIn('status', ['draft', 'drafting'])
            ->orderByDesc('updated_at')
            ->limit(100)
            ->get(['id', 'title', 'status', 'updated_at']);

        return view('app-publish::publishing.articles.editor', [
            'article' => $article,
            'drafts' => $drafts,
        ]);
    }

    /**
     * @param Request $request
     * @return View
     */
    public function create(Request $request): View
    {
        $accounts = PublishAccount::where('status', 'active')->orderBy('name')->get();
        $sites = PublishSite::where('status', 'connected')->orderBy('name')->get();
        $templates = PublishTemplate::orderBy('name')->get();

        return view('app-publish::publishing.articles.create', [
            'accounts' => $accounts,
            'sites' => $sites,
            'templates' => $templates,
            'articleTypes' => config('hws-publish.article_types', []),
            'aiEngines' => config('hws-publish.ai_engines', []),
            'deliveryModes' => config('hws-publish.campaign_modes', []),
            'preselected_account_id' => $request->input('account_id'),
            'preselected_site_id' => $request->input('site_id'),
        ]);
    }

    /**
     * Store a new standalone article.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(StoreArticleRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $validated['article_id'] = PublishArticle::generateArticleId();
        $validated['status'] = 'drafting';
        $validated['created_by'] = auth()->id();
        $validated['word_count'] = $validated['body'] ? str_word_count(strip_tags($validated['body'])) : 0;

        $article = PublishArticle::create($validated);
        app(ArticleActivityService::class)->record($article, [
            'activity_group' => 'lifecycle:' . ($article->article_id ?: $article->id),
            'activity_type' => 'lifecycle',
            'stage' => 'article',
            'substage' => 'created',
            'status' => $article->status,
            'success' => true,
            'title' => $article->title,
            'message' => 'Standalone article created.',
            'meta' => [
                'delivery_mode' => $article->delivery_mode,
                'article_type' => $article->article_type,
            ],
        ]);

        hexaLog('publish', 'article_created', "Article created: {$article->title} ({$article->article_id})");

        return response()->json([
            'success' => true,
            'message' => "Article created successfully.",
            'article' => $article,
            'redirect' => route('publish.articles.edit', $article->id),
        ]);
    }

    /**
     * Show a single article.
     *
     * @param int $id
     * @return View
     */
    public function show(int $id): View
    {
        $article = PublishArticle::with([
            'account', 'site', 'campaign', 'template', 'creator', 'usedSources',
        ])->findOrFail($id);

        $lifecycle = $this->syncArticleWordPressState($article);

        return view('app-publish::publishing.articles.show', [
            'article' => $article,
            'lifecycle' => $lifecycle,
        ]);
    }

    public function audit(int $id): JsonResponse
    {
        $article = PublishArticle::with([
            'account', 'site', 'campaign', 'template', 'creator', 'usedSources',
        ])->findOrFail($id);

        $service = app(ArticleActivityService::class);

        return response()->json([
            'success' => true,
            'data' => $service->buildAuditDump($article),
            'pretty_text' => $service->buildPrettyAuditText($article),
        ]);
    }

    /**
     * Show the article editor (rich text + AI tools).
     *
     * @param int $id
     * @return View
     */
    public function edit(int $id)
    {
        return redirect()->route('publish.pipeline.v2', ['id' => $id]);
    }

    /**
     * Update an article.
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function update(UpdateArticleRequest $request, int $id): JsonResponse
    {
        $article = $this->findArticle($id);
        $validated = $request->validated();

        if (isset($validated['body'])) {
            $validated['word_count'] = str_word_count(strip_tags($validated['body']));
        }

        $article->update($validated);
        app(ArticleActivityService::class)->record($article, [
            'activity_group' => 'lifecycle:' . ($article->article_id ?: $article->id),
            'activity_type' => 'lifecycle',
            'stage' => 'article',
            'substage' => 'updated',
            'status' => $article->status,
            'success' => true,
            'title' => $article->title,
            'message' => 'Article updated manually.',
            'meta' => [
                'changed_fields' => array_keys($validated),
            ],
        ]);

        hexaLog('publish', 'article_updated', "Article updated: {$article->title} ({$article->article_id})");

        return response()->json([
            'success' => true,
            'message' => "Article saved.",
        ]);
    }

    /**
     * Publish an article to WordPress.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function publish(int $id): JsonResponse
    {
        $article = $this->findArticle($id, ['site']);
        $site = $article->site;
        $wpStatus = ($article->delivery_mode === 'draft-wordpress') ? 'draft' : 'publish';
        $hasExistingPost = !empty($article->wp_post_id);

        $delivery = app(\hexa_app_publish\Publishing\Delivery\Services\WordPressDeliveryService::class);
        $result = $hasExistingPost
            ? $delivery->updatePost($site, (int) $article->wp_post_id, $article->title, $article->body ?? '', $wpStatus, [
                'author' => $article->author ?? $site?->default_author ?? null,
            ])
            : $delivery->createPost($site, $article->title, $article->body ?? '', $wpStatus, [
                'author' => $article->author ?? $site?->default_author ?? null,
            ]);

        if (!$result['success']) {
            app(ArticlePersistenceService::class)->markFailed($article);
            hexaLog('publish', 'article_publish_failed', "Article publish failed: {$article->title} ({$article->article_id}) — {$result['message']}");
            return response()->json($result);
        }

        $persistence = app(\hexa_app_publish\Publishing\Articles\Services\ArticlePersistenceService::class);
        $persistence->updateDeliveryResult($article, $result, $result['post_status'] ?? $wpStatus);
        hexaLog('publish', $hasExistingPost ? 'article_updated' : 'article_published', ($hasExistingPost ? 'Article updated on WordPress: ' : 'Article published: ') . "{$article->title} ({$article->article_id}) → {$site->url} (WP ID: {$result['post_id']})");

        try {
            if (($result['post_status'] ?? $wpStatus) === 'publish') {
                app(TelegramService::class)->notifyPublished($article->title, $site->name, $result['post_url'] ?? null);
            }
        } catch (\Exception $e) {}

        return response()->json([
            'success' => true,
            'message' => (($result['post_status'] ?? $wpStatus) === 'publish'
                ? "Article published to {$site->name}."
                : "WordPress draft updated on {$site->name}.") . " WP Post ID: {$result['post_id']}.",
        ]);
    }

    /**
     * Refresh the article's WordPress state (status, url, published_at) from the live site.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function refreshWp(int $id): JsonResponse
    {
        $article = $this->findArticle($id, ['site']);
        $steps = [];
        $stepStart = microtime(true);
        $step = function (string $label, string $status, ?string $detail = null) use (&$steps, &$stepStart) {
            $steps[] = [
                'label'  => $label,
                'status' => $status, // 'ok' | 'skip' | 'error' | 'info'
                'detail' => $detail,
                'ms'     => (int) round((microtime(true) - $stepStart) * 1000),
            ];
            $stepStart = microtime(true);
        };

        $step('Loading article record', 'ok', "#{$article->id} · " . ($article->article_id ?: 'no ref'));

        if (!$article->site) {
            $step('Resolving WordPress site', 'skip', 'Article has no associated site');
        } else {
            $step('Resolving WordPress site', 'ok', $article->site->name . ' (' . $article->site->url . ')');
        }

        $before = [
            'wp_status'    => $article->wp_status,
            'wp_post_url'  => $article->wp_post_url,
            'status'       => $article->status,
            'delivery'     => $article->delivery_mode,
            'published_at' => $article->published_at?->toIso8601String(),
        ];

        if (!$article->wp_post_id) {
            $step('Checking WordPress post ID', 'skip', 'No wp_post_id — this is still a local draft');
        } else {
            $step('Checking WordPress post ID', 'ok', '#' . $article->wp_post_id);
            $step('Calling WordPress inspectPost', 'info', 'Fetching live post from ' . ($article->site->url ?? 'WordPress'));
        }

        $result = $this->syncArticleWordPressState($article);
        $article->refresh();

        if (!empty($result['sync_error'])) {
            $step('WordPress inspect', 'error', $result['sync_error']);
        } elseif ($article->wp_post_id) {
            $step('WordPress inspect', 'ok', 'Post status: ' . ($result['effective_status'] ?? $article->wp_status ?: '—'));
        }

        $changes = [];
        foreach (['wp_status', 'wp_post_url', 'status', 'delivery_mode'] as $field) {
            $afterValue = $field === 'delivery_mode' ? $article->delivery_mode : $article->{$field};
            $beforeValue = $before[$field === 'delivery_mode' ? 'delivery' : $field] ?? null;
            if ($afterValue !== $beforeValue) {
                $changes[$field] = [$beforeValue, $afterValue];
            }
        }
        $afterPublishedAt = $article->published_at?->toIso8601String();
        if ($afterPublishedAt !== $before['published_at']) {
            $changes['published_at'] = [$before['published_at'], $afterPublishedAt];
        }

        if (empty($changes)) {
            $step('Diffing local vs WordPress', 'ok', 'No field changes — already in sync');
        } else {
            foreach ($changes as $field => [$was, $now]) {
                $step('Updated ' . $field, 'ok', ($was ?: '∅') . ' → ' . ($now ?: '∅'));
            }
        }

        $step('Refresh complete', empty($result['sync_error']) ? 'ok' : 'error');

        return response()->json([
            'success' => empty($result['sync_error']),
            'message' => $result['sync_message'] ?? ($result['sync_error'] ? null : (count($changes) ? 'Synced ' . count($changes) . ' field(s) from WordPress.' : 'Already in sync with WordPress.')),
            'error'   => $result['sync_error'] ?? null,
            'changes' => $changes,
            'steps'   => $steps,
            'article' => [
                'id'           => $article->id,
                'title'        => $article->title,
                'status'       => $article->status,
                'wp_status'    => $article->wp_status,
                'wp_post_url'  => $article->wp_post_url,
                'published_at' => $article->published_at?->toIso8601String(),
            ],
        ]);
    }

    /**
     * Run AI content detection on an article.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function aiCheck(int $id): JsonResponse
    {
        $article = $this->findArticle($id);
        $text = $this->articleText($article->body);

        if (!$this->hasMinimumContent($article->body, 50)) {
            return response()->json(['success' => false, 'message' => 'Article content too short for AI detection.']);
        }

        $result = app(SaplingService::class)->detect($text);

        if ($result['success']) {
            $article->update(['ai_detection_score' => $result['data']['score']]);
            hexaLog('publish', 'article_ai_check', "AI check: {$article->article_id} — score: {$result['data']['score']}%");
        }

        return response()->json([
            'success' => $result['success'],
            'message' => $result['message'],
            'score' => $result['data']['score'] ?? null,
        ]);
    }

    /**
     * Run SEO analysis on an article.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function seoCheck(int $id): JsonResponse
    {
        $article = $this->findArticle($id);
        $html = $article->body ?? '';

        if (!$this->hasMinimumContent($html, 50)) {
            return response()->json(['success' => false, 'message' => 'Article content too short for SEO analysis.']);
        }

        $seoData = app(SeoAnalysisService::class)->analyze($html, $article->title ?? '');
        $article->update(['seo_score' => $seoData['score'], 'seo_data' => $seoData]);
        hexaLog('publish', 'article_seo_check', "SEO check: {$article->article_id} — score: {$seoData['score']}");

        return response()->json([
            'success' => true,
            'message' => "SEO analysis completed. Score: {$seoData['score']}/100.",
            'score' => $seoData['score'],
            'data' => $seoData,
        ]);
    }

    /**
     * Spin/rewrite article content using AI.
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function spin(SpinArticleRequest $request, int $id): JsonResponse
    {
        $article = $this->findArticle($id, ['template', 'site']);
        $validated = $request->validated();

        $body = $article->body;
        if (!$this->hasMinimumContent($body, 20)) {
            return response()->json(['success' => false, 'message' => 'Article has no content to spin.']);
        }

        $instruction = $validated['instruction'] ?? '';
        $articleType = $article->article_type ?? ($article->template->article_type ?? null);
        $tone = $article->template->tone ?? null;
        if (is_array($tone)) {
            $tone = implode(', ', array_values(array_filter(array_map(static fn ($value): string => trim((string) $value), $tone))));
        }
        if (!is_string($tone) || trim($tone) === '') {
            $tone = null;
        }

        // Prepend template AI prompt if available
        if ($article->template && $article->template->ai_prompt) {
            $instruction = $article->template->ai_prompt . "\n\n" . $instruction;
        }

        $article->update(['status' => 'spinning', 'ai_engine_used' => $validated['ai_engine']]);

        $result = match ($validated['ai_engine']) {
            'anthropic' => app(AnthropicService::class)->spinArticle($body, $instruction, $articleType, $tone),
            'chatgpt' => app(ChatGptService::class)->spinArticle($body, $instruction, $articleType, $tone),
            'grok' => app(GrokService::class)->spinArticle($body, $instruction, $articleType, $tone),
            'gemini' => app(GeminiService::class)->spinArticle($body, $instruction, $articleType, $tone),
            default => ['success' => false, 'message' => 'Unsupported AI engine.', 'data' => null],
        };

        if (!$result['success']) {
            $article->update(['status' => 'review']);
            app(ArticleActivityService::class)->record($article, [
                'activity_group' => 'manual-spin:' . ($article->article_id ?: $article->id),
                'activity_type' => 'ai',
                'stage' => 'generation',
                'substage' => 'manual_spin_failed',
                'status' => 'failed',
                'provider' => $validated['ai_engine'],
                'agent' => 'manual-spin',
                'success' => false,
                'title' => $article->title,
                'message' => (string) ($result['message'] ?? 'Spin failed'),
                'request_payload' => [
                    'instruction' => $instruction,
                    'article_type' => $articleType,
                    'tone' => $tone,
                    'body' => $body,
                ],
                'response_payload' => $result,
            ]);
            hexaLog('publish', 'article_spin_failed', "Spin failed: {$article->article_id} — {$result['message']}");
            return response()->json($result);
        }

        $newBody = $result['data']['content'] ?? '';
        $wordCount = str_word_count(strip_tags($newBody));

        $article->update([
            'body' => $newBody,
            'word_count' => $wordCount,
            'status' => 'review',
        ]);
        app(ArticleActivityService::class)->record($article, [
            'activity_group' => 'manual-spin:' . ($article->article_id ?: $article->id),
            'activity_type' => 'ai',
            'stage' => 'generation',
            'substage' => 'manual_spin_complete',
            'status' => 'success',
            'provider' => $validated['ai_engine'],
            'agent' => 'manual-spin',
            'success' => true,
            'title' => $article->title,
            'message' => 'Manual spin completed.',
            'request_payload' => [
                'instruction' => $instruction,
                'article_type' => $articleType,
                'tone' => $tone,
                'body' => $body,
            ],
            'response_payload' => [
                'content' => $newBody,
                'usage' => $result['data']['usage'] ?? null,
                'provider_response' => $result,
            ],
            'meta' => [
                'word_count' => $wordCount,
            ],
        ]);

        hexaLog('publish', 'article_spun', "Article spun: {$article->article_id} via {$validated['ai_engine']} ({$wordCount} words)");

        // Send Telegram approval request if delivery mode is review or notify
        if (in_array($article->delivery_mode, ['review', 'notify'])) {
            try {
                app(TelegramService::class)->sendArticleApproval(
                    $article->id,
                    $article->title ?? 'Untitled',
                    $article->site->name ?? 'Unknown',
                    $wordCount
                );
            } catch (\Exception $e) {
                // Don't fail the spin if Telegram fails
            }
        }

        return response()->json([
            'success' => true,
            'message' => "Article rewritten via {$validated['ai_engine']}. {$wordCount} words.",
            'body' => $newBody,
            'word_count' => $wordCount,
        ]);
    }

    /**
     * Unified photo search across all enabled photo APIs.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function searchPhotos(SearchDiscoveryRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $result = app(MediaSearchService::class)->searchPhotos(
            $validated['query'],
            $validated['sources'] ?? ['pexels', 'unsplash', 'pixabay'],
            $validated['per_page'] ?? 15
        );

        return response()->json([
            'success' => true,
            'results' => $result['photos'],
            'total'   => count($result['photos']),
            'errors'  => $result['errors'],
            'message' => $result['message'],
        ]);
    }

    /**
     * Unified article/news source search across all enabled APIs.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function searchSources(SearchDiscoveryRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $result = app(SourceDiscoveryService::class)->searchArticles($validated['query'], [
            'sources'  => $validated['sources'] ?? ['google-news-rss', 'gnews', 'newsdata'],
            'per_page' => $validated['per_page'] ?? 10,
        ]);

        return response()->json([
            'success' => true,
            'results' => $result['data']['articles'] ?? [],
            'total'   => count($result['data']['articles'] ?? []),
            'errors'  => $result['data']['errors'] ?? [],
            'message' => $result['message'],
        ]);
    }

    /**
     * Scrape a URL and extract article content.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function scrapeUrl(ScrapeUrlRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $result = app(SourceExtractionService::class)->extract($validated['url']);

        return response()->json($result);
    }

    /**
     * Insert links into article content using AI.
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function insertLinks(InsertLinksRequest $request, int $id): JsonResponse
    {
        $article = $this->findArticle($id, ['site']);

        if (!$this->hasMinimumContent($article->body, 50)) {
            return response()->json(['success' => false, 'message' => 'Article has no content for link insertion.']);
        }

        $validated = $request->validated();
        $maxLinks = $validated['max_links'] ?? 5;

        // Get links — either specific IDs or auto-select from account
        if (!empty($validated['link_ids'])) {
            $links = $this->loadSelectedLinks($validated['link_ids']);
        } else {
            $links = app(LinkInsertionService::class)->getAvailableLinks($article->publish_account_id, $maxLinks);
        }

        if (empty($links)) {
            return response()->json(['success' => false, 'message' => 'No active links available for this account.']);
        }

        $result = app(LinkInsertionService::class)->insertLinks($article->body, $links, $maxLinks);

        if ($result['success']) {
            $article->update([
                'body' => $result['data']['html'],
                'links_injected' => $result['data']['report'],
                'word_count' => str_word_count(strip_tags($result['data']['html'])),
            ]);

            hexaLog('publish', 'article_links_inserted', "Links inserted: {$article->article_id} — {$result['message']}");
        }

        return response()->json([
            'success' => $result['success'],
            'message' => $result['message'],
            'report' => $result['data']['report'] ?? [],
            'body' => $result['data']['html'] ?? null,
        ]);
    }

    private function syncArticleWordPressState(PublishArticle $article): array
    {
        $site = $article->site;
        $hasWordPressPost = !empty($article->wp_post_id) && $site;
        $wpAdminUrl = $hasWordPressPost
            ? rtrim((string) $site->url, '/') . '/wp-admin/post.php?post=' . $article->wp_post_id . '&action=edit'
            : null;

        $storedStatus = $article->wp_status ?: null;
        $storedUrl = $article->wp_post_url ?: null;
        $effectiveStatus = $storedStatus;
        $effectiveUrl = $storedUrl;
        $syncMessage = null;
        $syncError = null;

        if ($hasWordPressPost) {
            try {
                $inspection = app(\hexa_app_publish\Publishing\Delivery\Services\WordPressDeliveryService::class)
                    ->inspectPost($site, (int) $article->wp_post_id);

                if ($inspection['success']) {
                    $effectiveStatus = $inspection['post_status'] ?? $effectiveStatus;
                    $effectiveUrl = $inspection['post_url'] ?? $effectiveUrl;

                    $updates = [];
                    if ($effectiveStatus && $effectiveStatus !== $article->wp_status) {
                        $updates['wp_status'] = $effectiveStatus;
                    }
                    if ($effectiveUrl && $effectiveUrl !== $article->wp_post_url) {
                        $updates['wp_post_url'] = $effectiveUrl;
                    }
                    if ($effectiveStatus === 'publish') {
                        if (!$article->published_at) {
                            $updates['published_at'] = !empty($inspection['post_date'])
                                ? \Illuminate\Support\Carbon::parse((string) $inspection['post_date'])
                                : now();
                        }
                        if ($article->delivery_mode === 'draft-wordpress') {
                            $updates['delivery_mode'] = 'auto-publish';
                        }
                        if ($article->status !== 'completed') {
                            $updates['status'] = 'completed';
                        }
                    }

                    if (!empty($updates)) {
                        $article->update($updates);
                        $article->refresh();
                        $storedStatus = $article->wp_status ?: $effectiveStatus;
                        $storedUrl = $article->wp_post_url ?: $effectiveUrl;
                        $syncMessage = 'WordPress state was refreshed from the live site.';
                    }
                } else {
                    $syncError = $inspection['message'] ?? 'Unable to inspect WordPress post.';
                }
            } catch (\Throwable $e) {
                $syncError = $e->getMessage();
            }
        }

        $effectiveStatus = $article->wp_status ?: $effectiveStatus;
        $effectiveUrl = $article->wp_post_url ?: $effectiveUrl;
        $isLive = $effectiveStatus === 'publish';
        $resumeUrl = route('publish.pipeline.v2', ['id' => $article->id]);

        $recommendedAction = !$hasWordPressPost
            ? 'Resume in editor to keep working on this local draft, then prepare and publish when ready.'
            : ($isLive
                ? 'This article is already live. Resume in editor only if you want to revise the same WordPress post.'
                : 'This article already has a WordPress draft. Resume in editor to continue editing and publish that same WordPress draft without creating a duplicate post.');

        return [
            'has_wordpress_post' => $hasWordPressPost,
            'resume_url' => $resumeUrl,
            'wp_admin_url' => $wpAdminUrl,
            'public_url' => $isLive ? $effectiveUrl : null,
            'public_url_label' => $isLive ? 'View Live' : null,
            'stored_status' => $storedStatus,
            'effective_status' => $effectiveStatus,
            'is_live' => $isLive,
            'recommended_action' => $recommendedAction,
            'sync_message' => $syncMessage,
            'sync_error' => $syncError,
        ];
    }

    private function findArticle(int $id, array $relations = []): PublishArticle
    {
        return PublishArticle::with($relations)->findOrFail($id);
    }

    private function articleText(?string $html): string
    {
        return strip_tags($html ?? '');
    }

    private function hasMinimumContent(?string $html, int $minimumLength): bool
    {
        return strlen($this->articleText($html)) >= $minimumLength;
    }

    private function loadSelectedLinks(array $linkIds): array
    {
        return \hexa_app_publish\Models\PublishLinkList::whereIn('id', $linkIds)
            ->where('active', true)
            ->get()
            ->map(fn($link) => [
                'url' => $link->url,
                'anchor_text' => $link->anchor_text,
                'context' => $link->context,
            ])
            ->toArray();
    }
}
