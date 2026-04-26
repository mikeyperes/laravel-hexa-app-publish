<?php

namespace hexa_app_publish\Discovery\Sources\Services;

use hexa_app_publish\Support\AiModelCatalog;
use Illuminate\Support\Str;

class AiOptimizedArticleSearchService
{
    public function __construct(
        protected AiModelCatalog $catalog,
    ) {
    }

    /**
     * @return array{success: bool, message: string, data: array<string, mixed>|null}
     */
    public function search(string $topic, int $count, string $selection): array
    {
        $resolved = $this->catalog->resolveSearchSelection($selection);
        $provider = (string) ($resolved['provider'] ?? '');
        $model = (string) ($resolved['model'] ?? '');
        $backendLabel = (string) ($resolved['backend_label'] ?? 'AI Search');
        $optimized = (($resolved['mode'] ?? 'model') === 'optimized');

        if ($provider === '' || $model === '') {
            return ['success' => false, 'message' => 'Search selection could not be resolved.', 'data' => null];
        }

        return match ($provider) {
            'grok' => $this->dispatch(
                \hexa_package_grok\Services\GrokService::class,
                'Grok package not available.',
                $topic,
                $count,
                $model,
                $backendLabel,
                $optimized
            ),
            'openai' => $this->dispatch(
                \hexa_package_chatgpt\Services\ChatGptService::class,
                'ChatGPT package not available.',
                $topic,
                $count,
                $model,
                $backendLabel,
                $optimized
            ),
            'gemini' => $this->dispatch(
                \hexa_package_gemini\Services\GeminiService::class,
                'Gemini package not available.',
                $topic,
                $count,
                $model,
                $backendLabel,
                $optimized
            ),
            default => $this->dispatch(
                \hexa_package_anthropic\Services\AnthropicService::class,
                'Anthropic package not available.',
                $topic,
                $count,
                $model,
                $backendLabel,
                $optimized
            ),
        };
    }

    /**
     * @param class-string $serviceClass
     * @return array{success: bool, message: string, data: array<string, mixed>|null}
     */
    protected function dispatch(string $serviceClass, string $missingMessage, string $topic, int $count, string $model, string $backendLabel, bool $optimized = false): array
    {
        if (!class_exists($serviceClass)) {
            return ['success' => false, 'message' => $missingMessage, 'data' => null];
        }

        $service = app($serviceClass);
        if ($optimized && method_exists($service, 'searchArticlesOptimized')) {
            $result = $service->searchArticlesOptimized($topic, $count, $model);
        } elseif (method_exists($service, 'searchArticles')) {
            $result = $service->searchArticles($topic, $count, $model);
        } elseif (method_exists($service, 'searchArticlesOptimized')) {
            $result = $service->searchArticlesOptimized($topic, $count, $model);
        } else {
            return ['success' => false, 'message' => $missingMessage, 'data' => null];
        }

        if (is_array($result['data'] ?? null)) {
            $result['data']['search_backend'] = $result['data']['search_backend'] ?? Str::slug($backendLabel, '_');
            $result['data']['search_backend_label'] = $result['data']['search_backend_label'] ?? $backendLabel;
        }

        return $result;
    }
}
