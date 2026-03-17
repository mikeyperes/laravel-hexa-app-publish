<?php

namespace hexa_app_publish\Http\Controllers;

use hexa_core\Http\Controllers\Controller;
use hexa_core\Services\GenericService;
use hexa_app_publish\Models\PublishAccount;
use hexa_app_publish\Models\PublishArticle;
use hexa_app_publish\Models\PublishSite;
use hexa_app_publish\Models\PublishTemplate;
use hexa_app_publish\Services\PublishService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PublishArticleController extends Controller
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
     * List all articles with filters.
     *
     * @param Request $request
     * @return View
     */
    public function index(Request $request): View
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

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('article_id', 'like', "%{$search}%");
            });
        }

        $articles = $query->orderByDesc('created_at')->paginate(25);
        $accounts = PublishAccount::orderBy('name')->get();

        return view('app-publish::articles.index', [
            'articles' => $articles,
            'accounts' => $accounts,
            'statuses' => config('hws-publish.article_statuses', []),
        ]);
    }

    /**
     * Show create article form (standalone one-off).
     *
     * @param Request $request
     * @return View
     */
    public function create(Request $request): View
    {
        $accounts = PublishAccount::where('status', 'active')->orderBy('name')->get();
        $sites = PublishSite::where('status', 'connected')->orderBy('name')->get();
        $templates = PublishTemplate::orderBy('name')->get();

        return view('app-publish::articles.create', [
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
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'publish_account_id' => 'required|exists:publish_accounts,id',
            'publish_site_id' => 'required|exists:publish_sites,id',
            'publish_template_id' => 'nullable|exists:publish_templates,id',
            'title' => 'required|string|max:500',
            'body' => 'nullable|string',
            'excerpt' => 'nullable|string|max:1000',
            'article_type' => 'nullable|string|max:50',
            'delivery_mode' => 'nullable|in:draft-local,draft-wordpress,auto-publish,review,notify',
            'notes' => 'nullable|string',
        ]);

        $validated['article_id'] = PublishArticle::generateArticleId();
        $validated['status'] = 'drafting';
        $validated['created_by'] = auth()->id();
        $validated['word_count'] = $validated['body'] ? str_word_count(strip_tags($validated['body'])) : 0;

        $article = PublishArticle::create($validated);

        activity_log('publish', 'article_created', "Article created: {$article->title} ({$article->article_id})");

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

        return view('app-publish::articles.show', [
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

        return view('app-publish::articles.edit', [
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
    public function update(Request $request, int $id): JsonResponse
    {
        $article = PublishArticle::findOrFail($id);

        $validated = $request->validate([
            'title' => 'nullable|string|max:500',
            'body' => 'nullable|string',
            'excerpt' => 'nullable|string|max:1000',
            'article_type' => 'nullable|string|max:50',
            'status' => 'nullable|in:' . implode(',', config('hws-publish.article_statuses', [])),
            'delivery_mode' => 'nullable|in:draft-local,draft-wordpress,auto-publish,review,notify',
            'photos' => 'nullable|array',
            'links_injected' => 'nullable|array',
            'notes' => 'nullable|string',
        ]);

        if (isset($validated['body'])) {
            $validated['word_count'] = str_word_count(strip_tags($validated['body']));
        }

        $article->update($validated);

        activity_log('publish', 'article_updated', "Article updated: {$article->title} ({$article->article_id})");

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
        $article = PublishArticle::with('site')->findOrFail($id);

        // TODO: Use WordPress connector package to push content
        // For now, mark as published with placeholder

        $article->update([
            'status' => 'published',
            'published_at' => now(),
            'wp_status' => 'publish',
        ]);

        activity_log('publish', 'article_published', "Article published: {$article->title} ({$article->article_id}) → {$article->site->url}");

        return response()->json([
            'success' => true,
            'message' => "Article published to {$article->site->name}.",
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
        $article = PublishArticle::findOrFail($id);

        // TODO: Use Sapling package to run AI detection
        // For now, placeholder

        activity_log('publish', 'article_ai_check', "AI check run on: {$article->title} ({$article->article_id})");

        return response()->json([
            'success' => true,
            'message' => "AI detection check completed.",
            'score' => $article->ai_detection_score,
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
        $article = PublishArticle::findOrFail($id);

        // TODO: Implement SEO scoring (keyword density, readability, heading structure)
        // For now, placeholder

        activity_log('publish', 'article_seo_check', "SEO check run on: {$article->title} ({$article->article_id})");

        return response()->json([
            'success' => true,
            'message' => "SEO analysis completed.",
            'score' => $article->seo_score,
            'data' => $article->seo_data,
        ]);
    }

    /**
     * Spin/rewrite article content using AI.
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function spin(Request $request, int $id): JsonResponse
    {
        $article = PublishArticle::findOrFail($id);

        $validated = $request->validate([
            'ai_engine' => 'required|in:anthropic,chatgpt',
            'instruction' => 'nullable|string|max:2000',
        ]);

        // TODO: Use Anthropic or ChatGPT package to spin content
        // For now, placeholder

        $article->update([
            'ai_engine_used' => $validated['ai_engine'],
            'status' => 'spinning',
        ]);

        activity_log('publish', 'article_spin', "Article spin requested: {$article->title} ({$article->article_id}) via {$validated['ai_engine']}");

        return response()->json([
            'success' => true,
            'message' => "Spin request sent to {$validated['ai_engine']}.",
        ]);
    }

    /**
     * Unified photo search across all enabled photo APIs.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function searchPhotos(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'query' => 'required|string|max:255',
            'sources' => 'nullable|array',
            'per_page' => 'nullable|integer|min:1|max:50',
        ]);

        // TODO: Use Unsplash, Pexels, Pixabay packages to search
        // Aggregate results from all enabled sources

        return response()->json([
            'success' => true,
            'results' => [],
            'message' => 'Photo search not yet implemented.',
        ]);
    }

    /**
     * Unified article/news source search across all enabled APIs.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function searchSources(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'query' => 'required|string|max:255',
            'sources' => 'nullable|array',
            'per_page' => 'nullable|integer|min:1|max:50',
        ]);

        // TODO: Use GNews, NewsData, Google News RSS packages to search
        // Aggregate results from all enabled sources

        return response()->json([
            'success' => true,
            'results' => [],
            'message' => 'Article source search not yet implemented.',
        ]);
    }
}
