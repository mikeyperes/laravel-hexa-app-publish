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
use hexa_app_publish\Discovery\Sources\Services\SourceDiscoveryService;
use hexa_app_publish\Discovery\Sources\Services\SourceExtractionService;
use hexa_app_publish\Discovery\Media\Services\MediaSearchService;
use hexa_app_publish\Discovery\Links\Services\LinkInsertionService;
use hexa_app_publish\Quality\Detection\Services\SeoAnalysisService;
use hexa_package_anthropic\Services\AnthropicService;
use hexa_package_chatgpt\Services\ChatGptService;
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

        return view('app-publish::publishing.articles.show', [
            'article' => $article,
        ]);
    }

    /**
     * Show the article editor (rich text + AI tools).
     *
     * @param int $id
     * @return View
     */
    public function edit(int $id): View
    {
        $article = PublishArticle::with([
            'account', 'site', 'campaign', 'template', 'usedSources',
        ])->findOrFail($id);

        return view('app-publish::publishing.articles.edit', [
            'article' => $article,
            'articleTypes' => config('hws-publish.article_types', []),
            'aiEngines' => config('hws-publish.ai_engines', []),
        ]);
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

        $delivery = app(\hexa_app_publish\Publishing\Delivery\Services\WordPressDeliveryService::class);
        $result = $delivery->createPost($site, $article->title, $article->body ?? '', $wpStatus);

        if (!$result['success']) {
            $article->update(['status' => 'failed']);
            hexaLog('publish', 'article_publish_failed', "Article publish failed: {$article->title} ({$article->article_id}) — {$result['message']}");
            return response()->json($result);
        }

        $persistence = app(\hexa_app_publish\Publishing\Articles\Services\ArticlePersistenceService::class);
        $persistence->updateDeliveryResult($article, $result, $wpStatus);
        hexaLog('publish', 'article_published', "Article published: {$article->title} ({$article->article_id}) → {$site->url} (WP ID: {$result['post_id']})");

        try {
            app(TelegramService::class)->notifyPublished($article->title, $site->name, $result['post_url'] ?? null);
        } catch (\Exception $e) {}

        return response()->json([
            'success' => true,
            'message' => "Article published to {$site->name} as {$wpStatus}. WP Post ID: {$result['post_id']}.",
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

        // Prepend template AI prompt if available
        if ($article->template && $article->template->ai_prompt) {
            $instruction = $article->template->ai_prompt . "\n\n" . $instruction;
        }

        $article->update(['status' => 'spinning', 'ai_engine_used' => $validated['ai_engine']]);

        if ($validated['ai_engine'] === 'anthropic') {
            $result = app(AnthropicService::class)->spinArticle($body, $instruction, $articleType, $tone);
        } else {
            $result = app(ChatGptService::class)->spinArticle($body, $instruction, $articleType, $tone);
        }

        if (!$result['success']) {
            $article->update(['status' => 'review']);
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
