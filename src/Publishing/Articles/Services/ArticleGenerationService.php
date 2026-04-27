<?php

namespace hexa_app_publish\Publishing\Articles\Services;

use hexa_app_publish\Quality\Detection\Models\AiActivityLog;
use hexa_app_publish\Publishing\Articles\Services\ArticleActivityService;
use hexa_app_publish\Publishing\Settings\Models\PublishMasterSetting;
use hexa_app_publish\Publishing\Presets\Models\PublishPreset;
use hexa_app_publish\Publishing\Templates\Models\PublishTemplate;
use hexa_app_publish\Support\AiModelCatalog;
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
        $fallbackModels = collect((array) ($options['fallback_models'] ?? []))
            ->map(fn ($candidate) => trim((string) $candidate))
            ->filter()
            ->reject(fn ($candidate) => $candidate === $model)
            ->values()
            ->all();
        $templateId = $options['template_id'] ?? null;
        $templateValues = is_array($options['template_values'] ?? null) ? $options['template_values'] : [];
        $presetId = $options['preset_id'] ?? null;
        $articleType = $this->resolveArticleType($options['article_type'] ?? null, $templateId);
        $promptSlug = $options['prompt_slug'] ?? null;
        $customPrompt = $options['custom_prompt'] ?? null;
        $supportingUrlType = $options['supporting_url_type'] ?? 'matching_content_type';
        $changeRequest = $options['change_request'] ?? null;
        $prSubjectContext = $options['pr_subject_context'] ?? null;
        $agent = $options['agent'] ?? 'spin';
        $articleId = isset($options['article_id']) ? (int) $options['article_id'] : null;

        // Build the system prompt
        $systemPrompt = $this->buildPrompt(
            $sourceTexts,
            $templateId,
            $presetId,
            $customPrompt,
            $changeRequest,
            $prSubjectContext,
            false,
            $promptSlug,
            $articleType,
            $templateValues
        );

        // Inject web research instruction if requested
        if (!empty($options['web_research'])) {
            $systemPrompt .= "\n\nWEB RESEARCH: Before writing, search the web for current data, statistics, expert opinions, and recent developments related to this topic. Incorporate real, verifiable facts and supporting points from your research into the article. Cite specific sources where possible.";
        }

        $supportingUrlInstruction = $this->supportingUrlTypeInstruction($supportingUrlType);
        if ($supportingUrlInstruction !== '') {
            $systemPrompt .= "\n\n" . $supportingUrlInstruction;
        }

        $catalog = app(AiModelCatalog::class);
        $provider = $catalog->providerForModel($model);
        $result = null;
        $lastError = null;
        $attemptedModels = [];

        foreach (array_values(array_unique(array_merge([$model], $fallbackModels))) as $candidateModel) {
            $attemptedModels[] = $candidateModel;
            $provider = $catalog->providerForModel($candidateModel);
            $result = $this->callProvider($provider, $candidateModel, $systemPrompt, !empty($options['web_research']));
            app(ArticleActivityService::class)->record($articleId, [
                'activity_group' => 'ai:' . ($agent ?: 'spin'),
                'activity_type' => 'ai',
                'stage' => 'generation',
                'substage' => (($result['success'] ?? false) ? 'attempt_ok' : 'attempt_failed'),
                'status' => (($result['success'] ?? false) ? 'success' : 'failed'),
                'provider' => $provider,
                'model' => $candidateModel,
                'agent' => $agent,
                'method' => 'chat',
                'attempt_no' => count($attemptedModels),
                'is_retry' => count($attemptedModels) > 1,
                'success' => (bool) ($result['success'] ?? false),
                'message' => $result['message'] ?? null,
                'request_payload' => [
                    'prompt' => $systemPrompt,
                    'web_research' => !empty($options['web_research']),
                    'supporting_url_type' => $supportingUrlType,
                    'fallback_models' => $fallbackModels,
                ],
                'response_payload' => [
                    'usage' => $result['data']['usage'] ?? [],
                    'model' => $result['data']['model'] ?? $candidateModel,
                    'content_preview' => Str::limit((string) ($result['data']['content'] ?? ''), 4000, ''),
                ],
            ]);
            if (($result['success'] ?? false) === true) {
                $model = $candidateModel;
                break;
            }

            $lastError = $result['message'] ?? 'AI call failed';
        }

        if (!$result || !$result['success']) {
            return [
                'success' => false,
                'message' => $lastError ?? ($result['message'] ?? 'AI call failed'),
                'html' => '', 'text' => '', 'word_count' => 0, 'usage' => [],
                'model' => $model, 'cost' => 0, 'photo_suggestions' => [],
                'featured_image' => null, 'metadata' => [], 'resolved_prompt' => $systemPrompt,
                'attempted_models' => $attemptedModels,
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
        $metadata['data'] = $this->normalizeMetadataForArticleType($metadata['data'], $articleType, $sourceTexts);
        $content = $metadata['html'];
        [$content, $metadata['data']] = $this->postProcessGeneratedOutput($content, $metadata['data'], $articleType, $sourceTexts);
        $content = $this->stripLeadingTitleHeading($content, (string) ($metadata['data']['titles'][0] ?? ''));
        $content = $this->stripLeadingSectionHeading($content);

        $content = trim($content);
        $plainText = strip_tags($content);
        $wordCount = str_word_count($plainText);
        $cost = $this->calculateCost($model, $usage);

        // Log AI activity
        $this->logActivity($model, $agent, $systemPrompt, $content, $usage, $articleId);

        return [
            'success'          => true,
            'message'          => "Article generated: {$wordCount} words.",
            'html'             => $content,
            'text'             => $plainText,
            'word_count'       => $wordCount,
            'usage'            => $usage,
            'model'            => $result['data']['model'] ?? $model,
            'cost'             => round($cost, 6),
            'provider'         => $provider,
            'photo_suggestions' => $photoSuggestions['photos'],
            'featured_image'   => $featuredImage['search'],
            'featured_meta'    => $featuredImage['featured_meta'] ?? null,
            'metadata'         => $metadata['data'],
            'resolved_prompt'  => $systemPrompt,
            'attempted_models' => $attemptedModels,
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
     * @param string|null $articleType
     * @param array<string, mixed> $templateValues
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
        ?string $promptSlug = null,
        ?string $articleType = null,
        array $templateValues = []
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
        $photoCount = '2-3';
        $template = null;
        $resolvedArticleType = $articleType;
        if ($templateId) {
            $template = PublishTemplate::find($templateId);
            if ($template) {
                $templateSource = array_replace($template->toArray(), $templateValues);
                $parts = [];
                $resolvedArticleType = $resolvedArticleType ?: ($templateSource['article_type'] ?? $template->article_type);
                if (!empty($templateSource['ai_prompt'])) $parts[] = (string) $templateSource['ai_prompt'];
                if (!empty($templateSource['headline_rules'])) $parts[] = "Headline rules: {$templateSource['headline_rules']}";
                if (!empty($templateSource['tone'])) $parts[] = "Tone: " . (is_array($templateSource['tone']) ? implode(', ', $templateSource['tone']) : $templateSource['tone']);
                if ($resolvedArticleType) $parts[] = "Article type: {$resolvedArticleType}";
                if (!empty($templateSource['word_count_min']) || !empty($templateSource['word_count_max'])) $parts[] = "Target words: " . ($templateSource['word_count_min'] ?? '') . '-' . ($templateSource['word_count_max'] ?? '');
                if (!empty($templateSource['h2_notation'])) $parts[] = "H2 notation: {$templateSource['h2_notation']}";
                $templateConfig = implode("\n", $parts);
                $photoMin = max(1, (int) ($templateSource['inline_photo_min'] ?? $template->inline_photo_min ?: 2));
                $photoMax = max($photoMin, (int) ($templateSource['inline_photo_max'] ?? $template->inline_photo_max ?: ($template->photos_per_article ?: 3)));
                $photoCount = $photoMin === $photoMax ? (string) $photoMax : ($photoMin . '-' . $photoMax);
            }
        } elseif ($resolvedArticleType) {
            $templateConfig = "Article type: {$resolvedArticleType}";
        }

        // Dynamic metadata counts — pull from preset, fall back to defaults
        $titleCount = 10;
        $categoryCount = 10;
        $tagCount = 10;
        $titleSource = 'Default (10)';
        $categorySource = 'Default minimum (10)';
        $tagSource = 'Default minimum (10)';

        if ($preset) {
            if ($preset->default_category_count) {
                $requested = (int) $preset->default_category_count;
                $categoryCount = max(10, $requested);
                $categorySource = "Preset: {$preset->name} → default_category_count = {$requested}" . ($categoryCount !== $requested ? ' (raised to minimum 10)' : '');
            }
            if ($preset->default_tag_count) {
                $requested = (int) $preset->default_tag_count;
                $tagCount = max(10, $requested);
                $tagSource = "Preset: {$preset->name} → default_tag_count = {$requested}" . ($tagCount !== $requested ? ' (raised to minimum 10)' : '');
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
                'source' => $template ? "Template: {$template->name} → inline photos = {$photoCount}" : 'Default (2-3)',
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
        $articleTypeContract = $this->articleTypeInstruction($resolvedArticleType);
        if ($articleTypeContract !== '') {
            $result .= "\n\n=== ARTICLE TYPE CONTRACT ===\n" . $articleTypeContract;
            $log[] = ['shortcode' => '(article type)', 'source' => $resolvedArticleType ?: 'Empty', 'value' => $articleTypeContract];
        }
        $result .= "\n\n" . $this->h2NotationInstruction($template?->h2_notation ?? null)
            . "\n\n" . $this->hardLinkSafetyRules()
            . "\n\n" . $this->hardStockPhotoRules();

        $log[] = ['shortcode' => '(link safety)', 'source' => 'Hardcoded safeguard', 'value' => 'Require live canonical supporting URLs only.'];
        $log[] = ['shortcode' => '(stock photo safety)', 'source' => 'Hardcoded safeguard', 'value' => 'Never use article-specific names for generic stock photos.'];

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
            $url = is_array($src) ? ($src['url'] ?? '') : '';
            $text = is_array($src) ? ($src['text'] ?? $src) : $src;
            $str .= "\n=== Source {$num} ===\n";
            if ($title) $str .= "Title: {$title}\n";
            if ($url) $str .= "Source URL: {$url}\n";
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
        return "You are a professional content writer. Rewrite the provided source articles into a single new unique article.\n\n{custom_instructions}\n\n{wordpress_guidelines}\n\n{spinning_guidelines}\n\n{preset_config}\n\n{template_config}\n\nCRITICAL OUTPUT FORMAT: You MUST output valid HTML only. Do NOT include the article title anywhere in the body — no <h1> and no title-like <h2> at the top. The title is handled separately. Start the body directly with the first paragraph of content. Use <h2> ONLY for section subheadings within the article, not as a title. Use <p> for paragraphs. Use <strong> and <em> for emphasis. Use <ul>/<ol>/<li> for lists. Use <blockquote> for quotes. Use <a href=\"\"> for links. Do NOT output markdown.\n\nSUPPORTING LINKS: Include up to 3-5 relevant external links only when you can use real live canonical URLs. Never invent a URL, never guess a slug, and never use homepages, search pages, topic pages, category pages, author pages, archive pages, or redirect/tracking links. If you are not sure a supporting URL is real and live, omit it.\n\nPHOTO PLACEMENT: Insert HTML comments for photos at natural breaking points. Use this EXACT format:\n<!-- PHOTO: stock photo search term | alt text describing what the stock photo shows | caption describing the photo scene and how it relates to the topic | seo-filename -->\n\nIMPORTANT: These are STOCK PHOTOS, not real photos of the people in the article. The alt text and caption must describe the visual content of the stock photo, NOT name or reference specific people from the article unless the stock image source clearly identifies that exact real person.\n\nPlace {photo_count} photo markers.\n\nFEATURED IMAGE: Output one line:\n<!-- FEATURED: stock photo search term | alt text describing the stock photo | caption describing the photo scene | seo-filename -->\n\nMETADATA: At the very end of your response, output a JSON block:\n<!-- METADATA: {\"titles\":[\"title1\",\"title2\",...{title_count} titles],\"categories\":[\"cat1\",\"cat2\",...{category_count} categories],\"tags\":[\"tag1\",\"tag2\",...{tag_count} tags],\"description\":\"Short SEO summary of the article\"} -->\n\nThe titles should be compelling and SEO-friendly. Categories are broad topics. Tags are specific keywords. Description must be clean SEO copy only. Do not include instructions, parenthetical notes, or character-count reminders. Keep it under 160 characters.\n\n{source_articles}";
    }

    private function hardLinkSafetyRules(): string
    {
        return "LINK SAFETY RULES: Supporting URLs must be direct live article or primary-source pages that currently resolve. Never fabricate URLs or guess paths. Never use homepages, topic indexes, search results, author pages, archives, AMP mirrors, or redirect/tracking URLs. If uncertain, omit the link.";
    }

    private function hardStockPhotoRules(): string
    {
        return "STOCK PHOTO RULES: FEATURED IMAGE must always be suitable for Google image search. INLINE PHOTOS may be generic stock or Google image results. For PHOTO and FEATURED metadata, describe only what a generic stock photo visibly shows. Do not use article-specific names or identities unless the image source itself clearly identifies that exact real person.";
    }

    private function h2NotationInstruction(?string $notation): string
    {
        return match ($notation) {
            'sentence_case' => 'H2 RULES: All H2 subheadings must use sentence case.',
            'title_case' => 'H2 RULES: All H2 subheadings must use title case.',
            default => 'H2 RULES: All H2 subheadings must use Capital Case.',
        };
    }

    private function articleTypeInstruction(?string $articleType): string
    {
        return match ($articleType) {
            'editorial' => 'This article must read as an editorial analysis, not a source roundup. Build one coherent thesis from the source material. If the sources are broad or partially mismatched, ignore outlier details and focus on the strongest shared development or argument instead of forcing every source into the same piece. The title must sound like a clear analytical angle or argument, not a pasted source headline. Do not reuse source titles verbatim, do not mention publisher names in the title, and do not output list-like or stitched headlines.',
            'opinion' => 'This article must read as opinion. Lead with a clear point of view, defend it with evidence, and make the stance obvious in both the title and opening. Do not drift into neutral roundup language.',
            'news-report' => 'This article must read as straight news reporting. The title and opening paragraph must center on the concrete development, identify what happened, and avoid feature-style thesis language or opinion framing.',
            'local-news' => 'This article must read as local news. The headline and lede must clearly ground the story in the place, people, and immediate development. Avoid generic national-analysis framing.',
            'expert-article' => 'This article must read as expert analysis. The title should signal expertise and a clear angle. The body should explain, interpret, and advise rather than merely summarize sources.',
            'press-release' => 'This article must read as a formal press release with announcement framing, not as an editorial or news digest.',
            'pr-full-feature' => 'This article must read as a polished feature with a coherent narrative arc, not as a stitched source summary.',
            default => '',
        };
    }

    private function callProvider(string $provider, string $model, string $systemPrompt, bool $webResearch): array
    {
        if ($provider === 'grok') {
            if (!class_exists(\hexa_package_grok\Services\GrokService::class)) {
                return ['success' => false, 'message' => 'Grok package not available'];
            }

            return app(\hexa_package_grok\Services\GrokService::class)->chat($systemPrompt, 'Generate the article now.', $model, 0.7, 8192);
        }

        if ($provider === 'openai') {
            if (!class_exists(\hexa_package_chatgpt\Services\ChatGptService::class)) {
                return ['success' => false, 'message' => 'ChatGPT package not available'];
            }

            return app(\hexa_package_chatgpt\Services\ChatGptService::class)->chat($systemPrompt, 'Generate the article now.', $model, 0.7, 8192);
        }

        if ($provider === 'gemini') {
            if (!class_exists(\hexa_package_gemini\Services\GeminiService::class)) {
                return ['success' => false, 'message' => 'Gemini package not available'];
            }

            $gemini = app(\hexa_package_gemini\Services\GeminiService::class);

            return $webResearch
                ? $gemini->chatWithGoogleSearch($systemPrompt, 'Generate the article now.', $model, 0.7, 8192)
                : $gemini->chat($systemPrompt, 'Generate the article now.', $model, 0.7, 8192);
        }

        return $this->anthropic->chat($systemPrompt, 'Generate the article now.', $model, 8192);
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
                $placeholder = '<div class="photo-placeholder" contenteditable="false" data-idx="' . $i . '" data-search="' . htmlspecialchars($searchTerm) . '" data-caption="' . htmlspecialchars($altText) . '" style="border:2px dashed #a78bfa;background:#f5f3ff;border-radius:8px;padding:12px 16px;margin:16px 0;cursor:pointer;text-align:center;color:#7c3aed;font-size:13px;">'
                    . 'Loading photo...'
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

    private function postProcessGeneratedOutput(string $content, array $metadata, ?string $articleType, array $sourceTexts): array
    {
        if ($articleType !== 'press-release') {
            return [$content, $metadata];
        }

        $details = $this->extractValidatedDetails($sourceTexts);
        $targets = $this->extractPodcastPressReleaseTargets($sourceTexts);
        $content = $this->normalizePressReleaseYoutubeEmbed($content);
        $content = $this->ensurePodcastPressReleaseYoutubeEmbed($content, $targets);
        $content = $this->ensurePodcastPressReleaseFirstMentionLinks($content, $targets);
        $content = $this->ensurePodcastPressReleaseInlineGuestImage($content, $targets);
        $content = $this->normalizePressReleaseDateline($content, $details);

        return [$content, $metadata];
    }

    private function extractValidatedDetails(array $sourceTexts): array
    {
        $combined = $this->flattenSourceTexts($sourceTexts);
        if ($combined === '' || !preg_match('/=== Validated Details ===\s*(.*?)(?:\n===|\z)/s', $combined, $match)) {
            return ['date' => '', 'location' => '', 'contact' => '', 'contact_url' => ''];
        }

        $detailsText = trim((string) $match[1]);
        $extract = static function (string $label) use ($detailsText): string {
            return preg_match('/^' . preg_quote($label, '/') . ':\s*(.+)$/mi', $detailsText, $valueMatch)
                ? trim((string) $valueMatch[1])
                : '';
        };

        return [
            'date' => $extract('Date'),
            'location' => $extract('Location'),
            'contact' => $extract('Contact'),
            'contact_url' => $extract('Contact URL'),
        ];
    }

    private function flattenSourceTexts(array $sourceTexts): string
    {
        $chunks = [];
        foreach ($sourceTexts as $source) {
            if (is_array($source)) {
                $chunks[] = trim((string) ($source['text'] ?? $source['content'] ?? $source['body'] ?? $source['title'] ?? ''));
                continue;
            }

            $chunks[] = trim((string) $source);
        }

        return trim(implode("\n\n", array_filter($chunks)));
    }

    private function normalizePressReleaseYoutubeEmbed(string $content): string
    {
        $content = preg_replace('/<div\b[^>]*>\s*(<iframe\b[^>]*youtube\.com\/embed\/[^>]*><\/iframe>)\s*<\/div>/is', '$1', $content) ?? $content;
        $content = preg_replace('/(<iframe\b[^>]*?)\s+style="[^"]*"/i', '$1', $content) ?? $content;

        return $content;
    }

    private function extractPodcastPressReleaseTargets(array $sourceTexts): array
    {
        $combined = $this->flattenSourceTexts($sourceTexts);
        if ($combined === '' || !str_contains($combined, '=== Podcast Press Release Mission ===')) {
            return [];
        }

        if (!preg_match('/=== Canonical Link Targets ===\s*(.*?)(?:\n===|\z)/s', $combined, $match)) {
            return [];
        }

        $block = trim((string) $match[1]);
        $extract = static function (string $label) use ($block): string {
            return preg_match('/^' . preg_quote($label, '/') . ':\s*(.+)$/mi', $block, $valueMatch)
                ? trim((string) $valueMatch[1])
                : '';
        };

        return [
            'person_name' => $extract('Person Name'),
            'person_url' => $extract('Person URL'),
            'company_name' => $extract('Company Name'),
            'company_url' => $extract('Company URL'),
            'episode_url' => $extract('Episode URL'),
            'youtube_url' => $extract('YouTube URL'),
            'youtube_embed_url' => $extract('YouTube Embed URL'),
            'featured_image_url' => $extract('Featured Image URL'),
            'inline_guest_image_url' => $extract('Preferred Inline Guest Image URL'),
            'contact_url' => $extract('Contact URL'),
        ];
    }

    private function ensurePodcastPressReleaseYoutubeEmbed(string $content, array $targets): string
    {
        $embedUrl = trim((string) ($targets['youtube_embed_url'] ?? ''));
        if ($embedUrl === '' || preg_match('/youtube\.com\/embed\//i', $content)) {
            return $content;
        }

        $iframe = '<div class="podcast-youtube-embed"><iframe width="560" height="315" src="'
            . e($embedUrl)
            . '" title="YouTube video player" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" referrerpolicy="strict-origin-when-cross-origin" allowfullscreen></iframe></div>';

        if (preg_match('/<h[2-6]\b[^>]*>\s*About\b/i', $content)) {
            return preg_replace('/<h[2-6]\b[^>]*>\s*About\b/i', $iframe . '$0', $content, 1) ?? ($content . $iframe);
        }

        if (preg_match('/<h[2-6]\b/i', $content)) {
            return preg_replace('/<h[2-6]\b/i', $iframe . '$0', $content, 1) ?? ($content . $iframe);
        }

        if (preg_match('/(<p\b[^>]*>.*?<\/p>)/is', $content, $match)) {
            return preg_replace('/' . preg_quote($match[1], '/') . '/is', $match[1] . $iframe, $content, 1) ?? ($content . $iframe);
        }

        return $content . $iframe;
    }

    private function ensurePodcastPressReleaseFirstMentionLinks(string $content, array $targets): string
    {
        $content = $this->linkFirstPlainTextOccurrence(
            $content,
            trim((string) ($targets['person_name'] ?? '')),
            trim((string) ($targets['person_url'] ?? ''))
        );

        return $this->linkFirstPlainTextOccurrence(
            $content,
            trim((string) ($targets['company_name'] ?? '')),
            trim((string) ($targets['company_url'] ?? ''))
        );
    }

    private function ensurePodcastPressReleaseInlineGuestImage(string $content, array $targets): string
    {
        $imageUrl = trim((string) ($targets['inline_guest_image_url'] ?? ''));
        if ($content === '' || $imageUrl === '' || str_contains($content, $imageUrl)) {
            return $content;
        }

        $personName = trim((string) ($targets['person_name'] ?? ''));
        $companyName = trim((string) ($targets['company_name'] ?? ''));
        $alt = e($personName !== '' ? $personName : 'Podcast guest');
        $captionBase = $personName !== '' ? $personName : 'Podcast guest';
        $caption = $companyName !== '' ? ($captionBase . ' of ' . $companyName) : $captionBase;

        $figure = '<figure class="podcast-inline-guest-photo"><img src="'
            . e($imageUrl)
            . '" alt="'
            . $alt
            . '" loading="lazy"><figcaption>'
            . e($caption)
            . '</figcaption></figure>';

        if (preg_match('/<h[2-6]\b[^>]*>\s*About\b/i', $content)) {
            return preg_replace('/<h[2-6]\b[^>]*>\s*About\b/i', $figure . '$0', $content, 1) ?? ($content . $figure);
        }

        $paragraphMatches = [];
        if (preg_match_all('/<p\b[^>]*>.*?<\/p>/is', $content, $paragraphMatches) && count($paragraphMatches[0]) >= 1) {
            $insertAfter = $paragraphMatches[0][min(1, count($paragraphMatches[0]) - 1)];
            return preg_replace('/' . preg_quote($insertAfter, '/') . '/is', $insertAfter . $figure, $content, 1) ?? ($content . $figure);
        }

        if (preg_match('/<h[2-6]\b/i', $content)) {
            return preg_replace('/<h[2-6]\b/i', $figure . '$0', $content, 1) ?? ($content . $figure);
        }

        return $content . $figure;
    }

    private function linkFirstPlainTextOccurrence(string $content, string $label, string $url): string
    {
        if ($content === '' || $label === '' || $url === '' || !class_exists(\DOMDocument::class)) {
            return $content;
        }

        if (preg_match('/<a\b[^>]*>\s*' . preg_quote($label, '/') . '\s*<\/a>/iu', $content)) {
            return $content;
        }

        $previous = libxml_use_internal_errors(true);
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $html = '<?xml encoding="utf-8" ?><div id="hexa-link-root">' . $content . '</div>';
        if (!$dom->loadHTML($html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD)) {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
            return $content;
        }

        $xpath = new \DOMXPath($dom);
        $nodes = $xpath->query('//text()[normalize-space() != "" and not(ancestor::a) and not(ancestor::script) and not(ancestor::style) and not(ancestor::h1) and not(ancestor::h2) and not(ancestor::h3) and not(ancestor::h4) and not(ancestor::h5) and not(ancestor::h6)]');
        if (!$nodes) {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
            return $content;
        }

        foreach ($nodes as $textNode) {
            $text = $textNode->nodeValue ?? '';
            $offset = mb_stripos($text, $label);
            if ($offset === false) {
                continue;
            }

            $before = mb_substr($text, 0, $offset);
            $match = mb_substr($text, $offset, mb_strlen($label));
            $after = mb_substr($text, $offset + mb_strlen($label));
            $fragment = $dom->createDocumentFragment();

            if ($before !== '') {
                $fragment->appendChild($dom->createTextNode($before));
            }

            $anchor = $dom->createElement('a');
            $anchor->setAttribute('href', $url);
            $anchor->setAttribute('target', '_blank');
            $anchor->setAttribute('rel', 'noopener noreferrer');
            $anchor->appendChild($dom->createTextNode($match));
            $fragment->appendChild($anchor);

            if ($after !== '') {
                $fragment->appendChild($dom->createTextNode($after));
            }

            $textNode->parentNode?->replaceChild($fragment, $textNode);
            $root = $dom->getElementById('hexa-link-root');
            $result = $root ? $this->innerHtml($root) : $content;
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
            return $result;
        }

        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        return $content;
    }

    private function innerHtml(\DOMNode $node): string
    {
        $html = '';
        foreach ($node->childNodes as $child) {
            $html .= $node->ownerDocument?->saveHTML($child) ?? '';
        }

        return $html;
    }

    private function normalizePressReleaseDateline(string $content, array $details): string
    {
        $date = trim((string) ($details['date'] ?? ''));
        $location = trim((string) ($details['location'] ?? ''));
        if ($date === '' || $location === '') {
            return $content;
        }

        if (!preg_match('/<p\b[^>]*>(.*?)<\/p>/is', $content, $match)) {
            return $content;
        }

        $firstParagraph = $match[1];
        $lead = htmlspecialchars($location . ' (Hexa PR Wire - ' . $date . ')', ENT_QUOTES, 'UTF-8');
        $updated = preg_replace(
            '/^\s*(?:<strong>)?[^<]*?\(\s*Hexa PR Wire(?:\s*[-–]\s*[^)]*)?\)\s*(?:<\/strong>)?\s*[-–]\s*/u',
            '<strong>' . $lead . '</strong> - ',
            $firstParagraph,
            1,
            $count
        );

        if (!$count || $updated === null) {
            return $content;
        }

        return preg_replace('/<p\b[^>]*>.*?<\/p>/is', '<p>' . $updated . '</p>', $content, 1) ?? $content;
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

    private function normalizeMetadataForArticleType(array $metadata, ?string $articleType, array $sourceTexts): array
    {
        $titles = collect((array) ($metadata['titles'] ?? []))
            ->map(fn ($title) => $this->normalizeTitleText((string) $title))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($titles === []) {
            $metadata['titles'] = [];
            return $metadata;
        }

        $sourceTitles = collect($sourceTexts)
            ->map(fn ($src) => is_array($src) ? $this->normalizeTitleText((string) ($src['title'] ?? '')) : '')
            ->filter()
            ->values()
            ->all();

        $selectedIndex = 0;
        foreach ($titles as $index => $candidate) {
            if ($this->titleMatchesArticleType($candidate, $articleType, $sourceTitles)) {
                $selectedIndex = $index;
                break;
            }
        }

        if ($selectedIndex !== 0) {
            $selected = $titles[$selectedIndex];
            unset($titles[$selectedIndex]);
            array_unshift($titles, $selected);
            $titles = array_values($titles);
        }

        $metadata['titles'] = $titles;
        $metadata['description'] = $this->normalizeDescriptionText((string) ($metadata['description'] ?? ''));

        return $metadata;
    }

    private function normalizeTitleText(string $title): string
    {
        $title = html_entity_decode(strip_tags($title), ENT_QUOTES, 'UTF-8');
        $title = preg_replace('/\s+/', ' ', $title);
        return trim((string) $title);
    }

    private function normalizeDescriptionText(string $description): string
    {
        $description = html_entity_decode(strip_tags($description), ENT_QUOTES, 'UTF-8');
        $description = preg_replace('/\bseo meta description\b[:\s-]*/i', '', $description);
        $description = preg_replace('/\(\s*under\s*\d+\s*characters?\s*\)/i', '', $description);
        $description = preg_replace('/\(\s*max(?:imum)?\s*\d+\s*characters?\s*\)/i', '', $description);
        $description = preg_replace('/\bunder\s*\d+\s*characters?\b/i', '', $description);
        $description = preg_replace('/\b(?:a|an)\s+1-?2 sentence SEO meta description summarizing the article\b/i', '', $description);
        $description = preg_replace('/\s+/', ' ', (string) $description);
        $description = trim((string) $description, " \t\n\r\0\x0B\"'()-");

        if ($description === '') {
            return '';
        }

        if (mb_strlen($description) <= 160) {
            return $description;
        }

        $truncated = mb_substr($description, 0, 160);
        $lastSpace = mb_strrpos($truncated, ' ');
        if ($lastSpace !== false && $lastSpace >= 80) {
            $truncated = mb_substr($truncated, 0, $lastSpace);
        }

        return trim($truncated, " \t\n\r\0\x0B,;:-");
    }

    private function titleMatchesArticleType(string $title, ?string $articleType, array $sourceTitles): bool
    {
        if ($title === '') {
            return false;
        }

        if (mb_strlen($title) < 24 || mb_strlen($title) > 120) {
            return false;
        }

        if (str_contains($title, '...') || preg_match('/\.\.\.$/', $title)) {
            return false;
        }

        if (preg_match('/\b(source|sources|round-?up|live updates?)\b/i', $title)) {
            return false;
        }

        foreach ($sourceTitles as $sourceTitle) {
            if ($this->titlesAreTooSimilar($title, $sourceTitle)) {
                return false;
            }
        }

        if (in_array($articleType, ['editorial', 'opinion'], true) && preg_match('/^(breaking|live|watch)\b/i', $title)) {
            return false;
        }

        if (in_array($articleType, ['news-report', 'local-news'], true) && str_contains($title, '?')) {
            return false;
        }

        return true;
    }

    private function titlesAreTooSimilar(string $left, string $right): bool
    {
        $left = Str::of(Str::lower($left))->replaceMatches('/[^a-z0-9\s]/', ' ')->replaceMatches('/\s+/', ' ')->trim()->value();
        $right = Str::of(Str::lower($right))->replaceMatches('/[^a-z0-9\s]/', ' ')->replaceMatches('/\s+/', ' ')->trim()->value();

        if ($left === '' || $right === '') {
            return false;
        }

        if ($left === $right) {
            return true;
        }

        similar_text($left, $right, $percent);
        if ($percent >= 72.0) {
            return true;
        }

        $leftTokens = array_values(array_filter(explode(' ', $left)));
        $rightTokens = array_values(array_filter(explode(' ', $right)));
        if ($leftTokens === [] || $rightTokens === []) {
            return false;
        }

        $intersection = count(array_intersect($leftTokens, $rightTokens));
        $union = count(array_unique(array_merge($leftTokens, $rightTokens)));

        return $union > 0 && ($intersection / $union) >= 0.8;
    }

    private function resolveArticleType(?string $articleType, ?int $templateId): ?string
    {
        if (is_string($articleType) && trim($articleType) !== '') {
            return trim($articleType);
        }

        if ($templateId) {
            return PublishTemplate::find($templateId)?->article_type;
        }

        return null;
    }

    private function stripLeadingTitleHeading(string $content, string $title): string
    {
        $content = trim($content);
        $title = trim(html_entity_decode(strip_tags($title), ENT_QUOTES, 'UTF-8'));
        if ($content === '' || $title === '') {
            return $content;
        }

        if (preg_match('/^\s*<h2[^>]*>(.*?)<\/h2>\s*/is', $content, $match)) {
            $headingText = trim(html_entity_decode(strip_tags($match[1]), ENT_QUOTES, 'UTF-8'));
            if ($headingText !== '' && Str::lower($headingText) === Str::lower($title)) {
                $content = preg_replace('/^\s*<h2[^>]*>.*?<\/h2>\s*/is', '', $content, 1) ?? $content;
            }
        }

        return trim($content);
    }

    private function stripLeadingSectionHeading(string $content): string
    {
        return trim((string) preg_replace('/^\s*<h2[^>]*>.*?<\/h2>\s*/is', '', trim($content), 1));
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
        return app(AiModelCatalog::class)->calculateCost($model, $usage);
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
    private function logActivity(string $model, string $agent, string $systemPrompt, string $content, array $usage, ?int $articleId = null): void
    {
        $catalog = app(AiModelCatalog::class);
        $provider = $catalog->providerForModel($model);
        $credentialService = app(\hexa_core\Services\CredentialService::class);

        $apiKey = match ($provider) {
            'grok' => $credentialService->get('grok', 'api_key') ?? '',
            'gemini' => $credentialService->get('gemini', 'api_key') ?? '',
            'openai' => \hexa_core\Models\Setting::getValue('chatgpt_api_key', ''),
            default => \hexa_core\Models\Setting::getValue('anthropic_api_key', ''),
        };

        AiActivityLog::logCall([
            'publish_article_id'  => $articleId,
            'provider'          => $provider,
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
