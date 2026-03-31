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
        // If no ?id= in URL, create a new draft and redirect with id
        if (!$request->has('id')) {
            $draft = PublishArticle::create([
                'article_id' => PublishArticle::generateArticleId(),
                'title'      => 'Untitled',
                'status'     => 'drafting',
                'created_by' => auth()->id(),
                'user_id'    => auth()->id(),
            ]);
            return redirect()->route('publish.pipeline', ['id' => $draft->id]);
        }

        // Load existing draft
        $draftId = (int) $request->input('id');
        $draft = PublishArticle::find($draftId);
        if (!$draft) {
            return redirect()->route('publish.pipeline');
        }

        $sites = PublishSite::where('status', 'connected')->orderBy('name')->get();

        return view('app-publish::article.pipeline.index', [
            'sites'   => $sites,
            'draftId' => $draft->id,
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

        // Load master settings (active ones, or specific IDs if provided)
        if (!empty($validated['master_setting_ids'])) {
            $masterSettings = PublishMasterSetting::whereIn('id', $validated['master_setting_ids'])
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->get();
        } else {
            $masterSettings = PublishMasterSetting::where('is_active', true)
                ->orderBy('sort_order')
                ->get();
        }

        // Build system prompt from master settings
        $systemParts = [];

        $wpGuidelines = $masterSettings->where('type', 'wordpress_guidelines')->pluck('content')->implode("\n\n");
        if ($wpGuidelines) {
            $systemParts[] = "=== WordPress Publishing Guidelines ===\n{$wpGuidelines}";
        }

        $spinGuidelines = $masterSettings->where('type', 'spinning_guidelines')->pluck('content')->implode("\n\n");
        if ($spinGuidelines) {
            $systemParts[] = "=== AI Spinning Guidelines ===\n{$spinGuidelines}";
        }

        // Add preset config if selected
        if (!empty($validated['preset_id'])) {
            $preset = PublishPreset::find($validated['preset_id']);
            if ($preset) {
                $presetParts = [];
                if ($preset->tone) {
                    $presetParts[] = "tone={$preset->tone}";
                }
                if ($preset->article_format) {
                    $presetParts[] = "format={$preset->article_format}";
                }
                if ($preset->follow_links) {
                    $presetParts[] = "follow_links={$preset->follow_links}";
                }
                if ($preset->image_preference) {
                    $presetParts[] = "image_preference={$preset->image_preference}";
                }
                if (!empty($presetParts)) {
                    $systemParts[] = "=== Preset Configuration ===\n" . implode(', ', $presetParts);
                }
            }
        }

        // Determine photo count from template if available
        $photoCount = '2-4';
        if (!empty($validated['template_id'])) {
            $templateForPhotos = PublishTemplate::find($validated['template_id']);
            if ($templateForPhotos && $templateForPhotos->photos_per_article) {
                $photoCount = (string) $templateForPhotos->photos_per_article;
            }
        }

        $htmlInstruction = "\n\nCRITICAL OUTPUT FORMAT: You MUST output valid HTML only. Do NOT include an <h1> title — the title is handled separately. Start with <h2> for section headings. Use <p> for paragraphs. Use <strong> and <em> for emphasis — NEVER use ** or * markdown. Use <ul>/<ol>/<li> for lists. Use <blockquote> for quotes. Use <a href=\"\"> for links with relevant supporting URLs where appropriate. Do NOT output markdown. Do NOT wrap in ```html code blocks. Output raw HTML tags directly.\n\nPHOTO PLACEMENT: Insert HTML comments where photos should be placed, using this exact format: <!-- PHOTO: descriptive search term for stock photo | alt text description -->. Place {$photoCount} photo markers throughout the article at natural breaking points (after key paragraphs, between sections). The search term should be specific and visual for finding stock photos (e.g. 'business executive speaking at podium' not just 'business'). The alt text should be a concise description of what the photo shows for accessibility (e.g. 'Business executive presenting quarterly results at company podium'). Keep alt text under 125 characters.";

        $systemPrompt = !empty($systemParts)
            ? implode("\n\n", $systemParts) . $htmlInstruction
            : "You are a professional content writer. Rewrite the provided source articles into a new unique article." . $htmlInstruction;

        // Custom prompt takes highest priority — prepended to system prompt
        if (!empty($validated['custom_prompt'])) {
            $systemPrompt = "=== PRIORITY INSTRUCTIONS (from user) ===\n{$validated['custom_prompt']}\n\n" . $systemPrompt;
        }

        // Build user message from prompt template + sources
        $userParts = [];

        if (!empty($validated['template_id'])) {
            $template = PublishTemplate::find($validated['template_id']);
            if ($template) {
                $templateParts = [];
                if ($template->ai_prompt) {
                    $templateParts[] = $template->ai_prompt;
                }
                if ($template->tone) {
                    $tones = is_array($template->tone) ? implode(', ', $template->tone) : $template->tone;
                    $templateParts[] = "Tone: {$tones}";
                }
                if ($template->word_count_min || $template->word_count_max) {
                    $templateParts[] = "Target word count: {$template->word_count_min} - {$template->word_count_max} words";
                }
                if ($template->article_type) {
                    $templateParts[] = "Article type: {$template->article_type}";
                }
                if (!empty($templateParts)) {
                    $userParts[] = implode("\n", $templateParts);
                }
            }
        }

        // If this is a change request, treat source_texts as the existing article
        if (!empty($validated['change_request'])) {
            $userParts[] = "Below is an existing article. Apply the following changes:\n\nChanges requested: {$validated['change_request']}\n\n=== Current Article ===\n{$validated['source_texts'][0]}";
        } else {
            $userParts[] = "Below are the source articles to spin into a new unique article:\n";

            foreach ($validated['source_texts'] as $i => $text) {
                $num = $i + 1;
                $userParts[] = "=== Source {$num} ===\n{$text}";
            }
        }

        $userMessage = implode("\n\n", $userParts);
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
        $photoSuggestions = [];
        if (preg_match_all('/<!--\s*PHOTO:\s*(.+?)\s*\|\s*(.+?)\s*-->/', $content, $photoMatches, PREG_SET_ORDER)) {
            foreach ($photoMatches as $i => $match) {
                $searchTerm = trim($match[1]);
                $caption = trim($match[2]);
                $photoSuggestions[] = [
                    'search_term' => $searchTerm,
                    'alt_text' => $caption,
                    'caption' => '',
                    'position' => $i,
                ];
                // Replace invisible comment with visible placeholder (includes data-idx for JS targeting)
                $placeholder = '<div class="photo-placeholder" contenteditable="false" data-idx="' . $i . '" data-search="' . htmlspecialchars($searchTerm) . '" data-caption="' . htmlspecialchars($caption) . '" style="border:2px dashed #a78bfa;background:#f5f3ff;border-radius:8px;padding:12px 16px;margin:16px 0;cursor:pointer;text-align:center;color:#7c3aed;font-size:14px;">'
                    . '<span style="font-size:13px;">Loading photo...</span>'
                    . '</div>';
                $content = preg_replace('/<!--\s*PHOTO:\s*' . preg_quote($match[1], '/') . '\s*\|\s*' . preg_quote($match[2], '/') . '\s*-->/', $placeholder, $content, 1);
            }
        }

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

                foreach ($imageUrls as $imgUrl) {
                    if (str_starts_with($imgUrl, rtrim($siteUrl, '/'))) continue;

                    $altText = '';
                    if (preg_match('/<img[^>]+src\s*=\s*["\']' . preg_quote($imgUrl, '/') . '["\'][^>]*alt\s*=\s*["\']([^"\']*)["\'][^>]*>/i', $html, $altMatch)) {
                        $altText = $altMatch[1];
                    }

                    $slugBase = Str::limit(Str::slug($altText ?: $articleTitle, '-'), 80, '');
                    $ext = pathinfo(parse_url($imgUrl, PHP_URL_PATH), PATHINFO_EXTENSION) ?: 'jpg';
                    $properFilename = $slugBase . '-' . (++$imgIndex) . '.' . $ext;

                    $send('step', "Uploading: {$properFilename}...");

                    if ($mode === self::WP_MODE_SSH) {
                        $uploadResult = $wptoolkit->wpCliUploadMedia($server, $installId, $imgUrl, $properFilename, $altText);
                    } else {
                        $uploadResult = $wp->uploadMedia($siteUrl, $site->wp_username, $site->wp_application_password, $imgUrl, $properFilename, $altText);
                    }

                    if ($uploadResult['success'] && !empty($uploadResult['data']['media_url'])) {
                        $imageMap[$imgUrl] = $uploadResult['data']['media_url'];
                        $wpImages[] = [
                            'source_url' => $imgUrl,
                            'media_id' => $uploadResult['data']['media_id'] ?? null,
                            'media_url' => $uploadResult['data']['media_url'],
                            'sizes' => $uploadResult['data']['sizes'] ?? [],
                            'filename' => $properFilename,
                            'alt_text' => $altText,
                        ];
                        $send('success', "Uploaded: {$properFilename} → " . Str::limit($uploadResult['data']['media_url'], 80), [
                            'wp_image' => $wpImages[count($wpImages) - 1],
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
            'author'              => $validated['author'] ?? $site->default_author ?? null,
            'status'              => 'completed',
            'wp_post_id'          => $result['data']['post_id'] ?? null,
            'wp_post_url'         => $result['data']['post_url'] ?? null,
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

        hexaLog('publish', 'pipeline_published', "Pipeline published to {$site->name}: {$validated['title']} (WP ID: {$result['data']['post_id']}, Article: {$article->article_id})");

        return response()->json([
            'success'    => true,
            'message'    => "Article published to {$site->name}. WP Post ID: {$result['data']['post_id']}.",
            'post_id'    => $result['data']['post_id'],
            'post_url'   => $result['data']['post_url'] ?? null,
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
}
