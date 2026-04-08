<?php

namespace hexa_app_publish\Publishing\Articles\Services;

use hexa_app_publish\Quality\Detection\Models\AiActivityLog;
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
    public function generate(string $articleHtml, string $model = 'claude-haiku-4-5-20251001'): array
    {
        $articleText = strip_tags($articleHtml);
        $prompt = "Based on this article, generate exactly:\n\n1. 10 unique title options (compelling, SEO-friendly)\n2. 15 category suggestions (broad topics)\n3. 15 tag suggestions (specific keywords)\n\nArticle:\n" . mb_substr($articleText, 0, 3000) . "\n\nRespond ONLY in this exact JSON format, no other text:\n{\"titles\":[\"title1\",...],\"categories\":[\"cat1\",...],\"tags\":[\"tag1\",...]}";

        $result = $this->anthropic->chat(
            'You are a content metadata expert. Output ONLY valid JSON. No markdown, no explanation.',
            $prompt,
            $model,
            1024
        );

        if (!$result['success']) {
            return ['success' => false, 'message' => $result['message'], 'titles' => [], 'categories' => [], 'tags' => [], 'urls' => []];
        }

        $content = $result['data']['content'] ?? '';
        $content = preg_replace('/^```json\s*/i', '', $content);
        $content = preg_replace('/\s*```$/', '', $content);
        $parsed = json_decode(trim($content), true);

        if (!$parsed || !isset($parsed['titles'])) {
            return ['success' => false, 'message' => 'Failed to parse AI response.', 'raw' => $content, 'titles' => [], 'categories' => [], 'tags' => [], 'urls' => []];
        }

        // Log the API call
        $usage = $result['data']['usage'] ?? [];
        $apiKey = \hexa_core\Models\Setting::getValue('anthropic_api_key', '');
        AiActivityLog::logCall([
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

        return [
            'success'    => true,
            'message'    => 'Metadata generated.',
            'titles'     => array_slice($parsed['titles'] ?? [], 0, 10),
            'categories' => array_slice($parsed['categories'] ?? [], 0, 15),
            'tags'       => array_slice($parsed['tags'] ?? [], 0, 15),
            'urls'       => array_slice($parsed['urls'] ?? [], 0, 10),
        ];
    }
}
