<?php

namespace hexa_app_publish\Publishing\Articles\Services;

use hexa_app_publish\Quality\Detection\Models\AiActivityLog;
use hexa_app_publish\Publishing\Settings\Models\PublishMasterSetting;
use hexa_app_publish\Publishing\Presets\Models\PublishPreset;
use hexa_app_publish\Publishing\Templates\Models\PublishTemplate;
use hexa_package_anthropic\Services\AnthropicService;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

/**
 * ArticleGenerationService — single source of truth for AI article spinning.
 *
 * Handles: prompt building, AI call, response parsing (HTML cleanup, markdown fallback,
 * photo/featured extraction, metadata extraction), cost calculation, activity logging.
 *
 * Replaces duplicated spin logic from PipelineController::spin() and
 * CampaignRunService::spinArticle().
 */
class ArticleGenerationService
{
    protected AnthropicService $anthropic;

    /** Pricing per million tokens */
    private const PRICING = [
        'claude-opus-4-6'              => ['input' => 15.0, 'output' => 75.0],
        'claude-opus-4-20250514'       => ['input' => 15.0, 'output' => 75.0],
        'claude-sonnet-4-6'            => ['input' => 3.0,  'output' => 15.0],
        'claude-sonnet-4-20250514'     => ['input' => 3.0,  'output' => 15.0],
        'claude-haiku-4-5-20251001'    => ['input' => 0.80, 'output' => 4.0],
        'gpt-4o'                       => ['input' => 2.50, 'output' => 10.0],
        'gpt-4-turbo'                  => ['input' => 10.0, 'output' => 30.0],
        'gpt-4'                        => ['input' => 30.0, 'output' => 60.0],
        'gpt-3.5-turbo'                => ['input' => 0.50, 'output' => 1.50],
        'grok-3'                       => ['input' => 3.0,  'output' => 15.0],
        'grok-3-mini'                  => ['input' => 0.30, 'output' => 0.50],
        'grok-2'                       => ['input' => 2.0,  'output' => 10.0],
    ];

    /**
     * @param AnthropicService $anthropic
     */
    public function __construct(AnthropicService $anthropic)
    {
        $this->anthropic = $anthropic;
    }

    /**
     * Generate/spin an article from source texts using AI.
     *
     * @param array $sourceTexts Array of ['title' => ..., 'text' => ...] or plain strings
     * @param array $options {
     *     @type string $model           AI model (default: claude-sonnet-4-6)
     *     @type int    $template_id     PublishTemplate ID for prompt config
     *     @type int    $preset_id       PublishPreset ID for tone/format
     *     @type string $custom_prompt   Additional instructions
     *     @type string $change_request  Revision instructions (replaces source merge)
     *     @type array  $master_setting_ids  Override which master settings to use
     *     @type string $agent           Log agent name (default: spin)
     * }
     * @return array{success: bool, message: string, html: string, text: string, word_count: int, usage: array, model: string, cost: float, photo_suggestions: array, featured_image: string|null, metadata: array, resolved_prompt: string}
     */
    public function generate(array $sourceTexts, array $options = []): array
    {
        $model = $options['model'] ?? 'claude-sonnet-4-6';
        $templateId = $options['template_id'] ?? null;
        $presetId = $options['preset_id'] ?? null;
        $promptSlug = $options['prompt_slug'] ?? null;
        $customPrompt = $options['custom_prompt'] ?? null;
        $supportingUrlType = $options['supporting_url_type'] ?? 'matching_content_type';
        $changeRequest = $options['change_request'] ?? null;
        $prSubjectContext = $options['pr_subject_context'] ?? null;
        $agent = $options['agent'] ?? 'spin';

        // Build the system prompt
        $systemPrompt = $this->buildPrompt(
            $sourceTexts,
            $templateId,
            $presetId,
            $customPrompt,
            $changeRequest,
            $prSubjectContext,
            false,
            $promptSlug
        );

        // Inject web research instruction if requested
        if (!empty($options['web_research'])) {
            $systemPrompt .= "\n\nWEB RESEARCH: Before writing, search the web for current data, statistics, expert opinions, and recent developments related to this topic. Incorporate real, verifiable facts and supporting points from your research into the article. Cite specific sources where possible.";
        }

        $supportingUrlInstruction = $this->supportingUrlTypeInstruction($supportingUrlType);
        if ($supportingUrlInstruction !== '') {
            $systemPrompt .= "\n\n" . $supportingUrlInstruction;
        }

        // Call AI — route to correct provider
        $isOpenAI = str_starts_with($model, 'gpt-');
        $isGrok = str_starts_with($model, 'grok-');
        if ($isGrok) {
            $grok = app(\hexa_package_grok\Services\GrokService::class);
            $result = $grok->chat($systemPrompt, 'Generate the article now.', $model, 0.7, 8192);
        } elseif ($isOpenAI) {
            $chatgpt = app(\hexa_package_chatgpt\Services\ChatGptService::class);
            $result = $chatgpt->chat($systemPrompt, 'Generate the article now.', $model, 0.7, 8192);
        } else {
            $result = $this->anthropic->chat($systemPrompt, 'Generate the article now.', $model, 8192);
        }

        if (!$result['success']) {
            return [
                'success' => false,
                'message' => $result['message'] ?? 'AI call failed',
                'html' => '', 'text' => '', 'word_count' => 0, 'usage' => [],
                'model' => $model, 'cost' => 0, 'photo_suggestions' => [],
                'featured_image' => null, 'metadata' => [], 'resolved_prompt' => $systemPrompt,
            ];
        }

        $content = $result['data']['content'] ?? '';
        $usage = $result['data']['usage'] ?? [];

        // Clean and parse the AI response
        $content = $this->cleanHtml($content);
        $content = $this->convertMarkdownFallback($content);
        $photoSuggestions = $this->extractPhotoSuggestions($content);
        $content = $photoSuggestions['html'];
        $featuredImage = $this->extractFeaturedImage($content);
        $content = $featuredImage['html'];
        $metadata = $this->extractMetadata($content);
        $content = $metadata['html'];

        $content = trim($content);
        $plainText = strip_tags($content);
        $wordCount = str_word_count($plainText);
        $cost = $this->calculateCost($model, $usage);

        // Log AI activity
        $this->logActivity($model, $agent, $systemPrompt, $content, $usage);

        return [
            'success'          => true,
            'message'          => "Article generated: {$wordCount} words.",
            'html'             => $content,
            'text'             => $plainText,
            'word_count'       => $wordCount,
            'usage'            => $usage,
            'model'            => $result['data']['model'] ?? $model,
            'cost'             => round($cost, 6),
            'provider'         => $isGrok ? 'grok' : ($isOpenAI ? 'openai' : 'anthropic'),
            'photo_suggestions' => $photoSuggestions['photos'],
            'featured_image'   => $featuredImage['search'],
            'featured_meta'    => $featuredImage['featured_meta'] ?? null,
            'metadata'         => $metadata['data'],
            'resolved_prompt'  => $systemPrompt,
        ];
    }

    public function supportingUrlTypeInstruction(?string $type): string
    {
        $type = is_string($type) && $type !== '' ? $type : 'matching_content_type';

        return match ($type) {
            'news' => 'SUPPORTING URL TYPE: Favor current news reporting, trade coverage, and journalistic sources. Avoid leaning on academic papers unless they are directly relevant to a factual claim.',
            'academic_research' => 'SUPPORTING URL TYPE: Favor academic papers, peer-reviewed studies, university research, and formal research institutions. Use these as the primary supporting URLs when web research is enabled.',
            'official_primary' => 'SUPPORTING URL TYPE: Favor official and primary-source URLs such as company sites, government agencies, regulators, nonprofits, court documents, and official statements.',
            'passive_background' => 'SUPPORTING URL TYPE: Favor broad background and context URLs. Use supporting URLs lightly, prioritize general explanatory context, and avoid overloading the article with dense research citations.',
            default => 'SUPPORTING URL TYPE: Match the supporting URLs to the article’s actual content type and editorial angle. For tabloids, entertainment, or trending news, prefer news and culture coverage over academic sources. For research-heavy or technical topics, prefer research or primary sources only when they fit the content type.',
        };
    }

    /**
     * Build the full system prompt with shortcode replacement.
     *
     * @param array $sourceTexts
     * @param int|null $templateId
     * @param int|null $presetId
     * @param string|null $customPrompt
     * @param string|null $changeRequest
     * @param string|null $prSubjectContext
     * @param bool $withLog Whether to return resolution log alongside the prompt
     * @param string|null $promptSlug
     * @return string|array Returns string normally, or ['prompt' => ..., 'log' => [...]] when $withLog is true
     */
    public function buildPrompt(
        array $sourceTexts,
        ?int $templateId,
        ?int $presetId,
        ?string $customPrompt,
        ?string $changeRequest,
        ?string $prSubjectContext = null,
        bool $withLog = false,
        ?string $promptSlug = null
    ): string|array
    {
        $log = [];

        // Load master spin prompt
        $masterPrompt = PublishMasterSetting::where('type', 'master_spin_prompt')
            ->where('is_active', true)
            ->value('content');

        if (empty($masterPrompt)) {
            $masterPrompt = $this->defaultPrompt();
            $log[] = ['shortcode' => '(master prompt)', 'source' => 'Hardcoded fallback', 'value' => '(default prompt template)'];
        } else {
            $log[] = ['shortcode' => '(master prompt)', 'source' => 'Master Setting: master_spin_prompt', 'value' => '(custom prompt from settings)'];
        }

        // Try Prompt Center override
        if (class_exists(\hexa_package_prompt_center\Prompts\Categories\Services\PromptService::class)) {
            try {
                $promptService = app(\hexa_package_prompt_center\Prompts\Categories\Services\PromptService::class);
                $promptTemplate = $promptSlug
                    ? $promptService->getByTemplateSlug($promptSlug)
                    : $promptService->getDefault('general-article-spin');
                if ($promptTemplate && !empty($promptTemplate->body)) {
                    $masterPrompt = $promptTemplate->body;
                    $sourceLabel = $promptSlug
                        ? 'Prompt Center slug: ' . $promptTemplate->slug
                        : 'Prompt Center: ' . $promptTemplate->name;
                    $log[0] = ['shortcode' => '(master prompt)', 'source' => $sourceLabel, 'value' => '(from Prompt Center template)'];
                }
            } catch (\Throwable $e) {}
        }

        // Load settings for shortcode replacement
        $masterSettings = PublishMasterSetting::where('is_active', true)->orderBy('sort_order')->get();
        $wpGuidelines = $masterSettings->where('type', 'wordpress_guidelines')->pluck('content')->implode("\n\n");
        $spinGuidelines = $masterSettings->where('type', 'spinning_guidelines')->pluck('content')->implode("\n\n");

        // Preset config
        $presetConfig = '';
        $imagePreference = '';
        $preset = null;
        if ($presetId) {
            $preset = PublishPreset::find($presetId);
            if ($preset) {
                $parts = [];
                if ($preset->tone) $parts[] = "Tone: {$preset->tone}";
                if ($preset->article_format) $parts[] = "Format: {$preset->article_format}";
                if ($preset->follow_links) $parts[] = "Links: {$preset->follow_links}";
                if ($preset->image_preference) $parts[] = "Images: {$preset->image_preference}";
                $presetConfig = implode("\n", $parts);
                $imagePreference = $preset->image_preference ?? '';
            }
        }

        // Template config
        $templateConfig = '';
        $photoCount = '2-4';
        $template = null;
        if ($templateId) {
            $template = PublishTemplate::find($templateId);
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

        // Dynamic metadata counts — pull from preset, fall back to defaults
        $titleCount = 10;
        $categoryCount = 15;
        $tagCount = 15;
        $titleSource = 'Default (10)';
        $categorySource = 'Default (15)';
        $tagSource = 'Default (15)';

        if ($preset) {
            if ($preset->default_category_count) {
                $categoryCount = (int) $preset->default_category_count;
                $categorySource = "Preset: {$preset->name} → default_category_count = {$categoryCount}";
            }
            if ($preset->default_tag_count) {
                $tagCount = (int) $preset->default_tag_count;
                $tagSource = "Preset: {$preset->name} → default_tag_count = {$tagCount}";
            }
        }

        // Build source articles text
        $sourceTextsStr = $this->buildSourceText($sourceTexts, $changeRequest);
        $sourceWordCount = str_word_count(strip_tags($sourceTextsStr));

        // Build shortcode replacements with logging
        $replacements = [
            '{custom_instructions}' => [
                'value' => !empty($customPrompt) ? "=== PRIORITY INSTRUCTIONS ===\n{$customPrompt}" : '',
                'source' => !empty($customPrompt) ? 'User input' : 'Empty (no custom instructions)',
            ],
            '{wordpress_guidelines}' => [
                'value' => $wpGuidelines ? "=== WordPress Guidelines ===\n{$wpGuidelines}" : '',
                'source' => $wpGuidelines ? 'Master Setting: wordpress_guidelines' : 'Empty (no guidelines set)',
            ],
            '{spinning_guidelines}' => [
                'value' => $spinGuidelines ? "=== Spinning Guidelines ===\n{$spinGuidelines}" : '',
                'source' => $spinGuidelines ? 'Master Setting: spinning_guidelines' : 'Empty (no guidelines set)',
            ],
            '{preset_config}' => [
                'value' => $presetConfig ? "=== Preset Config ===\n{$presetConfig}" : '',
                'source' => $preset ? "Preset: {$preset->name}" : 'Empty (no preset selected)',
            ],
            '{template_config}' => [
                'value' => $templateConfig ? "=== Template Config ===\n{$templateConfig}" : '',
                'source' => $template ? "Template: {$template->name}" : 'Empty (no template selected)',
            ],
            '{photo_count}' => [
                'value' => $photoCount,
                'source' => $template && $template->photos_per_article ? "Template: {$template->name} → photos_per_article = {$photoCount}" : 'Default (2-4)',
            ],
            '{title_count}' => [
                'value' => (string) $titleCount,
                'source' => $titleSource,
            ],
            '{category_count}' => [
                'value' => (string) $categoryCount,
                'source' => $categorySource,
            ],
            '{tag_count}' => [
                'value' => (string) $tagCount,
                'source' => $tagSource,
            ],
            '{source_articles}' => [
                'value' => $sourceTextsStr,
                'source' => count($sourceTexts) . ' source(s), ~' . number_format($sourceWordCount) . ' words',
            ],
            '{featured_image_preference}' => [
                'value' => $imagePreference,
                'source' => $imagePreference ? "Preset: {$preset->name} → image_preference" : 'Empty',
            ],
            '{pr_subject_context}' => [
                'value' => $prSubjectContext ? "=== PR SUBJECT CONTEXT ===\nThe following is background data about the article subject(s). Use relevant information to enrich the article. Focus on data that supports the article's angle and instruction. Not all data needs to be used — prioritize what's relevant.\n\n{$prSubjectContext}" : '',
                'source' => $prSubjectContext ? 'Pipeline: PR subject data (' . strlen($prSubjectContext) . ' chars)' : 'Empty (no PR subjects)',
            ],
        ];

        // Replace all shortcodes
        $keys = array_keys($replacements);
        $values = array_map(fn ($r) => $r['value'], $replacements);
        $prompt = str_replace($keys, $values, $masterPrompt);

        // Build log entries
        foreach ($replacements as $shortcode => $info) {
            $log[] = [
                'shortcode' => $shortcode,
                'source'    => $info['source'],
                'value'     => mb_strlen($info['value']) > 200 ? mb_substr($info['value'], 0, 200) . '...' : $info['value'],
            ];
        }

        $result = preg_replace("/\n{3,}/", "\n\n", trim($prompt));

        if ($withLog) {
            return ['prompt' => $result, 'log' => $log];
        }

        return $result;
    }

    /**
     * @param array $sourceTexts
     * @param string|null $changeRequest
     * @return string
     */
    private function buildSourceText(array $sourceTexts, ?string $changeRequest): string
    {
        if (!empty($changeRequest) && !empty($sourceTexts[0])) {
            $text = is_array($sourceTexts[0]) ? ($sourceTexts[0]['text'] ?? $sourceTexts[0]) : $sourceTexts[0];
            return "Below is an existing article. Apply the following changes:\n\nChanges requested: {$changeRequest}\n\n=== Current Article ===\n{$text}";
        }

        $str = "Below are the source articles to spin into a new unique article:\n";
        foreach ($sourceTexts as $i => $src) {
            $num = $i + 1;
            $title = is_array($src) ? ($src['title'] ?? '') : '';
            $text = is_array($src) ? ($src['text'] ?? $src) : $src;
            $str .= "\n=== Source {$num} ===\n";
            if ($title) $str .= "Title: {$title}\n";
            $str .= Str::limit($text, 3000) . "\n";
        }
        return $str;
    }

    /**
     * @return string
     */
    /**
     * Get the default prompt — from Prompt Center if available, otherwise hardcoded fallback.
     *
     * @return string
     */
    private function defaultPrompt(): string
    {
        return "You are a professional content writer. Rewrite the provided source articles into a single new unique article.\n\n{custom_instructions}\n\n{wordpress_guidelines}\n\n{spinning_guidelines}\n\n{preset_config}\n\n{template_config}\n\nCRITICAL OUTPUT FORMAT: You MUST output valid HTML only. Do NOT include the article title anywhere in the body — no <h1> and no title-like <h2> at the top. The title is handled separately. Start the body directly with the first paragraph of content. Use <h2> ONLY for section subheadings within the article, not as a title. Use <p> for paragraphs. Use <strong> and <em> for emphasis. Use <ul>/<ol>/<li> for lists. Use <blockquote> for quotes. Use <a href=\"\"> for links. Do NOT output markdown.\n\nSUPPORTING LINKS: Include 3-5 relevant external links within the article using <a href=\"URL\" target=\"_blank\"> tags.\n\nPHOTO PLACEMENT: Insert HTML comments for photos at natural breaking points. Use this EXACT format:\n<!-- PHOTO: stock photo search term | alt text describing what the stock photo shows | caption describing the photo scene and how it relates to the topic | seo-filename -->\n\nIMPORTANT: These are STOCK PHOTOS, not real photos of the people in the article. The alt text and caption must describe the visual content of the stock photo (e.g. \"actress performing on stage during theatrical production\"), NOT name or reference specific people from the article. Only use a person's name if the search term itself is that person's name.\n\nPlace {photo_count} photo markers.\n\nFEATURED IMAGE: Output one line:\n<!-- FEATURED: stock photo search term | alt text describing the stock photo | caption describing the photo scene | seo-filename -->\n\nMETADATA: At the very end of your response, output a JSON block:\n<!-- METADATA: {\"titles\":[\"title1\",\"title2\",...{title_count} titles],\"categories\":[\"cat1\",\"cat2\",...{category_count} categories],\"tags\":[\"tag1\",\"tag2\",...{tag_count} tags],\"description\":\"A 1-2 sentence SEO meta description summarizing the article\"} -->\n\nThe titles should be compelling and SEO-friendly. Categories are broad topics. Tags are specific keywords. Description is a concise meta description for SEO (under 160 characters).\n\n{source_articles}";
    }

    /**
     * Clean raw AI HTML output.
     *
     * @param string $content
     * @return string
     */
    private function cleanHtml(string $content): string
    {
        // Strip ```html code blocks
        $content = preg_replace('/^```html\s*/i', '', $content);
        $content = preg_replace('/\s*```$/', '', $content);

        // Strip full HTML document wrapper
        if (preg_match('/<body[^>]*>(.*)<\/body>/is', $content, $bodyMatch)) {
            $content = trim($bodyMatch[1]);
        }
        $content = preg_replace('/<!DOCTYPE[^>]*>/i', '', $content);
        $content = preg_replace('/<\/?html[^>]*>/i', '', $content);
        $content = preg_replace('/<head>.*?<\/head>/is', '', $content);

        // Strip H1
        $content = preg_replace('/<h1[^>]*>.*?<\/h1>/is', '', $content, 1);

        // Strip leading H2 that acts as a title (first element before any <p>)
        $content = trim($content);
        $content = preg_replace('/^\s*<h2[^>]*>.*?<\/h2>\s*/is', '', $content, 1);

        return trim($content);
    }

    /**
     * Convert markdown to HTML if AI returned markdown instead.
     *
     * @param string $content
     * @return string
     */
    private function convertMarkdownFallback(string $content): string
    {
        if (!preg_match('/^#{1,6}\s|^\*\*|^\- |\n#{1,6}\s/m', $content)) {
            return $content;
        }

        $content = preg_replace('/^######\s+(.+)$/m', '<h6>$1</h6>', $content);
        $content = preg_replace('/^#####\s+(.+)$/m', '<h5>$1</h5>', $content);
        $content = preg_replace('/^####\s+(.+)$/m', '<h4>$1</h4>', $content);
        $content = preg_replace('/^###\s+(.+)$/m', '<h3>$1</h3>', $content);
        $content = preg_replace('/^##\s+(.+)$/m', '<h2>$1</h2>', $content);
        $content = preg_replace('/^#\s+(.+)$/m', '<h1>$1</h1>', $content);
        $content = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $content);
        $content = preg_replace('/(?<!\*)\*(?!\*)(.+?)(?<!\*)\*(?!\*)/', '<em>$1</em>', $content);
        $content = preg_replace('/^[\-\*]\s+(.+)$/m', '<li>$1</li>', $content);
        $content = preg_replace('/(<li>.*<\/li>\n?)+/', '<ul>$0</ul>', $content);
        $content = preg_replace('/\[([^\]]+)\]\(([^\)]+)\)/', '<a href="$2">$1</a>', $content);

        $lines = explode("\n\n", $content);
        $content = implode("\n", array_map(function ($block) {
            $block = trim($block);
            if (empty($block)) return '';
            if (preg_match('/^<(h[1-6]|ul|ol|li|blockquote|div|p|table)/', $block)) return $block;
            return '<p>' . str_replace("\n", '<br>', $block) . '</p>';
        }, $lines));

        return $content;
    }

    /**
     * Extract photo placement suggestions from HTML comments.
     *
     * @param string $content
     * @return array{html: string, photos: array}
     */
    private function extractPhotoSuggestions(string $content): array
    {
        $photos = [];
        if (preg_match_all('/<!--\s*PHOTO:\s*(.+?)\s*-->/', $content, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $i => $match) {
                $parts = array_map('trim', explode('|', $match[1]));
                $searchTerm = $parts[0] ?? '';
                $altText = $parts[1] ?? $searchTerm;
                $caption = $parts[2] ?? '';
                $seoFilename = $parts[3] ?? Str::slug($searchTerm);
                $photos[] = [
                    'search_term' => $searchTerm,
                    'alt_text' => $altText,
                    'caption' => $caption,
                    'suggestedFilename' => $seoFilename,
                    'position' => $i,
                ];
                $placeholder = '<div class="photo-placeholder" contenteditable="false" data-idx="' . $i . '" data-search="' . htmlspecialchars($searchTerm) . '" data-caption="' . htmlspecialchars($altText) . '" style="border:2px dashed #a78bfa;background:#f5f3ff;border-radius:8px;padding:12px 16px;margin:16px 0;cursor:pointer;text-align:center;color:#7c3aed;font-size:14px;">'
                    . '<div style="display:inline-block;width:20px;height:20px;border:2px solid #a78bfa;border-top-color:transparent;border-radius:50%;animation:spin 0.8s linear infinite;"></div>'
                    . '<style>@keyframes spin{to{transform:rotate(360deg)}}</style>'
                    . '<span style="font-size:13px;margin-left:8px;">Loading photo...</span>'
                    . '</div>';
                $content = preg_replace('/<!--\s*PHOTO:\s*' . preg_quote($match[1], '/') . '\s*-->/', $placeholder, $content, 1);
            }
        }
        return ['html' => $content, 'photos' => $photos];
    }

    /**
     * Extract featured image suggestion.
     *
     * @param string $content
     * @return array{html: string, search: string|null}
     */
    private function extractFeaturedImage(string $content): array
    {
        $data = null;
        if (preg_match('/<!--\s*FEATURED:\s*(.+?)\s*-->/', $content, $match)) {
            $parts = array_map('trim', explode('|', $match[1]));
            $data = [
                'search'   => $parts[0] ?? '',
                'alt'      => $parts[1] ?? '',
                'caption'  => $parts[2] ?? '',
                'filename' => $parts[3] ?? Str::slug($parts[0] ?? 'featured'),
            ];
            $content = preg_replace('/<!--\s*FEATURED:\s*.+?\s*-->/', '', $content);
        }
        return ['html' => $content, 'search' => $data['search'] ?? null, 'featured_meta' => $data];
    }

    /**
     * Extract metadata (titles, categories, tags, description).
     *
     * @param string $content
     * @return array{html: string, data: array}
     */
    private function extractMetadata(string $content): array
    {
        $data = ['titles' => [], 'categories' => [], 'tags' => [], 'description' => ''];
        if (preg_match('/<!--\s*METADATA:\s*(\{.+?\})\s*-->/s', $content, $match)) {
            $parsed = json_decode(trim($match[1]), true);
            if ($parsed) $data = array_merge($data, $parsed);
            $content = preg_replace('/<!--\s*METADATA:\s*\{.+?\}\s*-->/s', '', $content);
        }
        return ['html' => $content, 'data' => $data];
    }

    /**
     * Calculate API cost from token usage.
     *
     * @param string $model
     * @param array $usage
     * @return float
     */
    public function calculateCost(string $model, array $usage): float
    {
        $rates = self::PRICING[$model] ?? ['input' => 0, 'output' => 0];
        $inputTokens = $usage['input_tokens'] ?? 0;
        $outputTokens = $usage['output_tokens'] ?? 0;
        return ($inputTokens * $rates['input'] / 1_000_000) + ($outputTokens * $rates['output'] / 1_000_000);
    }

    /**
     * Log the AI call to AiActivityLog.
     *
     * @param string $model
     * @param string $agent
     * @param string $systemPrompt
     * @param string $content
     * @param array $usage
     * @return void
     */
    private function logActivity(string $model, string $agent, string $systemPrompt, string $content, array $usage): void
    {
        $isOpenAI = str_starts_with($model, 'gpt-');
        $isGrok = str_starts_with($model, 'grok-');
        $apiKey = $isGrok
            ? ''
            : ($isOpenAI
                ? \hexa_core\Models\Setting::getValue('chatgpt_api_key', '')
                : \hexa_core\Models\Setting::getValue('anthropic_api_key', ''));
        AiActivityLog::logCall([
            'provider'          => $isGrok ? 'grok' : ($isOpenAI ? 'openai' : 'anthropic'),
            'model'             => $model,
            'agent'             => $agent,
            'prompt_tokens'     => $usage['input_tokens'] ?? 0,
            'completion_tokens' => $usage['output_tokens'] ?? 0,
            'system_prompt'     => mb_substr($systemPrompt, 0, 5000),
            'user_message'      => 'Generate the article now.',
            'response_content'  => mb_substr($content, 0, 10000),
            'success'           => true,
            'api_key_masked'    => $apiKey ? '...' . substr($apiKey, -4) : null,
        ]);
    }
}
