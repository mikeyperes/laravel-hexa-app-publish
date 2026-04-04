<?php

namespace hexa_app_publish\Publishing\Articles\Services;

use hexa_app_publish\Models\AiActivityLog;
use hexa_app_publish\Models\PublishMasterSetting;
use hexa_app_publish\Models\PublishPreset;
use hexa_app_publish\Models\PublishTemplate;
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

    /** Claude pricing per million tokens */
    private const PRICING = [
        'claude-opus-4-6'              => ['input' => 15.0, 'output' => 75.0],
        'claude-opus-4-20250514'       => ['input' => 15.0, 'output' => 75.0],
        'claude-sonnet-4-6'            => ['input' => 3.0,  'output' => 15.0],
        'claude-sonnet-4-20250514'     => ['input' => 3.0,  'output' => 15.0],
        'claude-haiku-4-5-20251001'    => ['input' => 0.80, 'output' => 4.0],
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
        $customPrompt = $options['custom_prompt'] ?? null;
        $changeRequest = $options['change_request'] ?? null;
        $agent = $options['agent'] ?? 'spin';

        // Build the system prompt
        $systemPrompt = $this->buildPrompt($sourceTexts, $templateId, $presetId, $customPrompt, $changeRequest);

        // Call AI
        $result = $this->anthropic->chat($systemPrompt, 'Generate the article now.', $model, 8192);

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
            'provider'         => 'anthropic',
            'photo_suggestions' => $photoSuggestions['photos'],
            'featured_image'   => $featuredImage['search'],
            'metadata'         => $metadata['data'],
            'resolved_prompt'  => $systemPrompt,
        ];
    }

    /**
     * Build the full system prompt with shortcode replacement.
     *
     * @param array $sourceTexts
     * @param int|null $templateId
     * @param int|null $presetId
     * @param string|null $customPrompt
     * @param string|null $changeRequest
     * @return string
     */
    private function buildPrompt(array $sourceTexts, ?int $templateId, ?int $presetId, ?string $customPrompt, ?string $changeRequest): string
    {
        // Load master spin prompt
        $masterPrompt = PublishMasterSetting::where('type', 'master_spin_prompt')
            ->where('is_active', true)
            ->value('content');

        if (empty($masterPrompt)) {
            $masterPrompt = $this->defaultPrompt();
        }

        // Load settings for shortcode replacement
        $masterSettings = PublishMasterSetting::where('is_active', true)->orderBy('sort_order')->get();
        $wpGuidelines = $masterSettings->where('type', 'wordpress_guidelines')->pluck('content')->implode("\n\n");
        $spinGuidelines = $masterSettings->where('type', 'spinning_guidelines')->pluck('content')->implode("\n\n");

        // Preset config
        $presetConfig = '';
        $imagePreference = '';
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

        // Build source articles text
        $sourceTextsStr = $this->buildSourceText($sourceTexts, $changeRequest);

        // Replace all shortcodes
        $prompt = str_replace([
            '{custom_instructions}',
            '{wordpress_guidelines}',
            '{spinning_guidelines}',
            '{preset_config}',
            '{template_config}',
            '{photo_count}',
            '{source_articles}',
            '{featured_image_preference}',
        ], [
            !empty($customPrompt) ? "=== PRIORITY INSTRUCTIONS ===\n{$customPrompt}" : '',
            $wpGuidelines ? "=== WordPress Guidelines ===\n{$wpGuidelines}" : '',
            $spinGuidelines ? "=== Spinning Guidelines ===\n{$spinGuidelines}" : '',
            $presetConfig ? "=== Preset Config ===\n{$presetConfig}" : '',
            $templateConfig ? "=== Template Config ===\n{$templateConfig}" : '',
            $photoCount,
            $sourceTextsStr,
            $imagePreference,
        ], $masterPrompt);

        return preg_replace("/\n{3,}/", "\n\n", trim($prompt));
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
    private function defaultPrompt(): string
    {
        return "You are a professional content writer. Rewrite the provided source articles into a single new unique article.\n\n{custom_instructions}\n\n{wordpress_guidelines}\n\n{spinning_guidelines}\n\n{preset_config}\n\n{template_config}\n\nCRITICAL OUTPUT FORMAT: You MUST output valid HTML only. Do NOT include an <h1> title. Start with <h2> for section headings. Use <p> for paragraphs. Use <strong> and <em> for emphasis. Use <ul>/<ol>/<li> for lists. Use <blockquote> for quotes. Use <a href=\"\"> for links. Do NOT output markdown.\n\nSUPPORTING LINKS: Include 3-5 relevant external links within the article using <a href=\"URL\" target=\"_blank\"> tags. These should link to real, credible news articles, official sources, government sites, or research that support the claims being made. Do NOT link to the source articles provided — find independent supporting references.\n\nPHOTO PLACEMENT: Insert HTML comments for photos: <!-- PHOTO: descriptive search term | alt text description -->. Place {photo_count} photo markers at natural breaking points. Search terms must be specific and visual — match commonly available stock photo subjects, avoid niche historical or overly specific terms. Alt text under 125 characters.\n\nFEATURED IMAGE: Also output one line: <!-- FEATURED: descriptive search term for the article featured image -->\n\nMETADATA: At the very end of your response, output a JSON block:\n<!-- METADATA: {\"titles\":[\"title1\",\"title2\",...10 titles],\"categories\":[\"cat1\",\"cat2\",...15 categories],\"tags\":[\"tag1\",\"tag2\",...15 tags],\"description\":\"A 1-2 sentence SEO meta description summarizing the article\"} -->\n\nThe titles should be compelling and SEO-friendly. Categories are broad topics. Tags are specific keywords. Description is a concise meta description for SEO (under 160 characters).\n\n{source_articles}";
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
                    . '<span style="font-size:13px;">Loading photo...</span>'
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
        $search = null;
        if (preg_match('/<!--\s*FEATURED:\s*(.+?)\s*-->/', $content, $match)) {
            $search = trim($match[1]);
            $content = preg_replace('/<!--\s*FEATURED:\s*.+?\s*-->/', '', $content);
        }
        return ['html' => $content, 'search' => $search];
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
        $apiKey = \hexa_core\Models\Setting::getValue('anthropic_api_key', '');
        AiActivityLog::logCall([
            'provider'          => 'anthropic',
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
