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
use hexa_package_anthropic\Services\AnthropicService;
use hexa_package_chatgpt\Services\ChatGptService;
use hexa_package_sapling\Services\SaplingService;
use hexa_app_publish\Services\WebScraperService;
use hexa_app_publish\Services\LinkInsertionService;
use hexa_package_telegram\Services\TelegramService;
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

            // Telegram notification
            try {
                app(TelegramService::class)->notifyPublished(
                    $article->title,
                    $site->name,
                    $result['data']['post_url'] ?? null
                );
            } catch (\Exception $e) {
                // Don't fail the publish if Telegram fails
            }

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

        $text = strip_tags($article->body ?? '');
        if (strlen($text) < 50) {
            return response()->json(['success' => false, 'message' => 'Article content too short for AI detection.']);
        }

        $result = app(SaplingService::class)->detect($text);

        if ($result['success']) {
            $article->update(['ai_detection_score' => $result['data']['score']]);
            activity_log('publish', 'article_ai_check', "AI check: {$article->article_id} — score: {$result['data']['score']}%");
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
        $article = PublishArticle::findOrFail($id);

        $html = $article->body ?? '';
        $text = strip_tags($html);

        if (strlen($text) < 50) {
            return response()->json(['success' => false, 'message' => 'Article content too short for SEO analysis.']);
        }

        $seoData = $this->analyzeSeo($html, $text, $article->title ?? '');

        $article->update([
            'seo_score' => $seoData['score'],
            'seo_data' => $seoData,
        ]);

        activity_log('publish', 'article_seo_check', "SEO check: {$article->article_id} — score: {$seoData['score']}");

        return response()->json([
            'success' => true,
            'message' => "SEO analysis completed. Score: {$seoData['score']}/100.",
            'score' => $seoData['score'],
            'data' => $seoData,
        ]);
    }

    /**
     * Analyze SEO metrics for article content.
     *
     * @param string $html Raw HTML content.
     * @param string $text Plain text content.
     * @param string $title Article title.
     * @return array
     */
    private function analyzeSeo(string $html, string $text, string $title): array
    {
        $wordCount = str_word_count($text);
        $sentenceCount = max(1, preg_match_all('/[.!?]+/', $text));
        $avgWordsPerSentence = $wordCount / $sentenceCount;

        // Flesch-Kincaid readability
        $syllableCount = $this->countSyllables($text);
        $fleschScore = 206.835 - (1.015 * $avgWordsPerSentence) - (84.6 * ($syllableCount / max(1, $wordCount)));
        $fleschScore = max(0, min(100, $fleschScore));

        // Heading structure
        preg_match_all('/<h([1-6])[^>]*>/i', $html, $headingMatches);
        $headingCount = count($headingMatches[0]);
        $hasH1 = in_array('1', $headingMatches[1] ?? []);
        $hasH2 = in_array('2', $headingMatches[1] ?? []);

        // Links
        preg_match_all('/<a\s/i', $html, $linkMatches);
        $linkCount = count($linkMatches[0]);

        // Images
        preg_match_all('/<img\s/i', $html, $imgMatches);
        $imageCount = count($imgMatches[0]);

        // Images with alt text
        preg_match_all('/<img[^>]+alt\s*=\s*"[^"]+"/i', $html, $imgAltMatches);
        $imagesWithAlt = count($imgAltMatches[0]);

        // Paragraph count
        preg_match_all('/<p[\s>]/i', $html, $pMatches);
        $paragraphCount = count($pMatches[0]);

        // Title length
        $titleLength = strlen($title);
        $titleScore = ($titleLength >= 30 && $titleLength <= 70) ? 10 : ($titleLength > 0 ? 5 : 0);

        // Scoring
        $score = 0;

        // Word count (0-15)
        if ($wordCount >= 800) $score += 15;
        elseif ($wordCount >= 500) $score += 10;
        elseif ($wordCount >= 300) $score += 5;

        // Readability (0-20)
        if ($fleschScore >= 60) $score += 20;
        elseif ($fleschScore >= 40) $score += 15;
        elseif ($fleschScore >= 20) $score += 10;
        else $score += 5;

        // Headings (0-15)
        if ($hasH2 && $headingCount >= 3) $score += 15;
        elseif ($headingCount >= 2) $score += 10;
        elseif ($headingCount >= 1) $score += 5;

        // Links (0-10)
        if ($linkCount >= 3) $score += 10;
        elseif ($linkCount >= 1) $score += 5;

        // Images (0-10)
        if ($imageCount >= 2) $score += 10;
        elseif ($imageCount >= 1) $score += 7;

        // Images with alt (0-10)
        if ($imageCount > 0 && $imagesWithAlt === $imageCount) $score += 10;
        elseif ($imagesWithAlt > 0) $score += 5;

        // Title (0-10)
        $score += $titleScore;

        // Paragraph structure (0-10)
        if ($paragraphCount >= 5) $score += 10;
        elseif ($paragraphCount >= 3) $score += 7;
        elseif ($paragraphCount >= 1) $score += 3;

        return [
            'score' => min(100, $score),
            'word_count' => $wordCount,
            'sentence_count' => $sentenceCount,
            'avg_words_per_sentence' => round($avgWordsPerSentence, 1),
            'flesch_readability' => round($fleschScore, 1),
            'heading_count' => $headingCount,
            'has_h2' => $hasH2,
            'link_count' => $linkCount,
            'image_count' => $imageCount,
            'images_with_alt' => $imagesWithAlt,
            'paragraph_count' => $paragraphCount,
            'title_length' => $titleLength,
        ];
    }

    /**
     * Estimate syllable count in text.
     *
     * @param string $text
     * @return int
     */
    private function countSyllables(string $text): int
    {
        $words = preg_split('/\s+/', strtolower($text));
        $total = 0;

        foreach ($words as $word) {
            $word = preg_replace('/[^a-z]/', '', $word);
            if (strlen($word) <= 3) {
                $total += 1;
                continue;
            }
            $word = preg_replace('/(?:[^laeiouy]es|ed|[^laeiouy]e)$/', '', $word);
            preg_match_all('/[aeiouy]{1,2}/', $word, $matches);
            $total += max(1, count($matches[0]));
        }

        return $total;
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
        $article = PublishArticle::with('template')->findOrFail($id);

        $validated = $request->validate([
            'ai_engine' => 'required|in:anthropic,chatgpt',
            'instruction' => 'nullable|string|max:2000',
        ]);

        $body = $article->body;
        if (!$body || strlen(strip_tags($body)) < 20) {
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
            activity_log('publish', 'article_spin_failed', "Spin failed: {$article->article_id} — {$result['message']}");
            return response()->json($result);
        }

        $newBody = $result['data']['content'] ?? '';
        $wordCount = str_word_count(strip_tags($newBody));

        $article->update([
            'body' => $newBody,
            'word_count' => $wordCount,
            'status' => 'review',
        ]);

        activity_log('publish', 'article_spun', "Article spun: {$article->article_id} via {$validated['ai_engine']} ({$wordCount} words)");

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

    /**
     * Scrape a URL and extract article content.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function scrapeUrl(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'url' => 'required|url|max:2048',
        ]);

        $result = app(WebScraperService::class)->extractArticle($validated['url']);

        return response()->json($result);
    }

    /**
     * Insert links into article content using AI.
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function insertLinks(Request $request, int $id): JsonResponse
    {
        $article = PublishArticle::with('site')->findOrFail($id);

        if (!$article->body || strlen(strip_tags($article->body)) < 50) {
            return response()->json(['success' => false, 'message' => 'Article has no content for link insertion.']);
        }

        $validated = $request->validate([
            'max_links' => 'nullable|integer|min:1|max:20',
            'link_ids' => 'nullable|array',
        ]);

        $maxLinks = $validated['max_links'] ?? 5;

        // Get links — either specific IDs or auto-select from account
        if (!empty($validated['link_ids'])) {
            $links = \hexa_app_publish\Models\PublishLinkList::whereIn('id', $validated['link_ids'])
                ->where('active', true)
                ->get()
                ->map(fn($l) => ['url' => $l->url, 'anchor_text' => $l->anchor_text, 'context' => $l->context])
                ->toArray();
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

            activity_log('publish', 'article_links_inserted', "Links inserted: {$article->article_id} — {$result['message']}");
        }

        return response()->json([
            'success' => $result['success'],
            'message' => $result['message'],
            'report' => $result['data']['report'] ?? [],
            'body' => $result['data']['html'] ?? null,
        ]);
    }
}
