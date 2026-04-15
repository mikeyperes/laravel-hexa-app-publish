<?php

namespace hexa_app_publish\Publishing\Pipeline\Services;

use hexa_app_publish\Publishing\Articles\Models\PublishArticle;
use hexa_app_publish\Publishing\Pipeline\Models\PublishPipelineState;

class PipelineStateService
{
    private const NON_CANONICAL_PAYLOAD_KEYS = [
        'resolvedPrompt',
    ];

    public function __construct(
        private PressReleaseWorkflowService $pressReleaseWorkflow
    ) {}

    public function load(PublishArticle $article): ?PublishPipelineState
    {
        if ($article->relationLoaded('pipelineState')) {
            return $article->getRelation('pipelineState');
        }

        return $article->pipelineState()->first();
    }

    public function payload(PublishArticle $article): array
    {
        $state = $this->load($article);

        return $this->normalizePayload($state?->payload ?? []);
    }

    public function save(PublishArticle $article, array $payload, ?string $workflowType = null): PublishPipelineState
    {
        $normalized = $this->normalizePayload($payload);

        return PublishPipelineState::updateOrCreate(
            ['publish_article_id' => $article->id],
            [
                'workflow_type' => $workflowType ?: $this->detectWorkflowType($normalized),
                'state_version' => (int) ($normalized['_v'] ?? 1),
                'payload' => $normalized,
            ]
        );
    }

    public function updatePressRelease(PublishArticle $article, array $pressRelease): PublishPipelineState
    {
        $payload = $this->payload($article);
        $payload['pressRelease'] = $this->pressReleaseWorkflow->normalizeState($pressRelease);

        return $this->save($article, $payload, 'press-release');
    }

    private function normalizePayload(array $payload): array
    {
        $legacyPressRelease = [
            'details' => [
                'date' => (string) ($payload['pressReleaseDate'] ?? ''),
                'location' => (string) ($payload['pressReleaseLocation'] ?? ''),
                'contact' => (string) ($payload['pressReleaseContact'] ?? ''),
                'contact_url' => (string) ($payload['pressReleaseContactUrl'] ?? ''),
            ],
            'content_dump' => (string) ($payload['pressReleaseContent'] ?? ''),
        ];

        foreach (self::NON_CANONICAL_PAYLOAD_KEYS as $key) {
            unset($payload[$key]);
        }

        $payload['_v'] = (int) ($payload['_v'] ?? 1);
        if (array_key_exists('photoSuggestions', $payload)) {
            $payload['photoSuggestions'] = $this->sanitizePhotoSuggestionsForPersistence($payload['photoSuggestions']);
        }
        if (array_key_exists('featuredPhoto', $payload)) {
            $payload['featuredPhoto'] = $this->sanitizePhotoAssetForPersistence($payload['featuredPhoto']);
        }
        $payload['pressRelease'] = $this->pressReleaseWorkflow->normalizeState(array_replace_recursive(
            $legacyPressRelease,
            (array) ($payload['pressRelease'] ?? [])
        ));

        return $payload;
    }

    private function sanitizePhotoSuggestionsForPersistence(mixed $suggestions): array
    {
        if (!is_array($suggestions)) {
            return [];
        }

        return array_map(function ($suggestion, $index) {
            if (!is_array($suggestion)) {
                return [
                    'position' => $index,
                    'search_term' => '',
                    'alt_text' => '',
                    'caption' => '',
                    'suggestedFilename' => '',
                    'autoPhoto' => null,
                    'confirmed' => false,
                    'removed' => false,
                ];
            }

            $autoPhoto = $this->sanitizePhotoAssetForPersistence($suggestion['autoPhoto'] ?? null);

            return [
                'position' => (int) ($suggestion['position'] ?? $index),
                'search_term' => trim((string) ($suggestion['search_term'] ?? '')),
                'alt_text' => (string) ($suggestion['alt_text'] ?? ''),
                'caption' => (string) ($suggestion['caption'] ?? ''),
                'suggestedFilename' => (string) ($suggestion['suggestedFilename'] ?? ''),
                'autoPhoto' => $autoPhoto,
                'confirmed' => $autoPhoto !== null && !empty($suggestion['confirmed']),
                'removed' => !empty($suggestion['removed']),
            ];
        }, $suggestions, array_keys($suggestions));
    }

    private function sanitizePhotoAssetForPersistence(mixed $photo): ?array
    {
        if (!is_array($photo)) {
            return null;
        }

        $normalized = [
            'id' => $photo['id'] ?? null,
            'source' => (string) ($photo['source'] ?? ''),
            'source_url' => (string) ($photo['source_url'] ?? $photo['pexels_url'] ?? $photo['unsplash_url'] ?? $photo['pixabay_url'] ?? $photo['url'] ?? ''),
            'url' => (string) ($photo['url'] ?? $photo['url_large'] ?? $photo['url_full'] ?? $photo['url_thumb'] ?? ''),
            'url_thumb' => (string) ($photo['url_thumb'] ?? $photo['url_large'] ?? $photo['url_full'] ?? $photo['url'] ?? ''),
            'url_large' => (string) ($photo['url_large'] ?? $photo['url_full'] ?? $photo['url_thumb'] ?? $photo['url'] ?? ''),
            'url_full' => (string) ($photo['url_full'] ?? $photo['url_large'] ?? $photo['url_thumb'] ?? $photo['url'] ?? ''),
            'alt' => (string) ($photo['alt'] ?? ''),
            'photographer' => (string) ($photo['photographer'] ?? ''),
            'photographer_url' => (string) ($photo['photographer_url'] ?? ''),
            'width' => (int) ($photo['width'] ?? 0),
            'height' => (int) ($photo['height'] ?? 0),
        ];

        if (
            $normalized['url'] === ''
            && $normalized['url_thumb'] === ''
            && $normalized['url_large'] === ''
            && $normalized['url_full'] === ''
        ) {
            return null;
        }

        return $normalized;
    }

    private function detectWorkflowType(array $payload): ?string
    {
        $articleType = data_get($payload, 'template_overrides.article_type')
            ?? data_get($payload, 'selectedTemplate.article_type')
            ?? data_get($payload, 'pressRelease.article_type');

        return $articleType === 'press-release' ? 'press-release' : $articleType;
    }
}
