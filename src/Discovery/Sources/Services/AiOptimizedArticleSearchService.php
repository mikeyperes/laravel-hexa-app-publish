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
    public function search(string $topic, int $count, string $model): array
    {
        $provider = $this->catalog->providerForModel($model);

        return match ($provider) {
            'grok' => $this->dispatch(
                \hexa_package_grok\Services\GrokService::class,
                'Grok package not available.',
                $topic,
                $count,
                $model,
                'Grok Optimized Search'
            ),
            'openai' => $this->dispatch(
                \hexa_package_chatgpt\Services\ChatGptService::class,
                'ChatGPT package not available.',
                $topic,
                $count,
                $model,
                'OpenAI Optimized Search'
            ),
            'gemini' => $this->dispatch(
                \hexa_package_gemini\Services\GeminiService::class,
                'Gemini package not available.',
                $topic,
                $count,
                $model,
                'Gemini Optimized Search'
            ),
            default => $this->dispatch(
                \hexa_package_anthropic\Services\AnthropicService::class,
                'Anthropic package not available.',
                $topic,
                $count,
                $model,
                'Claude Optimized Search'
            ),
        };
    }

    /**
     * @param class-string $serviceClass
     * @return array{success: bool, message: string, data: array<string, mixed>|null}
     */
    protected function dispatch(string $serviceClass, string $missingMessage, string $topic, int $count, string $model, string $backendLabel): array
    {
        if (!class_exists($serviceClass)) {
            return ['success' => false, 'message' => $missingMessage, 'data' => null];
        }

        $service = app($serviceClass);
        if (method_exists($service, 'searchArticlesOptimized')) {
            $result = $service->searchArticlesOptimized($topic, $count, $model);
        } elseif (method_exists($service, 'searchArticles')) {
            $result = $service->searchArticles($topic, $count, $model);
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
