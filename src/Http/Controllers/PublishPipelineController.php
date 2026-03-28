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
use hexa_package_article_extractor\Services\ArticleExtractorService;
use hexa_package_wordpress\Services\WordPressService;
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

    /**
     * @param ArticleExtractorService $extractor
     * @param AnthropicService        $anthropic
     * @param WordPressService        $wp
     */
    public function __construct(
        ArticleExtractorService $extractor,
        AnthropicService $anthropic,
        WordPressService $wp
    ) {
        $this->extractor = $extractor;
        $this->anthropic = $anthropic;
        $this->wp = $wp;
    }

    /**
     * Show the pipeline page.
     *
     * @return View
     */
    public function index(): View
    {
        $sites = PublishSite::where('status', 'connected')->orderBy('name')->get();

        return view('app-publish::article.pipeline.index', [
            'sites' => $sites,
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

        $htmlInstruction = "\n\nCRITICAL OUTPUT FORMAT: You MUST output valid HTML only. Do NOT include an <h1> title — the title is handled separately. Start with <h2> for section headings. Use <p> for paragraphs. Use <strong> and <em> for emphasis — NEVER use ** or * markdown. Use <ul>/<ol>/<li> for lists. Use <blockquote> for quotes. Use <a href=\"\"> for links with relevant supporting URLs where appropriate. Do NOT output markdown. Do NOT wrap in ```html code blocks. Output raw HTML tags directly.";

        $systemPrompt = !empty($systemParts)
            ? implode("\n\n", $systemParts) . $htmlInstruction
            : "You are a professional content writer. Rewrite the provided source articles into a new unique article." . $htmlInstruction;

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
    public function prepareForWordpress(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'html'       => 'required|string',
            'site_id'    => 'required|integer|exists:publish_sites,id',
            'categories' => 'nullable|array',
            'tags'       => 'nullable|array',
        ]);

        $site = PublishSite::findOrFail($validated['site_id']);

        if (!$site->wp_username || !$site->wp_application_password) {
            return response()->json([
                'success' => false,
                'message' => "Site '{$site->name}' has no WordPress credentials configured.",
            ]);
        }

        $html = $validated['html'];
        $siteUrl = $site->url;
        $username = $site->wp_username;
        $password = $site->wp_application_password;
        $checklist = [];

        // Step 1: Upload images to WordPress media library
        preg_match_all('/<img[^>]+src\s*=\s*["\']([^"\']+)["\'][^>]*>/i', $html, $imgMatches);
        $imageUrls = array_unique($imgMatches[1] ?? []);
        $imageMap = [];

        if (!empty($imageUrls)) {
            $checklist[] = ['step' => 'upload_images', 'label' => 'Uploading images to WordPress media library', 'status' => 'running'];

            foreach ($imageUrls as $imgUrl) {
                // Skip if already on the same WP site
                if (str_starts_with($imgUrl, rtrim($siteUrl, '/'))) {
                    continue;
                }

                // Extract alt text from the img tag
                $altText = '';
                if (preg_match('/<img[^>]+src\s*=\s*["\']' . preg_quote($imgUrl, '/') . '["\'][^>]*alt\s*=\s*["\']([^"\']*)["\'][^>]*>/i', $html, $altMatch)) {
                    $altText = $altMatch[1];
                }

                $uploadResult = $this->wp->uploadMedia($siteUrl, $username, $password, $imgUrl, '', $altText);
                if ($uploadResult['success']) {
                    $imageMap[$imgUrl] = $uploadResult['data']['media_url'];
                }
            }

            $uploadedCount = count($imageMap);
            $totalImages = count($imageUrls);
            $checklist[count($checklist) - 1]['status'] = 'done';
            $checklist[count($checklist) - 1]['detail'] = "{$uploadedCount}/{$totalImages} images uploaded";
        } else {
            $checklist[] = ['step' => 'upload_images', 'label' => 'Uploading images to WordPress media library', 'status' => 'skipped', 'detail' => 'No images found'];
        }

        // Step 2: Replace image URLs in HTML
        if (!empty($imageMap)) {
            $checklist[] = ['step' => 'replace_urls', 'label' => 'Replacing image URLs', 'status' => 'running'];
            foreach ($imageMap as $oldUrl => $newUrl) {
                $html = str_replace($oldUrl, $newUrl, $html);
            }
            $checklist[count($checklist) - 1]['status'] = 'done';
            $checklist[count($checklist) - 1]['detail'] = count($imageMap) . ' URLs replaced';
        } else {
            $checklist[] = ['step' => 'replace_urls', 'label' => 'Replacing image URLs', 'status' => 'skipped', 'detail' => 'No replacements needed'];
        }

        // Step 3: Create categories on WordPress
        $categoryIds = [];
        $requestedCategories = $validated['categories'] ?? [];
        if (!empty($requestedCategories)) {
            $checklist[] = ['step' => 'create_categories', 'label' => 'Creating categories on WordPress', 'status' => 'running'];

            // Get existing categories
            $existingCats = $this->wp->getCategories($siteUrl, $username, $password);
            $existingCatMap = [];
            if ($existingCats['success']) {
                foreach ($existingCats['data'] as $cat) {
                    $existingCatMap[strtolower($cat['name'])] = $cat['id'];
                }
            }

            foreach ($requestedCategories as $catName) {
                $catNameLower = strtolower(trim($catName));
                if (isset($existingCatMap[$catNameLower])) {
                    $categoryIds[] = $existingCatMap[$catNameLower];
                } else {
                    // Create new category via WP REST API
                    $catResult = $this->wpCreateTaxonomy($siteUrl, $username, $password, 'categories', $catName);
                    if ($catResult) {
                        $categoryIds[] = $catResult;
                    }
                }
            }

            $checklist[count($checklist) - 1]['status'] = 'done';
            $checklist[count($checklist) - 1]['detail'] = count($categoryIds) . ' categories ready';
        } else {
            $checklist[] = ['step' => 'create_categories', 'label' => 'Creating categories on WordPress', 'status' => 'skipped', 'detail' => 'No categories specified'];
        }

        // Step 4: Create tags on WordPress
        $tagIds = [];
        $requestedTags = $validated['tags'] ?? [];
        if (!empty($requestedTags)) {
            $checklist[] = ['step' => 'create_tags', 'label' => 'Creating tags on WordPress', 'status' => 'running'];

            // Get existing tags
            $existingTags = $this->wp->getTags($siteUrl, $username, $password);
            $existingTagMap = [];
            if ($existingTags['success']) {
                foreach ($existingTags['data'] as $tag) {
                    $existingTagMap[strtolower($tag['name'])] = $tag['id'];
                }
            }

            foreach ($requestedTags as $tagName) {
                $tagNameLower = strtolower(trim($tagName));
                if (isset($existingTagMap[$tagNameLower])) {
                    $tagIds[] = $existingTagMap[$tagNameLower];
                } else {
                    $tagResult = $this->wpCreateTaxonomy($siteUrl, $username, $password, 'tags', $tagName);
                    if ($tagResult) {
                        $tagIds[] = $tagResult;
                    }
                }
            }

            $checklist[count($checklist) - 1]['status'] = 'done';
            $checklist[count($checklist) - 1]['detail'] = count($tagIds) . ' tags ready';
        } else {
            $checklist[] = ['step' => 'create_tags', 'label' => 'Creating tags on WordPress', 'status' => 'skipped', 'detail' => 'No tags specified'];
        }

        // Step 5: Validate HTML
        $checklist[] = ['step' => 'validate_html', 'label' => 'Validating HTML', 'status' => 'running'];
        $htmlValid = !empty(trim(strip_tags($html)));
        $checklist[count($checklist) - 1]['status'] = $htmlValid ? 'done' : 'failed';
        $checklist[count($checklist) - 1]['detail'] = $htmlValid ? 'HTML valid' : 'HTML is empty after processing';

        return response()->json([
            'success'      => $htmlValid,
            'message'      => $htmlValid ? 'Content prepared for WordPress.' : 'HTML validation failed.',
            'html'         => $html,
            'category_ids' => $categoryIds,
            'tag_ids'      => $tagIds,
            'checklist'    => $checklist,
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
            'html'         => 'required|string',
            'title'        => 'required|string|max:500',
            'site_id'      => 'required|integer|exists:publish_sites,id',
            'category_ids' => 'nullable|array',
            'tag_ids'      => 'nullable|array',
            'status'       => 'required|in:publish,draft,future',
            'date'         => 'nullable|date',
            'draft_id'     => 'nullable|integer|exists:publish_articles,id',
        ]);

        $site = PublishSite::findOrFail($validated['site_id']);

        if (!$site->wp_username || !$site->wp_application_password) {
            return response()->json([
                'success' => false,
                'message' => "Site '{$site->name}' has no WordPress credentials configured.",
            ]);
        }

        $postData = [
            'title'   => $validated['title'],
            'content' => $validated['html'],
            'status'  => $validated['status'],
        ];

        if (!empty($validated['category_ids'])) {
            $postData['categories'] = $validated['category_ids'];
        }

        if (!empty($validated['tag_ids'])) {
            $postData['tags'] = $validated['tag_ids'];
        }

        if ($validated['status'] === 'future' && !empty($validated['date'])) {
            $postData['date'] = $validated['date'];
        }

        $result = $this->wp->createPost(
            $site->url,
            $site->wp_username,
            $site->wp_application_password,
            $postData
        );

        if (!$result['success']) {
            hexaLog('publish', 'pipeline_publish_failed', "Pipeline publish failed to {$site->name}: {$result['message']}");
            return response()->json($result);
        }

        // Update draft record if we have one
        if (!empty($validated['draft_id'])) {
            $draft = PublishArticle::find($validated['draft_id']);
            if ($draft) {
                $draft->update([
                    'status'       => 'completed',
                    'published_at' => now(),
                    'wp_post_id'   => $result['data']['post_id'],
                    'wp_post_url'  => $result['data']['post_url'],
                    'wp_status'    => $result['data']['post_status'],
                    'publish_site_id' => $site->id,
                ]);
            }
        }

        hexaLog('publish', 'pipeline_published', "Pipeline published to {$site->name}: {$validated['title']} (WP ID: {$result['data']['post_id']})");

        return response()->json([
            'success'  => true,
            'message'  => "Article published to {$site->name}. WP Post ID: {$result['data']['post_id']}.",
            'post_id'  => $result['data']['post_id'],
            'post_url' => $result['data']['post_url'],
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
}
