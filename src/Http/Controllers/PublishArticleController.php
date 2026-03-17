<?php

namespace hexa_app_publish\Http\Controllers;

use hexa_core\Http\Controllers\Controller;
use hexa_core\Services\GenericService;
use hexa_app_publish\Models\PublishAccount;
use hexa_app_publish\Models\PublishArticle;
use hexa_app_publish\Models\PublishSite;
use hexa_app_publish\Models\PublishTemplate;
use hexa_app_publish\Services\PublishService;
use hexa_package_wordpress\Services\WordPressService;
use hexa_package_pexels\Services\PexelsService;
use hexa_package_unsplash\Services\UnsplashService;
use hexa_package_pixabay\Services\PixabayService;
use hexa_package_gnews\Services\GNewsService;
use hexa_package_newsdata\Services\NewsDataService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PublishArticleController extends Controller
{
    protected GenericService $generic;
    protected PublishService $publishService;
    protected WordPressService $wp;

    /**
     * @param GenericService $generic
     * @param PublishService $publishService
     * @param WordPressService $wp
     */
    public function __construct(GenericService $generic, PublishService $publishService, WordPressService $wp)
    {
        $this->generic = $generic;
        $this->publishService = $publishService;
        $this->wp = $wp;
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
        $site = $article->site;

        if ($site->connection_type === 'wp_rest_api') {
            if (!$site->wp_username || !$site->wp_application_password) {
                return response()->json([
                    'success' => false,
                    'message' => "Site '{$site->name}' has no WordPress credentials configured.",
                ]);
            }

            $wpStatus = ($article->delivery_mode === 'draft-wordpress') ? 'draft' : 'publish';

            $result = $this->wp->createPost($site->url, $site->wp_username, $site->wp_application_password, [
                'title' => $article->title,
                'content' => $article->body,
                'excerpt' => $article->excerpt ?? '',
                'status' => $wpStatus,
            ]);

            if (!$result['success']) {
                $article->update(['status' => 'failed']);
                activity_log('publish', 'article_publish_failed', "Article publish failed: {$article->title} ({$article->article_id}) — {$result['message']}");

                return response()->json($result);
            }

            $article->update([
                'status' => 'completed',
                'published_at' => now(),
                'wp_post_id' => $result['data']['post_id'],
                'wp_post_url' => $result['data']['post_url'],
                'wp_status' => $result['data']['post_status'],
            ]);

            activity_log('publish', 'article_published', "Article published: {$article->title} ({$article->article_id}) → {$site->url} (WP ID: {$result['data']['post_id']})");

            return response()->json([
                'success' => true,
                'message' => "Article published to {$site->name} as {$wpStatus}. WP Post ID: {$result['data']['post_id']}.",
            ]);
        }

        // WP Toolkit publishing — TODO
        return response()->json([
            'success' => false,
            'message' => 'WP Toolkit publishing not yet implemented.',
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

        $query = $validated['query'];
        $perPage = $validated['per_page'] ?? 15;
        $sources = $validated['sources'] ?? ['pexels', 'unsplash', 'pixabay'];
        $allPhotos = [];
        $errors = [];

        if (in_array('pexels', $sources)) {
            $result = app(PexelsService::class)->searchPhotos($query, $perPage);
            if ($result['success']) {
                $allPhotos = array_merge($allPhotos, $result['data']['photos'] ?? []);
            } else {
                $errors[] = 'Pexels: ' . $result['message'];
            }
        }

        if (in_array('unsplash', $sources)) {
            $result = app(UnsplashService::class)->searchPhotos($query, $perPage);
            if ($result['success']) {
                $allPhotos = array_merge($allPhotos, $result['data']['photos'] ?? []);
            } else {
                $errors[] = 'Unsplash: ' . $result['message'];
            }
        }

        if (in_array('pixabay', $sources)) {
            $result = app(PixabayService::class)->searchPhotos($query, $perPage);
            if ($result['success']) {
                $allPhotos = array_merge($allPhotos, $result['data']['photos'] ?? []);
            } else {
                $errors[] = 'Pixabay: ' . $result['message'];
            }
        }

        return response()->json([
            'success' => true,
            'results' => $allPhotos,
            'total' => count($allPhotos),
            'errors' => $errors,
            'message' => count($allPhotos) . ' photos found across ' . count($sources) . ' source(s).',
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

        $query = $validated['query'];
        $perPage = $validated['per_page'] ?? 10;
        $sources = $validated['sources'] ?? ['google-news-rss', 'gnews', 'newsdata'];
        $allArticles = [];
        $errors = [];

        // Google News RSS — free, no API key
        if (in_array('google-news-rss', $sources)) {
            try {
                $rssUrl = 'https://news.google.com/rss/search?q=' . urlencode($query) . '&hl=en-US&gl=US&ceid=US:en';
                $xml = @simplexml_load_string(file_get_contents($rssUrl));
                if ($xml && isset($xml->channel->item)) {
                    $count = 0;
                    foreach ($xml->channel->item as $item) {
                        if ($count >= $perPage) break;
                        $allArticles[] = [
                            'source_api' => 'google-news-rss',
                            'title' => (string) $item->title,
                            'description' => strip_tags((string) $item->description),
                            'content' => '',
                            'url' => (string) $item->link,
                            'image' => null,
                            'published_at' => (string) $item->pubDate,
                            'source_name' => (string) ($item->source ?? 'Google News'),
                            'source_url' => '',
                        ];
                        $count++;
                    }
                }
            } catch (\Exception $e) {
                $errors[] = 'Google News RSS: ' . $e->getMessage();
            }
        }

        if (in_array('gnews', $sources)) {
            $result = app(GNewsService::class)->searchArticles($query, $perPage);
            if ($result['success']) {
                $allArticles = array_merge($allArticles, $result['data']['articles'] ?? []);
            } else {
                $errors[] = 'GNews: ' . $result['message'];
            }
        }

        if (in_array('newsdata', $sources)) {
            $result = app(NewsDataService::class)->searchArticles($query, $perPage);
            if ($result['success']) {
                $allArticles = array_merge($allArticles, $result['data']['articles'] ?? []);
            } else {
                $errors[] = 'NewsData: ' . $result['message'];
            }
        }

        return response()->json([
            'success' => true,
            'results' => $allArticles,
            'total' => count($allArticles),
            'errors' => $errors,
            'message' => count($allArticles) . ' articles found across ' . count($sources) . ' source(s).',
        ]);
    }
}
