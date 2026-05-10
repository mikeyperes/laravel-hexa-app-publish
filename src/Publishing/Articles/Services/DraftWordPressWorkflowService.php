<?php

namespace hexa_app_publish\Publishing\Articles\Services;

use hexa_app_publish\Publishing\Articles\Models\PublishArticle;
use hexa_app_publish\Publishing\Campaigns\Services\CampaignChecklistService;
use hexa_app_publish\Publishing\Delivery\Services\WordPressPreparationService;
use hexa_app_publish\Publishing\Pipeline\Services\PipelineStateService;

class DraftWordPressWorkflowService
{
    public function __construct(
        private PipelineStateService $pipelineStateService,
        private WordPressPreparationService $preparationService,
        private CampaignChecklistService $checklistService,
    ) {
    }

    public function buildPreview(PublishArticle $article): array
    {
        $article->loadMissing(['site', 'creator', 'pipelineState']);

        $payload = $this->pipelineStateService->payload($article);
        $site = $article->site;
        $siteUrl = rtrim((string) ($site?->url ?? ''), '/');
        $isPressReleaseSource = (bool) ($site?->is_press_release_source ?? false);
        $articleType = (string) ($article->article_type ?: ($payload['currentArticleType'] ?? $payload['article_type'] ?? 'editorial'));

        $categories = $this->normalizeStringList($article->categories ?: ($payload['categories'] ?? []));
        $tags = $this->normalizeStringList($article->tags ?: ($payload['tags'] ?? []));
        $publicationTermIds = ($articleType === 'press-release' && $isPressReleaseSource)
            ? $this->normalizeIntList($payload['selectedSyndicationCats'] ?? [])
            : [];

        $featuredPhoto = is_array($payload['featuredPhoto'] ?? null) ? $payload['featuredPhoto'] : null;
        $featuredUrl = $this->resolveFeaturedSourceUrl($payload, $featuredPhoto);
        $featuredMeta = $this->resolveFeaturedMeta($payload, $featuredPhoto);
        $wpImages = is_array($article->wp_images) ? $article->wp_images : [];

        return [
            'article' => [
                'id' => $article->id,
                'article_id' => $article->article_id,
                'title' => (string) ($article->title ?? ''),
                'excerpt' => (string) ($article->excerpt ?? ''),
                'body' => (string) ($article->body ?? ''),
                'word_count' => (int) ($article->word_count ?? 0),
                'status' => (string) ($article->status ?? ''),
                'delivery_mode' => (string) ($article->delivery_mode ?? 'draft-local'),
                'article_type' => $articleType,
                'author' => (string) ($article->author ?? ($site?->default_author ?? '')),
                'wp_post_id' => $article->wp_post_id ? (int) $article->wp_post_id : null,
                'wp_status' => (string) ($article->wp_status ?? ''),
                'wp_post_url' => (string) ($article->wp_post_url ?? ''),
                'wp_admin_url' => ($siteUrl !== '' && $article->wp_post_id)
                    ? ($siteUrl . '/wp-admin/post.php?post=' . $article->wp_post_id . '&action=edit')
                    : '',
                'site' => $site ? [
                    'id' => $site->id,
                    'name' => $site->name,
                    'url' => $site->url,
                    'is_press_release_source' => $isPressReleaseSource,
                    'default_author' => $site->default_author,
                ] : null,
                'categories' => $categories,
                'tags' => $tags,
                'publication_term_ids' => $publicationTermIds,
                'photo_suggestions' => $this->resolvePhotoSuggestions($article, $payload),
                'featured_url' => $featuredUrl,
                'featured_meta' => $featuredMeta,
                'featured_media_id' => $this->resolvePreparedFeaturedMediaId($article, $payload),
                'featured_wp_url' => (string) ($payload['preparedFeaturedWpUrl'] ?? ''),
                'existing_uploads' => $wpImages,
                'checklist' => $this->prepareChecklist(),
            ],
        ];
    }

    public function prepare(PublishArticle $article): array
    {
        $preview = $this->buildPreview($article);
        $data = $preview['article'];

        if (empty($data['site']['id'])) {
            return [
                'success' => false,
                'message' => 'This draft has no connected WordPress site selected.',
                'checklist' => $this->prepareChecklist(),
                'steps' => [],
                'article' => $data,
            ];
        }

        if (trim((string) $data['body']) === '') {
            return [
                'success' => false,
                'message' => 'This draft has no article body to prepare.',
                'checklist' => $this->prepareChecklist(),
                'steps' => [],
                'article' => $data,
            ];
        }

        $checklist = $this->prepareChecklist();
        $steps = [];
        $track = function (string $type, string $message, array $extra = []) use (&$steps, &$checklist): void {
            $entry = [
                'type' => $type,
                'message' => $message,
                'stage' => (string) ($extra['stage'] ?? ''),
                'substage' => (string) ($extra['substage'] ?? ''),
                'details' => (string) ($extra['details'] ?? ''),
            ];
            $steps[] = $entry;
            $this->applyChecklistEvent($checklist, $entry);
        };

        $result = $this->preparationService->prepare(
            $article->site,
            (string) $data['body'],
            [
                'title' => $data['title'],
                'categories' => $data['categories'],
                'tags' => $data['tags'],
                'publication_term_ids' => $data['publication_term_ids'],
                'photo_suggestions' => $data['photo_suggestions'],
                'featured_meta' => $data['featured_meta'],
                'featured_url' => $data['featured_url'],
                'draft_id' => $article->id,
                'existing_uploads' => $data['existing_uploads'],
                'existing_featured_media_id' => $data['featured_media_id'],
            ],
            $track
        );

        if (!$result['success']) {
            $this->finalizeChecklist($checklist, false);

            return [
                'success' => false,
                'message' => (string) ($result['message'] ?? 'WordPress preparation failed.'),
                'checklist' => array_values($checklist),
                'steps' => $steps,
                'article' => $data,
            ];
        }

        $article->update([
            'body' => (string) ($result['html'] ?? $article->body),
            'categories' => $data['categories'],
            'tags' => $data['tags'],
            'wp_images' => $result['wp_images'] ?? $article->wp_images,
        ]);

        $payload = $this->pipelineStateService->payload($article);
        $payload['category_ids'] = array_values(array_filter(array_map('intval', (array) ($result['category_ids'] ?? []))));
        $payload['tag_ids'] = array_values(array_filter(array_map('intval', (array) ($result['tag_ids'] ?? []))));
        $payload['publication_term_ids'] = array_values(array_filter(array_map('intval', (array) $data['publication_term_ids'])));
        $payload['featured_media_id'] = isset($result['featured_media_id']) ? (int) $result['featured_media_id'] : ($payload['featured_media_id'] ?? null);
        $payload['preparedFeaturedMediaId'] = $result['featured_media_id'] ?? ($payload['preparedFeaturedMediaId'] ?? null);
        $payload['preparedFeaturedWpUrl'] = $result['featured_wp_url'] ?? ($payload['preparedFeaturedWpUrl'] ?? '');
        $payload['uploadedImages'] = $this->buildExistingUploadMap($result['wp_images'] ?? []);
        $this->pipelineStateService->save($article, $payload, $article->article_type ?: null);

        $this->finalizeChecklist($checklist, true);

        return [
            'success' => true,
            'message' => 'Draft prepared for WordPress.',
            'checklist' => array_values($checklist),
            'steps' => $steps,
            'article' => $this->buildPreview($article)['article'],
            'prepared' => [
                'featured_media_id' => $result['featured_media_id'] ?? null,
                'featured_wp_url' => $result['featured_wp_url'] ?? '',
                'wp_images_count' => count($result['wp_images'] ?? []),
            ],
        ];
    }

    private function prepareChecklist(): array
    {
        $keys = ['wp_connection', 'wp_html', 'wp_media', 'wp_taxonomies', 'wp_integrity', 'delivery'];
        $items = array_values(array_filter(
            $this->checklistService->definitions('draft-wordpress'),
            fn (array $item): bool => in_array((string) ($item['key'] ?? ''), $keys, true)
        ));

        return array_map(function (array $item): array {
            $item['status'] = $item['key'] === 'delivery' ? 'pending' : 'pending';
            $item['event_lines'] = [];
            $item['live_detail'] = '';
            return $item;
        }, $items);
    }

    private function applyChecklistEvent(array &$checklist, array $entry): void
    {
        $key = match ($entry['stage']) {
            'connection' => 'wp_connection',
            'html' => 'wp_html',
            'media' => 'wp_media',
            'taxonomy' => 'wp_taxonomies',
            'integrity' => 'wp_integrity',
            default => null,
        };

        if ($key === null) {
            return;
        }

        foreach ($checklist as &$item) {
            if (($item['key'] ?? '') !== $key) {
                continue;
            }

            $item['event_lines'][] = $entry['message'];
            $item['live_detail'] = $entry['message'];

            if (in_array($entry['type'], ['error'], true)) {
                $item['status'] = 'failed';
            } elseif (in_array($entry['type'], ['success', 'done'], true)) {
                $item['status'] = 'done';
            } elseif (($item['status'] ?? 'pending') !== 'done') {
                $item['status'] = 'running';
            }

            break;
        }
        unset($item);
    }

    private function finalizeChecklist(array &$checklist, bool $success): void
    {
        foreach ($checklist as &$item) {
            if (($item['key'] ?? '') === 'delivery') {
                $item['status'] = 'pending';
                $item['live_detail'] = $success
                    ? 'Preparation complete. Create or publish the WordPress post next.'
                    : 'Preparation failed before delivery.';
                continue;
            }

            if (($item['status'] ?? 'pending') === 'running') {
                $item['status'] = $success ? 'done' : 'failed';
            }
        }
        unset($item);
    }

    private function resolvePhotoSuggestions(PublishArticle $article, array $payload): array
    {
        $suggestions = $article->photo_suggestions;
        if (!is_array($suggestions) || $suggestions === []) {
            $suggestions = is_array($payload['photoSuggestions'] ?? null) ? $payload['photoSuggestions'] : [];
        }

        return array_values(array_filter($suggestions, fn ($item) => is_array($item)));
    }

    private function resolveFeaturedSourceUrl(array $payload, ?array $featuredPhoto): string
    {
        return trim((string) (
            $payload['preparedFeaturedWpUrl']
            ?? $payload['featuredPhoto']['url_large']
            ?? $payload['featuredPhoto']['url_full']
            ?? $payload['featuredPhoto']['url']
            ?? $featuredPhoto['url_large']
            ?? $featuredPhoto['url_full']
            ?? $featuredPhoto['url']
            ?? ''
        ));
    }

    private function resolveFeaturedMeta(array $payload, ?array $featuredPhoto): ?array
    {
        $url = $this->resolveFeaturedSourceUrl($payload, $featuredPhoto);
        if ($url === '') {
            return null;
        }

        return [
            'alt_text' => trim((string) ($payload['featuredAlt'] ?? $featuredPhoto['alt'] ?? 'Featured image')),
            'caption' => trim((string) ($payload['featuredCaption'] ?? '')),
            'filename' => trim((string) ($payload['featuredFilename'] ?? 'featured-image')),
        ];
    }

    private function resolvePreparedFeaturedMediaId(PublishArticle $article, array $payload): ?int
    {
        $prepared = $payload['preparedFeaturedMediaId'] ?? null;
        if ($prepared) {
            return (int) $prepared;
        }

        foreach ((array) ($article->wp_images ?? []) as $img) {
            if (!is_array($img)) {
                continue;
            }
            if (!empty($img['is_featured']) && !empty($img['media_id'])) {
                return (int) $img['media_id'];
            }
        }

        return null;
    }

    private function buildExistingUploadMap(array $wpImages): array
    {
        $map = [];
        foreach ($wpImages as $img) {
            if (!is_array($img)) {
                continue;
            }

            $urls = array_filter(array_unique(array_merge(
                [
                    $img['source_url'] ?? null,
                    $img['inline_url'] ?? null,
                    $img['media_url'] ?? null,
                ],
                array_values((array) ($img['sizes'] ?? []))
            )));

            foreach ($urls as $url) {
                $map[(string) $url] = $img;
            }
        }

        return $map;
    }

    private function normalizeStringList(mixed $values): array
    {
        if (!is_array($values)) {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn ($value): string => trim((string) $value),
            $values
        ), static fn (string $value): bool => $value !== ''));
    }

    private function normalizeIntList(mixed $values): array
    {
        if (!is_array($values)) {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn ($value): int => (int) $value,
            $values
        )));
    }
}
