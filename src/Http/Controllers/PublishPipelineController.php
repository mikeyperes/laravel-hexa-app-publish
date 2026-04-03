<?php

namespace hexa_app_publish\Http\Controllers;

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
use hexa_package_anthropic\Services\AnthropicService;
use Illuminate\Support\Str;
use hexa_package_article_extractor\Services\ArticleExtractorService;
use hexa_package_whm\Models\HostingAccount;
use hexa_package_whm\Models\WhmServer;
use hexa_package_wordpress\Services\WordPressService;
use hexa_package_wptoolkit\Services\WpToolkitService;
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
class PublishPipelineController extends Controller
{
    protected ArticleExtractorService $extractor;
    protected AnthropicService $anthropic;
    protected WordPressService $wp;
    protected WpToolkitService $wptoolkit;

    /** Connection mode constants */
    const WP_MODE_REST = 'wp_rest_api';
    const WP_MODE_SSH  = 'wptoolkit';

    /**
     * @param ArticleExtractorService $extractor
     * @param AnthropicService        $anthropic
     * @param WordPressService        $wp
     * @param WpToolkitService        $wptoolkit
     */
    public function __construct(
        ArticleExtractorService $extractor,
        AnthropicService $anthropic,
        WordPressService $wp,
        WpToolkitService $wptoolkit
    ) {
        $this->extractor = $extractor;
        $this->anthropic = $anthropic;
        $this->wp = $wp;
        $this->wptoolkit = $wptoolkit;
    }

    /**
     * Resolve the WHM server for a WP Toolkit site.
     *
     * @param PublishSite $site
     * @return array{server: WhmServer|null, account: HostingAccount|null}
     */
    private function resolveWpToolkitServer(PublishSite $site): array
    {
        $account = HostingAccount::find($site->hosting_account_id);
        $server = $account ? WhmServer::find($account->whm_server_id) : null;
        return ['server' => $server, 'account' => $account];
    }

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
        $newsCategories = \DB::table('lists')->where('list_key', 'news_categories')->where('is_active', true)->orderBy('sort_order')->pluck('list_value');

        $draftUser = $draft->created_by ? \hexa_core\Models\User::find($draft->created_by) : null;

        return view('app-publish::article.pipeline.index', [
            'sites'          => $sites,
            'draftId'        => $draft->id,
            'newsCategories' => $newsCategories,
            'draftUser'      => $draftUser,
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

        $urls = $validated['urls'];
        $userAgent = $validated['user_agent'] ?? 'chrome';
        $method = $validated['method'] ?? 'auto';
        $retries = $validated['retries'] ?? 1;
        $timeout = $validated['timeout'] ?? 20;
        $minWords = $validated['min_words'] ?? 50;
        $autoFallback = $validated['auto_fallback'] ?? true;
        $results = [];
        $passCount = 0;

        foreach ($urls as $url) {
            $extraction = $this->extractor->extract($url, $method, null, [
                'user_agent' => $userAgent,
                'retries'    => $retries,
                'timeout'    => $timeout,
                'min_words'  => $minWords,
            ]);

            // Auto-fallback: if failed and enabled, retry with googlebot UA
            if (!$extraction['success'] && $autoFallback && $userAgent !== 'googlebot') {
                $extraction = $this->extractor->extract($url, $method, null, [
                    'user_agent' => 'googlebot',
                    'retries'    => $retries,
                    'timeout'    => $timeout,
                    'min_words'  => $minWords,
                ]);
                if ($extraction['success']) {
                    $extraction['message'] = 'Extracted via fallback (Googlebot). ' . $extraction['message'];
                }
            }

            $results[] = [
                'url'            => $url,
                'success'        => $extraction['success'],
                'message'        => $extraction['message'],
                'title'          => $extraction['data']['title'] ?? '',
                'word_count'     => $extraction['data']['word_count'] ?? 0,
                'text'           => $extraction['data']['content_text'] ?? '',
                'formatted_html' => $extraction['data']['content_formatted'] ?? '',
                'fetch_info'     => $extraction['fetch_info'] ?? null,
            ];

            if ($extraction['success']) {
                $passCount++;
            }
        }

        return response()->json([
            'success' => true,
            'message' => "{$passCount} of " . count($urls) . " sources verified.",
            'results' => $results,
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

        // Load master spin prompt (or use default)
        $masterPrompt = PublishMasterSetting::where('type', 'master_spin_prompt')
            ->where('is_active', true)
            ->value('content');

        if (empty($masterPrompt)) {
            $masterPrompt = "You are a professional content writer. Rewrite the provided source articles into a single new unique article.\n\n{custom_instructions}\n\n{wordpress_guidelines}\n\n{spinning_guidelines}\n\n{preset_config}\n\n{template_config}\n\nCRITICAL OUTPUT FORMAT: You MUST output valid HTML only. Do NOT include an <h1> title. Start with <h2> for section headings. Use <p> for paragraphs. Use <strong> and <em> for emphasis. Use <ul>/<ol>/<li> for lists. Use <blockquote> for quotes. Use <a href=\"\"> for links. Do NOT output markdown.\n\nSUPPORTING LINKS: Include 3-5 relevant external links within the article using <a href=\"URL\" target=\"_blank\"> tags. These should link to real, credible news articles, official sources, government sites, or research that support the claims being made. Do NOT link to the source articles provided — find independent supporting references.\n\nPHOTO PLACEMENT: Insert HTML comments for photos: <!-- PHOTO: descriptive search term | alt text description -->. Place {photo_count} photo markers at natural breaking points. Search terms must be specific and visual — match commonly available stock photo subjects, avoid niche historical or overly specific terms. Alt text under 125 characters.\n\nFEATURED IMAGE: Also output one line: <!-- FEATURED: descriptive search term for the article featured image -->\n\nMETADATA: At the very end of your response, output a JSON block:\n<!-- METADATA: {\"titles\":[\"title1\",\"title2\",...10 titles],\"categories\":[\"cat1\",\"cat2\",...15 categories],\"tags\":[\"tag1\",\"tag2\",...15 tags],\"description\":\"A 1-2 sentence SEO meta description summarizing the article\"} -->\n\nThe titles should be compelling and SEO-friendly. Categories are broad topics. Tags are specific keywords. Description is a concise meta description for SEO (under 160 characters).\n\n{source_articles}";
        }

        // Load settings for shortcode replacement
        $masterSettings = PublishMasterSetting::where('is_active', true)->orderBy('sort_order')->get();

        // Build shortcode values
        $wpGuidelines = $masterSettings->where('type', 'wordpress_guidelines')->pluck('content')->implode("\n\n");
        $spinGuidelines = $masterSettings->where('type', 'spinning_guidelines')->pluck('content')->implode("\n\n");

        $presetConfig = '';
        if (!empty($validated['preset_id'])) {
            $preset = PublishPreset::find($validated['preset_id']);
            if ($preset) {
                $parts = [];
                if ($preset->tone) $parts[] = "Tone: {$preset->tone}";
                if ($preset->article_format) $parts[] = "Format: {$preset->article_format}";
                if ($preset->follow_links) $parts[] = "Links: {$preset->follow_links}";
                if ($preset->image_preference) $parts[] = "Images: {$preset->image_preference}";
                $presetConfig = implode("\n", $parts);
            }
        }

        $templateConfig = '';
        $photoCount = '2-4';
        if (!empty($validated['template_id'])) {
            $template = PublishTemplate::find($validated['template_id']);
            if ($template) {
                $parts = [];
                if ($template->ai_prompt) $parts[] = $template->ai_prompt;
                if ($template->tone) $parts[] = "Tone: " . (is_array($template->tone) ? implode(', ', $template->tone) : $template->tone);
                if ($template->article_type) $parts[] = "Article type: {$template->article_type}";
                if ($template->word_count_min || $template->word_count_max) $parts[] = "Target words: {$template->word_count_min}-{$template->word_count_max}";
                $templateConfig = implode("\n", $parts);
                if ($template->photos_per_article) $photoCount = (string) $template->photos_per_article;
            }
        }

        // Build source articles text
        $sourceTextsStr = '';
        if (!empty($validated['change_request'])) {
            $sourceTextsStr = "Below is an existing article. Apply the following changes:\n\nChanges requested: {$validated['change_request']}\n\n=== Current Article ===\n{$validated['source_texts'][0]}";
        } else {
            $sourceTextsStr = "Below are the source articles to spin into a new unique article:\n";
            foreach ($validated['source_texts'] as $i => $text) {
                $num = $i + 1;
                $sourceTextsStr .= "\n=== Source {$num} ===\n{$text}";
            }
        }

        // Replace all shortcodes
        $systemPrompt = str_replace([
            '{custom_instructions}',
            '{wordpress_guidelines}',
            '{spinning_guidelines}',
            '{preset_config}',
            '{template_config}',
            '{photo_count}',
            '{source_articles}',
            '{featured_image_preference}',
        ], [
            !empty($validated['custom_prompt']) ? "=== PRIORITY INSTRUCTIONS ===\n{$validated['custom_prompt']}" : '',
            $wpGuidelines ? "=== WordPress Guidelines ===\n{$wpGuidelines}" : '',
            $spinGuidelines ? "=== Spinning Guidelines ===\n{$spinGuidelines}" : '',
            $presetConfig ? "=== Preset Config ===\n{$presetConfig}" : '',
            $templateConfig ? "=== Template Config ===\n{$templateConfig}" : '',
            $photoCount,
            $sourceTextsStr,
            $preset->image_preference ?? '',
        ], $masterPrompt);

        // Clean up empty sections (double newlines from empty shortcodes)
        $systemPrompt = preg_replace("/\n{3,}/", "\n\n", trim($systemPrompt));

        $userMessage = 'Generate the article now.';
        $model = $validated['model'];

        $result = $this->anthropic->chat($systemPrompt, $userMessage, $model, 8192);

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['message'],
            ]);
        }

        $content = $result['data']['content'] ?? '';

        // Strip ```html code blocks if AI wrapped output
        $content = preg_replace('/^```html\s*/i', '', $content);
        $content = preg_replace('/\s*```$/', '', $content);

        // Strip full HTML document wrapper — extract body content only
        if (preg_match('/<body[^>]*>(.*)<\/body>/is', $content, $bodyMatch)) {
            $content = trim($bodyMatch[1]);
        }
        // Also strip <html>, <head>, <title>, <!DOCTYPE> if no <body> tag
        $content = preg_replace('/<!DOCTYPE[^>]*>/i', '', $content);
        $content = preg_replace('/<\/?html[^>]*>/i', '', $content);
        $content = preg_replace('/<head>.*?<\/head>/is', '', $content);
        $content = trim($content);

        // Strip H1 from body — title is handled separately
        $content = preg_replace('/<h1[^>]*>.*?<\/h1>/is', '', $content, 1);
        $content = trim($content);

        // Markdown → HTML fallback: detect and convert if AI returned markdown
        if (preg_match('/^#{1,6}\s|^\*\*|^\- |\n#{1,6}\s/m', $content)) {
            // Headings: ## Title → <h2>Title</h2>
            $content = preg_replace('/^######\s+(.+)$/m', '<h6>$1</h6>', $content);
            $content = preg_replace('/^#####\s+(.+)$/m', '<h5>$1</h5>', $content);
            $content = preg_replace('/^####\s+(.+)$/m', '<h4>$1</h4>', $content);
            $content = preg_replace('/^###\s+(.+)$/m', '<h3>$1</h3>', $content);
            $content = preg_replace('/^##\s+(.+)$/m', '<h2>$1</h2>', $content);
            $content = preg_replace('/^#\s+(.+)$/m', '<h1>$1</h1>', $content);
            // Bold: **text** → <strong>text</strong>
            $content = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $content);
            // Italic: *text* → <em>text</em>
            $content = preg_replace('/(?<!\*)\*(?!\*)(.+?)(?<!\*)\*(?!\*)/', '<em>$1</em>', $content);
            // Unordered lists: - item → <li>item</li>
            $content = preg_replace('/^[\-\*]\s+(.+)$/m', '<li>$1</li>', $content);
            $content = preg_replace('/(<li>.*<\/li>\n?)+/', '<ul>$0</ul>', $content);
            // Links: [text](url) → <a href="url">text</a>
            $content = preg_replace('/\[([^\]]+)\]\(([^\)]+)\)/', '<a href="$2">$1</a>', $content);
            // Paragraphs: wrap remaining plain text blocks in <p> tags
            $lines = explode("\n\n", $content);
            $content = implode("\n", array_map(function ($block) {
                $block = trim($block);
                if (empty($block)) return '';
                if (preg_match('/^<(h[1-6]|ul|ol|li|blockquote|div|p|table)/', $block)) return $block;
                return '<p>' . str_replace("\n", '<br>', $block) . '</p>';
            }, $lines));
        }

        // Extract photo placement suggestions from HTML comments
        // Supports: <!-- PHOTO: search | alt | caption | filename --> or legacy <!-- PHOTO: search | alt -->
        $photoSuggestions = [];
        if (preg_match_all('/<!--\s*PHOTO:\s*(.+?)\s*-->/', $content, $photoMatches, PREG_SET_ORDER)) {
            foreach ($photoMatches as $i => $match) {
                $parts = array_map('trim', explode('|', $match[1]));
                $searchTerm = $parts[0] ?? '';
                $altText = $parts[1] ?? $searchTerm;
                $caption = $parts[2] ?? '';
                $seoFilename = $parts[3] ?? Str::slug($searchTerm);
                $photoSuggestions[] = [
                    'search_term' => $searchTerm,
                    'alt_text' => $altText,
                    'caption' => $caption,
                    'suggestedFilename' => $seoFilename,
                    'position' => $i,
                ];
                $placeholder = '<div class="photo-placeholder" contenteditable="false" data-idx="' . $i . '" data-search="' . htmlspecialchars($searchTerm) . '" data-caption="' . htmlspecialchars($altText) . '" style="border:2px dashed #a78bfa;background:#f5f3ff;border-radius:8px;padding:12px 16px;margin:16px 0;cursor:pointer;text-align:center;color:#7c3aed;font-size:14px;">'
                    . '<span style="font-size:13px;">Loading photo...</span>'
                    . '</div>';
                $content = preg_replace('/<!--\s*PHOTO:\s*' . preg_quote($match[1], '/') . '\s*-->/', $placeholder, $content, 1);
            }
        }

        // Extract FEATURED image suggestion
        $featuredImage = null;
        if (preg_match('/<!--\s*FEATURED:\s*(.+?)\s*-->/', $content, $featuredMatch)) {
            $featuredImage = trim($featuredMatch[1]);
            $content = preg_replace('/<!--\s*FEATURED:\s*.+?\s*-->/', '', $content);
        }

        // Extract METADATA (titles, categories, tags)
        $metadata = ['titles' => [], 'categories' => [], 'tags' => []];
        if (preg_match('/<!--\s*METADATA:\s*(\{.+?\})\s*-->/s', $content, $metaMatch)) {
            $parsed = json_decode(trim($metaMatch[1]), true);
            if ($parsed) $metadata = array_merge($metadata, $parsed);
            $content = preg_replace('/<!--\s*METADATA:\s*\{.+?\}\s*-->/s', '', $content);
        }

        $content = trim($content);
        $plainText = strip_tags($content);
        $wordCount = str_word_count($plainText);

        // Log AI activity
        $usage = $result['data']['usage'] ?? [];
        $apiKey = \hexa_core\Models\Setting::getValue('anthropic_api_key', '');
        AiActivityLog::logCall([
            'provider'         => 'anthropic',
            'model'            => $validated['model'],
            'agent'            => !empty($validated['change_request']) ? 'pipeline-revise' : 'pipeline-spin',
            'prompt_tokens'    => $usage['input_tokens'] ?? 0,
            'completion_tokens' => $usage['output_tokens'] ?? 0,
            'system_prompt'    => mb_substr($systemPrompt, 0, 5000),
            'user_message'     => mb_substr($userMessage, 0, 5000),
            'response_content' => mb_substr($content, 0, 10000),
            'success'          => true,
            'api_key_masked'   => $apiKey ? '...' . substr($apiKey, -4) : null,
        ]);

        // Calculate cost for response
        $pricing = [
            'claude-opus-4-6' => ['input' => 15.0, 'output' => 75.0],
            'claude-opus-4-20250514' => ['input' => 15.0, 'output' => 75.0],
            'claude-sonnet-4-6' => ['input' => 3.0, 'output' => 15.0],
            'claude-sonnet-4-20250514' => ['input' => 3.0, 'output' => 15.0],
            'claude-haiku-4-5-20251001' => ['input' => 0.80, 'output' => 4.0],
        ];
        $rates = $pricing[$model] ?? ['input' => 0, 'output' => 0];
        $inputTokens = $usage['input_tokens'] ?? 0;
        $outputTokens = $usage['output_tokens'] ?? 0;
        $cost = ($inputTokens * $rates['input'] / 1_000_000) + ($outputTokens * $rates['output'] / 1_000_000);

        return response()->json([
            'success'    => true,
            'message'    => "Article generated: {$wordCount} words.",
            'html'       => $content,
            'text'       => $plainText,
            'word_count' => $wordCount,
            'usage'      => $usage,
            'model'      => $result['data']['model'] ?? $model,
            'cost'       => round($cost, 6),
            'provider'   => 'anthropic',
            'user_name'  => auth()->user()?->name ?? 'System',
            'ip'         => request()->ip(),
            'timestamp_utc' => now()->utc()->format('Y-m-d H:i:s'),
            'photo_suggestions' => $photoSuggestions,
            'featured_image' => $featuredImage,
            'metadata' => $metadata,
            'resolved_prompt' => $systemPrompt,
        ]);
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

        $articleText = strip_tags($validated['article_html']);
        $prompt = "Based on this article, generate exactly:\n\n1. 10 unique title options (compelling, SEO-friendly)\n2. 15 category suggestions (broad topics)\n3. 15 tag suggestions (specific keywords)\n\nArticle:\n" . mb_substr($articleText, 0, 3000) . "\n\nRespond ONLY in this exact JSON format, no other text:\n{\"titles\":[\"title1\",...],\"categories\":[\"cat1\",...],\"tags\":[\"tag1\",...]}";

        $result = $this->anthropic->chat(
            'You are a content metadata expert. Output ONLY valid JSON. No markdown, no explanation.',
            $prompt,
            'claude-haiku-4-5-20251001',
            1024
        );

        if (!$result['success']) {
            return response()->json(['success' => false, 'message' => $result['message']]);
        }

        $content = $result['data']['content'] ?? '';
        // Extract JSON from response
        $content = preg_replace('/^```json\s*/i', '', $content);
        $content = preg_replace('/\s*```$/', '', $content);

        $parsed = json_decode(trim($content), true);

        if (!$parsed || !isset($parsed['titles'])) {
            return response()->json(['success' => false, 'message' => 'Failed to parse AI response.', 'raw' => $content]);
        }

        // Log the API call
        $usage = $result['data']['usage'] ?? [];
        $apiKey = \hexa_core\Models\Setting::getValue('anthropic_api_key', '');
        AiActivityLog::logCall([
            'provider' => 'anthropic',
            'model' => 'claude-haiku-4-5-20251001',
            'agent' => 'pipeline-metadata',
            'prompt_tokens' => $usage['input_tokens'] ?? 0,
            'completion_tokens' => $usage['output_tokens'] ?? 0,
            'system_prompt' => 'Content metadata expert',
            'response_content' => $content,
            'success' => true,
            'api_key_masked' => $apiKey ? '...' . substr($apiKey, -4) : null,
        ]);

        return response()->json([
            'success' => true,
            'titles' => array_slice($parsed['titles'] ?? [], 0, 10),
            'categories' => array_slice($parsed['categories'] ?? [], 0, 15),
            'tags' => array_slice($parsed['tags'] ?? [], 0, 15),
            'urls' => array_slice($parsed['urls'] ?? [], 0, 10),
        ]);
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
        ]);

        $site = PublishSite::findOrFail($validated['site_id']);
        $mode = $site->connection_type === self::WP_MODE_SSH ? self::WP_MODE_SSH : self::WP_MODE_REST;
        $wp = $this->wp;
        $wptoolkit = $this->wptoolkit;

        return response()->stream(function () use ($validated, $site, $mode, $wp, $wptoolkit) {
            $send = function (string $type, string $message, array $extra = []) {
                $event = array_merge(['type' => $type, 'message' => $message, 'time' => now()->format('H:i:s')], $extra);
                echo "data: " . json_encode($event) . "\n\n";
                if (ob_get_level()) ob_flush();
                flush();
            };

            $send('info', "Connecting to {$site->name} via {$mode}...");

            // Validate mode
            if ($mode === self::WP_MODE_REST && (!$site->wp_username || !$site->wp_application_password)) {
                $send('error', "Site '{$site->name}' has no WordPress credentials configured.");
                $send('done', 'Failed', ['success' => false]);
                return;
            }

            $server = null;
            $installId = null;
            if ($mode === self::WP_MODE_SSH) {
                $resolved = $this->resolveWpToolkitServer($site);
                $server = $resolved['server'];
                $installId = $site->wordpress_install_id;
                if (!$server || !$installId) {
                    $send('error', "Missing WP Toolkit server or install ID.");
                    $send('done', 'Failed', ['success' => false]);
                    return;
                }
                $send('success', "SSH server resolved: {$server->hostname}");
            } else {
                $send('success', "REST API credentials verified for {$site->wp_username}");
            }

            $html = $validated['html'];
            $siteUrl = $site->url;

            // Step 1: Upload images
            preg_match_all('/<img[^>]+src\s*=\s*["\']([^"\']+)["\'][^>]*>/i', $html, $imgMatches);
            $imageUrls = array_unique($imgMatches[1] ?? []);
            $imageMap = [];
            $wpImages = [];

            if (!empty($imageUrls)) {
                $send('info', "Uploading " . count($imageUrls) . " image(s)...");
                $imgIndex = 0;
                $articleTitle = $validated['title'] ?? 'article';

                // Photo suggestions from frontend (keyed by source URL for caption/description lookup)
                $photoMeta = [];
                foreach ($validated['photo_suggestions'] ?? [] as $ps) {
                    if (!empty($ps['autoPhoto']['url_large'])) {
                        $photoMeta[$ps['autoPhoto']['url_large']] = $ps;
                    }
                }
                $draftId = $validated['draft_id'] ?? 0;

                foreach ($imageUrls as $imgUrl) {
                    if (str_starts_with($imgUrl, rtrim($siteUrl, '/'))) continue;

                    // Look up photo suggestion data for this URL
                    $ps = $photoMeta[$imgUrl] ?? null;
                    $altText = $ps['alt_text'] ?? '';
                    $caption = $ps['caption'] ?? '';
                    $seoName = $ps['suggestedFilename'] ?? '';

                    // Fallback: extract alt from img tag
                    if (!$altText && preg_match('/<img[^>]+src\s*=\s*["\']' . preg_quote($imgUrl, '/') . '["\'][^>]*alt\s*=\s*["\']([^"\']*)["\'][^>]*>/i', $html, $altMatch)) {
                        $altText = $altMatch[1];
                    }

                    // Filename: hexa_{draftId}_{seoname}.ext
                    $slugBase = $seoName ?: Str::limit(Str::slug($altText ?: $articleTitle, '-'), 60, '');
                    $ext = pathinfo(parse_url($imgUrl, PHP_URL_PATH), PATHINFO_EXTENSION) ?: 'jpg';
                    $properFilename = 'hexa_' . $draftId . '_' . $slugBase . '.' . $ext;
                    $description = $altText; // Use alt as description for SEO

                    $send('step', "Uploading: {$properFilename}...");

                    if ($mode === self::WP_MODE_SSH) {
                        $uploadResult = $wptoolkit->wpCliUploadMedia($server, $installId, $imgUrl, $properFilename, $altText, $caption, $description);
                    } else {
                        $uploadResult = $wp->uploadMedia($siteUrl, $site->wp_username, $site->wp_application_password, $imgUrl, $properFilename, $altText);
                    }

                    if ($uploadResult['success'] && !empty($uploadResult['data']['media_url'])) {
                        $imageMap[$imgUrl] = $uploadResult['data']['media_url'];
                        $wpImages[] = $uploadResult['data'];
                        $send('success', "Uploaded: {$properFilename} → " . Str::limit($uploadResult['data']['media_url'], 80), [
                            'wp_image' => $uploadResult['data'],
                        ]);
                    } else {
                        $send('warning', "Failed to upload: {$properFilename} — " . ($uploadResult['message'] ?? 'unknown error'));
                    }
                }
                $send('success', count($imageMap) . "/" . count($imageUrls) . " images uploaded");
            } else {
                $send('step', "No images to upload");
            }

            // Step 2: Replace image URLs
            if (!empty($imageMap)) {
                foreach ($imageMap as $oldUrl => $newUrl) {
                    $html = str_replace($oldUrl, $newUrl, $html);
                }
                $send('success', count($imageMap) . " image URL(s) replaced in HTML");
            }

            // Step 3: Create categories
            $categoryIds = [];
            $requestedCategories = $validated['categories'] ?? [];
            if (!empty($requestedCategories)) {
                $send('info', "Creating " . count($requestedCategories) . " categories...");
                if ($mode === self::WP_MODE_SSH) {
                    $batchResult = $wptoolkit->wpCliBatchCategories($server, $installId, $requestedCategories);
                    $categoryIds = $batchResult['term_ids'] ?? [];
                } else {
                    $existingCats = $wp->getCategories($siteUrl, $site->wp_username, $site->wp_application_password);
                    $existingCatMap = [];
                    if ($existingCats['success']) {
                        foreach ($existingCats['data'] as $cat) $existingCatMap[strtolower($cat['name'])] = $cat['id'];
                    }
                    foreach ($requestedCategories as $catName) {
                        $catNameLower = strtolower(trim($catName));
                        if (isset($existingCatMap[$catNameLower])) {
                            $categoryIds[] = $existingCatMap[$catNameLower];
                        } else {
                            $catResult = $this->wpCreateTaxonomy($siteUrl, $site->wp_username, $site->wp_application_password, 'categories', $catName);
                            if ($catResult) $categoryIds[] = $catResult;
                        }
                    }
                }
                $send('success', count($categoryIds) . "/" . count($requestedCategories) . " categories ready — IDs: " . implode(',', array_slice($categoryIds, 0, 5)) . (count($categoryIds) > 5 ? '...' : ''));
            } else {
                $send('step', "No categories to create");
            }

            // Step 4: Create tags
            $tagIds = [];
            $requestedTags = $validated['tags'] ?? [];
            if (!empty($requestedTags)) {
                $send('info', "Creating " . count($requestedTags) . " tags...");
                if ($mode === self::WP_MODE_SSH) {
                    $batchResult = $wptoolkit->wpCliBatchTags($server, $installId, $requestedTags);
                    $tagIds = $batchResult['term_ids'] ?? [];
                } else {
                    $existingTags = $wp->getTags($siteUrl, $site->wp_username, $site->wp_application_password);
                    $existingTagMap = [];
                    if ($existingTags['success']) {
                        foreach ($existingTags['data'] as $tag) $existingTagMap[strtolower($tag['name'])] = $tag['id'];
                    }
                    foreach ($requestedTags as $tagName) {
                        $tagNameLower = strtolower(trim($tagName));
                        if (isset($existingTagMap[$tagNameLower])) {
                            $tagIds[] = $existingTagMap[$tagNameLower];
                        } else {
                            $tagResult = $this->wpCreateTaxonomy($siteUrl, $site->wp_username, $site->wp_application_password, 'tags', $tagName);
                            if ($tagResult) $tagIds[] = $tagResult;
                        }
                    }
                }
                $send('success', count($tagIds) . "/" . count($requestedTags) . " tags ready");
            } else {
                $send('step', "No tags to create");
            }

            // Step 5: Validate HTML
            $send('info', "Validating HTML...");
            $htmlValid = !empty(trim(strip_tags($html)));
            $send($htmlValid ? 'success' : 'error', $htmlValid ? 'HTML valid' : 'HTML is empty after processing');

            // Final result
            $send('done', $htmlValid ? 'Preparation complete' : 'Preparation failed', [
                'success'      => $htmlValid,
                'html'         => $html,
                'category_ids' => $categoryIds,
                'tag_ids'      => $tagIds,
                'wp_images'    => $wpImages,
            ]);

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

        $mode = $site->connection_type === self::WP_MODE_SSH ? self::WP_MODE_SSH : self::WP_MODE_REST;

        if ($mode === self::WP_MODE_REST && (!$site->wp_username || !$site->wp_application_password)) {
            return response()->json(['success' => false, 'message' => "Site '{$site->name}' has no WordPress credentials."]);
        }

        if ($mode === self::WP_MODE_SSH) {
            $resolved = $this->resolveWpToolkitServer($site);
            $server = $resolved['server'];
            $installId = $site->wordpress_install_id;
            if (!$server || !$installId) {
                return response()->json(['success' => false, 'message' => "Site '{$site->name}' is missing WP Toolkit configuration."]);
            }

            $result = $this->wptoolkit->wpCliCreatePost(
                $server,
                $installId,
                $validated['title'],
                $validated['html'],
                $validated['status'],
                $validated['category_ids'] ?? [],
                $validated['tag_ids'] ?? [],
                ($validated['status'] === 'future' && !empty($validated['date'])) ? $validated['date'] : null
            );
        } else {
            $postData = [
                'title'   => $validated['title'],
                'content' => $validated['html'],
                'status'  => $validated['status'],
            ];
            if (!empty($validated['category_ids'])) $postData['categories'] = $validated['category_ids'];
            if (!empty($validated['tag_ids'])) $postData['tags'] = $validated['tag_ids'];
            if ($validated['status'] === 'future' && !empty($validated['date'])) $postData['date'] = $validated['date'];

            $result = $this->wp->createPost($site->url, $site->wp_username, $site->wp_application_password, $postData);
        }

        if (!$result['success']) {
            hexaLog('publish', 'pipeline_publish_failed', "Pipeline publish failed to {$site->name}: {$result['message']}");
            return response()->json($result);
        }

        // SSH wpCliCreatePost returns only post_id (no post_url) — build it from site URL
        $wpPostId = $result['data']['post_id'] ?? null;
        $wpPostUrl = $result['data']['post_url'] ?? ($wpPostId ? rtrim($site->url, '/') . '/?p=' . $wpPostId : null);

        // Save or update the article record with comprehensive data
        $articleData = [
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
            'wp_post_id'          => $wpPostId,
            'wp_post_url'         => $wpPostUrl,
            'wp_status'           => $validated['status'],
            'published_at'        => now(),
            'source_articles'     => $validated['sources'] ?? null,
            'categories'          => $validated['categories'] ?? null,
            'tags'                => $validated['tags'] ?? null,
            'wp_images'           => $validated['wp_images'] ?? null,
            'links_injected'      => null,
            'created_by'          => auth()->id(),
        ];

        if (!empty($validated['draft_id'])) {
            $article = PublishArticle::find($validated['draft_id']);
            if ($article) {
                $article->update($articleData);
            } else {
                $articleData['article_id'] = PublishArticle::generateArticleId();
                $article = PublishArticle::create($articleData);
            }
        } else {
            $articleData['article_id'] = PublishArticle::generateArticleId();
            $article = PublishArticle::create($articleData);
        }

        hexaLog('publish', 'pipeline_published', "Pipeline published to {$site->name}: {$validated['title']} (WP ID: {$wpPostId}, Article: {$article->article_id})");

        return response()->json([
            'success'    => true,
            'message'    => "Article published to {$site->name}. WP Post ID: {$wpPostId}.",
            'post_id'    => $wpPostId,
            'post_url'   => $wpPostUrl,
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
            'sources'     => 'nullable|array',
            'tags'        => 'nullable|array',
            'categories'  => 'nullable|array',
            'notes'       => 'nullable|string',
        ]);

        $data = [
            'title'            => $validated['title'] ?? 'Untitled Pipeline Draft',
            'body'             => $validated['body'] ?? null,
            'excerpt'          => $validated['excerpt'] ?? null,
            'status'           => 'drafting',
            'created_by'       => $validated['user_id'] ?? auth()->id(),
            'publish_site_id'  => $validated['site_id'] ?? null,
            'ai_engine_used'   => $validated['ai_model'] ?? null,
            'source_articles'  => $validated['sources'] ?? null,
            'word_count'       => isset($validated['body']) ? str_word_count(strip_tags($validated['body'])) : 0,
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
     * @return int|null          The created term ID, or null on failure
     */
    private function wpCreateTaxonomy(string $siteUrl, string $username, string $password, string $taxonomy, string $name): ?int
    {
        $endpoint = rtrim($siteUrl, '/') . "/wp-json/wp/v2/{$taxonomy}";

        try {
            $response = \Illuminate\Support\Facades\Http::withBasicAuth($username, $password)
                ->timeout(15)
                ->post($endpoint, ['name' => $name]);

            if ($response->successful()) {
                return $response->json('id');
            }

            Log::warning("Failed to create WP {$taxonomy}: {$name}", [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);
            return null;
        } catch (\Exception $e) {
            Log::error("wpCreateTaxonomy error: {$taxonomy}/{$name}", ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Auto-provision WordPress REST API credentials for a WP Toolkit site.
     * Uses wp-cli via SSH to get admin user and create an application password.
     *
     * @param PublishSite $site
     * @return array{success: bool, message: string}
     */
    private function autoProvisionWpCredentials(PublishSite $site): array
    {
        if ($site->connection_type !== 'wptoolkit' || !$site->hosting_account_id || !$site->wordpress_install_id) {
            return ['success' => false, 'message' => 'Site is not connected via WP Toolkit.'];
        }

        $account = HostingAccount::find($site->hosting_account_id);
        if (!$account || !$account->whm_server_id) {
            return ['success' => false, 'message' => 'Hosting account or server not found.'];
        }

        $server = WhmServer::find($account->whm_server_id);
        if (!$server) {
            return ['success' => false, 'message' => 'WHM server not found.'];
        }

        // SSH to server and use wp-cli to get admin user + create application password
        $sshKey = config('hws.ssh.key_path', '/Users/mp/Projects/hexa-commands/id_localmap');
        $sshHost = $server->hostname;
        $cpanelUser = $account->username;
        $installId = $site->wordpress_install_id;

        try {
            $ssh = new \phpseclib3\Net\SSH2($sshHost, 22, 15);
            $key = \phpseclib3\Crypt\PublicKeyLoader::load(file_get_contents($sshKey));
            if (!$ssh->login('root', $key)) {
                return ['success' => false, 'message' => 'SSH login failed to ' . $sshHost];
            }

            // Get admin username via wp-toolkit
            $cmd = "wp-toolkit --wp-cli -instance-id " . escapeshellarg((string) $installId) . " -- user list --role=administrator --field=user_login --format=csv 2>&1";
            $output = trim($ssh->exec($cmd));
            $lines = array_filter(explode("\n", $output), fn($l) => !empty(trim($l)) && trim($l) !== 'user_login');
            $adminUser = trim($lines[array_key_first($lines)] ?? '');

            if (empty($adminUser)) {
                return ['success' => false, 'message' => 'Could not find WordPress admin user via wp-cli.'];
            }

            // Create application password
            $appName = 'hexa-publish-' . date('Ymd');
            $cmd = "wp-toolkit --wp-cli -instance-id " . escapeshellarg((string) $installId) . " -- user application-password create " . escapeshellarg($adminUser) . " " . escapeshellarg($appName) . " --porcelain 2>&1";
            $appPassword = trim($ssh->exec($cmd));

            if (empty($appPassword) || str_contains($appPassword, 'Error') || str_contains($appPassword, 'error')) {
                return ['success' => false, 'message' => 'Failed to create application password: ' . Str::limit($appPassword, 200)];
            }

            // Save credentials to site
            $site->update([
                'wp_username' => $adminUser,
                'wp_application_password' => $appPassword,
            ]);

            hexaLog('publish', 'wp_credentials_provisioned', "Auto-provisioned WP credentials for {$site->name}: user={$adminUser}");

            return ['success' => true, 'message' => "Credentials provisioned for {$site->name} (user: {$adminUser})."];

        } catch (\Exception $e) {
            Log::error('autoProvisionWpCredentials failed', ['site' => $site->name, 'error' => $e->getMessage()]);
            return ['success' => false, 'message' => 'SSH error: ' . $e->getMessage()];
        }
    }

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
