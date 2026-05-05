<?php

namespace hexa_app_publish\Publishing\Articles\Services;

use hexa_app_publish\Quality\Detection\Models\AiActivityLog;
use hexa_app_publish\Publishing\Articles\Services\ArticleActivityService;
use hexa_package_anthropic\Services\AnthropicService;

/**
 * MetadataGenerationService — generates article titles, categories, tags via AI.
 *
 * Uses Haiku for speed and cost efficiency.
 * Replaces inline metadata generation from PipelineController::generateMetadata().
 */
class MetadataGenerationService
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
     * Generate metadata (titles, categories, tags) from article HTML.
     *
     * @param string $articleHtml
     * @param string $model AI model to use (default: haiku)
     * @return array{success: bool, titles: array, categories: array, tags: array, urls: array, message: string}
     */
    public function generate(string $articleHtml, string $model = 'claude-haiku-4-5-20251001', ?int $articleId = null): array
    {
        $articleText = strip_tags($articleHtml);
        $prompt = "Based on this article, generate exactly:\n\n1. 10 unique title options (compelling, SEO-friendly)\n2. 10 category suggestions (broad topics)\n3. 10 tag suggestions (specific keywords)\n\nHeadline rules:\n- Never use em dashes or en dashes in any title.\n- Never use first-person phrasing such as I, I'm, I've, my, we, our, or us.\n- Keep the titles in third person unless the article explicitly requires a first-person essay.\n- Return all 10 title options. Do not return a single title.\n\nArticle:\n" . mb_substr($articleText, 0, 3000) . "\n\nRespond ONLY in this exact JSON format, no other text:\n{\"titles\":[\"title1\",...],\"categories\":[\"cat1\",...],\"tags\":[\"tag1\",...]}";

        $result = $this->anthropic->chat(
            'You are a content metadata expert. Output ONLY valid JSON. No markdown, no explanation.',
            $prompt,
            $model,
            1024
        );

        if (!$result['success']) {
            app(ArticleActivityService::class)->record($articleId, [
                'activity_group' => 'metadata',
                'activity_type' => 'metadata',
                'stage' => 'metadata',
                'substage' => 'failed',
                'status' => 'failed',
                'provider' => 'anthropic',
                'model' => $model,
                'agent' => 'metadata-generation',
                'method' => 'chat',
                'success' => false,
                'message' => $result['message'] ?? 'Metadata generation failed.',
                'request_payload' => ['prompt' => $prompt],
                'response_payload' => ['usage' => $result['data']['usage'] ?? []],
            ]);
            return ['success' => false, 'message' => $result['message'], 'titles' => [], 'categories' => [], 'tags' => [], 'urls' => []];
        }

        $content = $result['data']['content'] ?? '';
        $content = preg_replace('/^```json\s*/i', '', $content);
        $content = preg_replace('/\s*```$/', '', $content);
        $parsed = json_decode(trim($content), true);

        if (!$parsed || !isset($parsed['titles'])) {
            return ['success' => false, 'message' => 'Failed to parse AI response.', 'raw' => $content, 'titles' => [], 'categories' => [], 'tags' => [], 'urls' => []];
        }

        $titles = collect((array) ($parsed['titles'] ?? []))
            ->map(fn ($title) => $this->normalizeListText((string) $title))
            ->filter(fn ($title) => !$this->titleUsesFirstPerson($title))
            ->unique()
            ->values()
            ->take(10)
            ->all();

        $categories = collect((array) ($parsed['categories'] ?? []))
            ->map(fn ($value) => $this->normalizeListText((string) $value))
            ->filter()
            ->unique()
            ->values()
            ->take(10)
            ->all();

        $tags = collect((array) ($parsed['tags'] ?? []))
            ->map(fn ($value) => $this->normalizeListText((string) $value))
            ->filter()
            ->unique()
            ->values()
            ->take(10)
            ->all();

        // Log the API call
        $usage = $result['data']['usage'] ?? [];
        $apiKey = \hexa_core\Models\Setting::getValue('anthropic_api_key', '');
        AiActivityLog::logCall([
            'publish_article_id'  => $articleId,
            'provider'          => 'anthropic',
            'model'             => $model,
            'agent'             => 'metadata-generation',
            'prompt_tokens'     => $usage['input_tokens'] ?? 0,
            'completion_tokens' => $usage['output_tokens'] ?? 0,
            'system_prompt'     => 'Content metadata expert',
            'response_content'  => $content,
            'success'           => true,
            'api_key_masked'    => $apiKey ? '...' . substr($apiKey, -4) : null,
        ]);

        app(ArticleActivityService::class)->record($articleId, [
            'activity_group' => 'metadata',
            'activity_type' => 'metadata',
            'stage' => 'metadata',
            'substage' => 'complete',
            'status' => 'success',
            'provider' => 'anthropic',
            'model' => $model,
            'agent' => 'metadata-generation',
            'method' => 'chat',
            'success' => true,
            'message' => 'Metadata generated.',
            'request_payload' => ['prompt' => $prompt],
            'response_payload' => [
                'titles' => $titles,
                'categories' => $categories,
                'tags' => $tags,
                'raw' => $content,
                'usage' => $usage,
            ],
        ]);

        return [
            'success'    => true,
            'message'    => 'Metadata generated.',
            'titles'     => $titles,
            'categories' => $categories,
            'tags'       => $tags,
            'urls'       => array_slice($parsed['urls'] ?? [], 0, 10),
        ];
    }

    private function normalizeListText(string $value): string
    {
        $value = html_entity_decode(strip_tags($value), ENT_QUOTES, 'UTF-8');
        $value = str_replace(['—', '–', '―'], '-', $value);

        return trim((string) preg_replace('/\s+/', ' ', $value));
    }

    private function titleUsesFirstPerson(string $title): bool
    {
        return preg_match("/\\b(i|i'm|i’m|i've|i’ve|i'd|i’d|me|my|mine|myself|we|we're|we’re|we've|we’ve|our|ours|us)\\b/i", $title) === 1;
    }
}
