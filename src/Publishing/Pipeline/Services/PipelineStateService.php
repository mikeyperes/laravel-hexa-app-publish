<?php

namespace hexa_app_publish\Publishing\Pipeline\Services;

use hexa_app_publish\Publishing\Articles\Models\PublishArticle;
use hexa_app_publish\Publishing\Pipeline\Models\PublishPipelineState;

class PipelineStateService
{
    private const NON_CANONICAL_PAYLOAD_KEYS = [
        'resolvedPrompt',
        '_saved_at',
    ];

    public function __construct(
        private PressReleaseWorkflowService $pressReleaseWorkflow,
        private PrArticleWorkflowService $prArticleWorkflow,
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
        $payload = $this->normalizePayload($state?->payload ?? []);

        if ($state?->updated_at) {
            $payload['_saved_at'] = $state->updated_at->toIso8601String();
        }

        return $payload;
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
        if (array_key_exists('selectedPrProfiles', $payload)) {
            $payload['selectedPrProfiles'] = $this->sanitizePrProfilesForPersistence($payload['selectedPrProfiles']);
        }
        if (array_key_exists('prSubjectData', $payload)) {
            $payload['prSubjectData'] = $this->sanitizePrSubjectDataForPersistence($payload['prSubjectData']);
        }
        if (array_key_exists('articleTitle', $payload)) {
            $payload['articleTitle'] = trim((string) ($payload['articleTitle'] ?? ''));
        }
        if (array_key_exists('editorContent', $payload)) {
            $payload['editorContent'] = (string) ($payload['editorContent'] ?? '');
        }
        if (array_key_exists('spunContent', $payload)) {
            $payload['spunContent'] = (string) ($payload['spunContent'] ?? '');
        }
        $payload['pressRelease'] = $this->pressReleaseWorkflow->normalizeState(array_replace_recursive(
            $legacyPressRelease,
            (array) ($payload['pressRelease'] ?? [])
        ));
        $payload['prArticle'] = $this->prArticleWorkflow->normalizeState((array) ($payload['prArticle'] ?? []));

        return $payload;
    }

    private function sanitizePrProfilesForPersistence(mixed $profiles): array
    {
        if (!is_array($profiles)) {
            return [];
        }

        return array_values(array_filter(array_map(function ($profile) {
            if (!is_array($profile)) {
                return null;
            }

            $id = $profile['id'] ?? null;
            if ($id === null || $id === '') {
                return null;
            }

            return [
                'id' => is_numeric($id) ? (int) $id : (string) $id,
                'name' => trim((string) ($profile['name'] ?? '')),
                'type' => trim((string) ($profile['type'] ?? '—')),
                'type_slug' => trim((string) ($profile['type_slug'] ?? '')),
                'description' => trim((string) ($profile['description'] ?? '')),
                'photo_url' => trim((string) ($profile['photo_url'] ?? '')),
                'external_source' => trim((string) ($profile['external_source'] ?? '')),
                'external_id' => trim((string) ($profile['external_id'] ?? '')),
                'context' => (string) ($profile['context'] ?? ''),
                'fields' => is_array($profile['fields'] ?? null) ? $profile['fields'] : [],
            ];
        }, $profiles)));
    }

    private function sanitizePrSubjectDataForPersistence(mixed $subjectData): array
    {
        if (!is_array($subjectData)) {
            return [];
        }

        $normalized = [];

        foreach ($subjectData as $profileId => $state) {
            if (!is_array($state)) {
                continue;
            }

            $normalized[(string) $profileId] = [
                'loading' => false,
                'loaded' => !empty($state['loaded']) || !empty($state['fields']) || !empty($state['relations']) || !empty($state['photos']),
                'fields' => $this->sanitizePrFieldsForPersistence($state['fields'] ?? []),
                'driveUrl' => $this->sanitizeDriveUrl($state['driveUrl'] ?? ''),
                'photos' => $this->sanitizePrPhotosForPersistence($state['photos'] ?? []),
                'loadingPhotos' => false,
                'notionUrl' => trim((string) ($state['notionUrl'] ?? '')),
                'relations' => $this->sanitizePrRelationsForPersistence($state['relations'] ?? []),
                'selectedEntries' => $this->sanitizeBooleanSelectionMap($state['selectedEntries'] ?? []),
                'selectedPhotos' => $this->sanitizeBooleanSelectionMap($state['selectedPhotos'] ?? []),
            ];
        }

        return $normalized;
    }

    private function sanitizePrFieldsForPersistence(mixed $fields): array
    {
        if (!is_array($fields)) {
            return [];
        }

        return array_values(array_filter(array_map(function ($field) {
            if (!is_array($field)) {
                return null;
            }

            $key = trim((string) ($field['key'] ?? ''));
            $notionField = trim((string) ($field['notion_field'] ?? $key));
            if ($key === '' && $notionField === '') {
                return null;
            }

            return [
                'key' => $key,
                'notion_field' => $notionField,
                'value' => is_array($field['value'] ?? null) ? $field['value'] : (string) ($field['value'] ?? ''),
                'display_value' => is_array($field['display_value'] ?? null) ? $field['display_value'] : (string) ($field['display_value'] ?? ''),
            ];
        }, $fields)));
    }

    private function sanitizePrPhotosForPersistence(mixed $photos): array
    {
        if (!is_array($photos)) {
            return [];
        }

        $normalized = [];
        $seen = [];

        foreach ($photos as $photo) {
            if (!is_array($photo)) {
                continue;
            }

            $url = trim((string) ($photo['webContentLink'] ?? $photo['webViewLink'] ?? $photo['thumbnailLink'] ?? ''));
            if ($url === '' || isset($seen[$url])) {
                continue;
            }

            $seen[$url] = true;
            $normalized[] = [
                'id' => (string) ($photo['id'] ?? md5($url)),
                'name' => trim((string) ($photo['name'] ?? 'Subject Photo')),
                'property' => trim((string) ($photo['property'] ?? '')),
                'source' => trim((string) ($photo['source'] ?? 'notion-profile')),
                'thumbnailLink' => trim((string) ($photo['thumbnailLink'] ?? $url)),
                'webContentLink' => $url,
                'webViewLink' => trim((string) ($photo['webViewLink'] ?? $url)),
                'width' => (int) ($photo['width'] ?? 0),
                'height' => (int) ($photo['height'] ?? 0),
            ];
        }

        return $normalized;
    }

    private function sanitizePrRelationsForPersistence(mixed $relations): array
    {
        if (!is_array($relations)) {
            return [];
        }

        return array_values(array_filter(array_map(function ($relation) {
            if (!is_array($relation)) {
                return null;
            }

            $slug = trim((string) ($relation['slug'] ?? ''));
            if ($slug === '') {
                return null;
            }

            return [
                'slug' => $slug,
                'label' => trim((string) ($relation['label'] ?? $slug)),
                'count' => (int) ($relation['count'] ?? 0),
                'preview_fields' => array_values(array_filter((array) ($relation['preview_fields'] ?? []), fn ($field) => is_string($field) && $field !== '')),
                'detail_fields' => array_values(array_filter((array) ($relation['detail_fields'] ?? []), fn ($field) => is_string($field) && $field !== '')),
                'open' => !array_key_exists('open', $relation) || !empty($relation['open']),
                'loading' => false,
                'loaded' => !array_key_exists('loaded', $relation) || !empty($relation['loaded']) || !empty($relation['entries']),
                'entries' => $this->sanitizePrRelationEntriesForPersistence($relation['entries'] ?? []),
            ];
        }, $relations)));
    }

    private function sanitizePrRelationEntriesForPersistence(mixed $entries): array
    {
        if (!is_array($entries)) {
            return [];
        }

        return array_values(array_filter(array_map(function ($entry) {
            if (!is_array($entry)) {
                return null;
            }

            $id = $entry['id'] ?? null;
            if ($id === null || $id === '') {
                return null;
            }

            return [
                'id' => is_numeric($id) ? (int) $id : (string) $id,
                'title' => trim((string) ($entry['title'] ?? '')),
                'preview' => is_array($entry['preview'] ?? null) ? $entry['preview'] : [],
                'open' => !empty($entry['open']),
                'loading' => false,
                'detail' => null,
            ];
        }, $entries)));
    }

    private function sanitizeBooleanSelectionMap(mixed $map): array
    {
        if (!is_array($map)) {
            return [];
        }

        $normalized = [];
        foreach ($map as $key => $value) {
            if ($value) {
                $normalized[(string) $key] = true;
            }
        }

        return $normalized;
    }

    private function sanitizeDriveUrl(mixed $value): string
    {
        $url = trim((string) $value);
        if ($url === '' || !filter_var($url, FILTER_VALIDATE_URL)) {
            return '';
        }

        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        return in_array($host, ['drive.google.com', 'docs.google.com'], true) ? $url : '';
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
