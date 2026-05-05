<?php

namespace hexa_app_publish\Publishing\Pipeline\Services;

use Illuminate\Support\Arr;

class PressReleaseWorkflowService
{
    public const DOCUMENT_CONTEXT = 'press-release-document';
    public const PHOTO_CONTEXT = 'press-release-photo';

    public function defaultState(): array
    {
        return [
            'article_type' => 'press-release',
            'submit_method' => 'content-dump',
            'content_dump' => '',
            'public_url' => '',
            'public_url_method' => 'auto',
            'document_files' => [],
            'resolved_source_text' => '',
            'resolved_source_preview' => '',
            'resolved_source_label' => '',
            'notion_episode_query' => '',
            'notion_episode' => [],
            'notion_guest' => [],
            'notion_host' => [],
            'notion_podcast' => [],
            'notion_missing_fields' => [],
            'notion_source_fields' => [
                'episode' => [],
                'guest' => [],
                'host' => [],
                'podcast' => [],
                'enforcement' => [],
            ],
            'details' => [
                'date' => '',
                'location' => '',
                'contact' => '',
                'contact_url' => '',
            ],
            'activity_log' => [],
            'photo_method' => 'google-drive',
            'google_drive_url' => '',
            'photo_public_url' => '',
            'photo_files' => [],
            'detected_photos' => [],
            'polish_only' => false,
        ];
    }

    public function normalizeState(array $state): array
    {
        $normalized = array_replace_recursive($this->defaultState(), $state);

        $normalized['document_files'] = $this->normalizeFiles($normalized['document_files'] ?? []);
        $normalized['photo_files'] = $this->normalizeFiles($normalized['photo_files'] ?? []);
        $normalized['notion_episode'] = is_array($normalized['notion_episode'] ?? null) ? $normalized['notion_episode'] : [];
        $normalized['notion_guest'] = is_array($normalized['notion_guest'] ?? null) ? $normalized['notion_guest'] : [];
        $normalized['notion_host'] = is_array($normalized['notion_host'] ?? null) ? $normalized['notion_host'] : [];
        $normalized['notion_podcast'] = is_array($normalized['notion_podcast'] ?? null) ? $normalized['notion_podcast'] : [];
        $normalized['notion_missing_fields'] = array_values(array_filter(array_map('strval', (array) ($normalized['notion_missing_fields'] ?? [])), fn ($value) => trim($value) !== ''));
        $normalized['notion_source_fields'] = $this->normalizeSourceFields($normalized['notion_source_fields'] ?? []);
        $normalized['detected_photos'] = array_values(array_map(function (array $photo) {
            return [
                'url' => (string) ($photo['url'] ?? ''),
                'thumbnail_url' => (string) ($photo['thumbnail_url'] ?? ($photo['url'] ?? '')),
                'preview_url' => (string) ($photo['preview_url'] ?? ($photo['thumbnail_url'] ?? ($photo['url'] ?? ''))),
                'source_url' => (string) ($photo['source_url'] ?? ($photo['download_url'] ?? ($photo['view_url'] ?? ($photo['url'] ?? '')))),
                'alt_text' => (string) ($photo['alt_text'] ?? ''),
                'caption' => (string) ($photo['caption'] ?? ''),
                'source' => (string) ($photo['source'] ?? ''),
                'source_label' => (string) ($photo['source_label'] ?? ($photo['source'] ?? '')),
                'source_meta_html' => (string) ($photo['source_meta_html'] ?? ''),
                'role' => (string) ($photo['role'] ?? ''),
                'download_url' => (string) ($photo['download_url'] ?? ($photo['url'] ?? '')),
                'view_url' => (string) ($photo['view_url'] ?? ($photo['url'] ?? '')),
                'drive_field' => (string) ($photo['drive_field'] ?? ''),
                'drive_folder_url' => (string) ($photo['drive_folder_url'] ?? ''),
                'filename' => (string) ($photo['filename'] ?? ''),
                'mime_type' => (string) ($photo['mime_type'] ?? ''),
                'width' => isset($photo['width']) ? (int) $photo['width'] : null,
                'height' => isset($photo['height']) ? (int) $photo['height'] : null,
            ];
        }, array_filter((array) ($normalized['detected_photos'] ?? []), fn ($photo) => is_array($photo) && !empty($photo['url']))));

        $normalized['activity_log'] = array_values(array_map(function (array $entry) {
            return [
                'type' => (string) ($entry['type'] ?? 'info'),
                'message' => (string) ($entry['message'] ?? ''),
                'timestamp' => (string) ($entry['timestamp'] ?? now()->toIso8601String()),
                'context' => (array) ($entry['context'] ?? []),
            ];
        }, array_filter((array) ($normalized['activity_log'] ?? []), 'is_array')));

        $normalized['polish_only'] = (bool) ($normalized['polish_only'] ?? false);

        return $normalized;
    }

    public function appendLog(array $state, string $type, string $message, array $context = []): array
    {
        $state = $this->normalizeState($state);
        $state['activity_log'][] = [
            'type' => $type,
            'message' => $message,
            'timestamp' => now()->toIso8601String(),
            'context' => $context,
        ];

        return $state;
    }

    public function replaceLog(array $state, array $entries): array
    {
        $state = $this->normalizeState($state);
        $state['activity_log'] = array_values($entries);

        return $this->normalizeState($state);
    }

    public function buildSpinSourceText(array $state): string
    {
        $state = $this->normalizeState($state);

        $parts = [];
        if (!empty($state['resolved_source_text'])) {
            $parts[] = "=== Submitted Source Material ===\n" . trim($state['resolved_source_text']);
        } elseif (!empty($state['content_dump'])) {
            $parts[] = "=== Submitted Source Material ===\n" . trim($state['content_dump']);
        }

        $details = array_filter($state['details'] ?? [], fn ($value) => filled($value));
        if (!empty($details)) {
            $detailLines = [];
            foreach ($details as $key => $value) {
                $detailLines[] = ucfirst(str_replace('_', ' ', $key)) . ': ' . $value;
            }
            $parts[] = "=== Validated Details ===\n" . implode("\n", $detailLines);
        }

        if (!empty($state['google_drive_url']) && ($state['submit_method'] ?? '') !== 'notion-podcast') {
            $parts[] = "=== Photo Source Reference ===\nGoogle Drive URL: " . $state['google_drive_url'];
        }

        return trim(implode("\n\n", array_filter($parts)));
    }

    public function promptSlug(array $state): string
    {
        $state = $this->normalizeState($state);

        $isPodcastImport = ($state['submit_method'] ?? '') === 'notion-podcast';

        if (!empty($state['polish_only'])) {
            return $isPodcastImport ? 'press-release-podcast-polish' : 'press-release-polish';
        }

        return $isPodcastImport ? 'press-release-podcast-spin' : 'press-release-spin';
    }

    private function normalizeFiles(array $files): array
    {
        return array_values(array_map(function (array $file) {
            return [
                'id' => (int) ($file['id'] ?? 0),
                'filename' => (string) ($file['filename'] ?? ''),
                'original_name' => (string) ($file['original_name'] ?? ''),
                'mime_type' => (string) ($file['mime_type'] ?? ''),
                'size' => (int) ($file['size'] ?? 0),
                'url' => (string) ($file['url'] ?? ''),
                'path' => (string) ($file['path'] ?? ''),
            ];
        }, array_filter($files, fn ($file) => is_array($file) && !empty(Arr::get($file, 'id')))));
    }

    private function normalizeSourceFields(mixed $raw): array
    {
        $sections = is_array($raw) ? $raw : [];
        $normalized = [
            'episode' => [],
            'guest' => [],
            'host' => [],
            'podcast' => [],
            'enforcement' => [],
        ];

        foreach (array_keys($normalized) as $section) {
            $normalized[$section] = array_values(array_map(function (array $entry) {
                return [
                    'field' => (string) ($entry['field'] ?? ''),
                    'value' => (string) ($entry['value'] ?? ''),
                    'source_field' => (string) ($entry['source_field'] ?? ''),
                    'source_table' => (string) ($entry['source_table'] ?? ''),
                ];
            }, array_filter((array) ($sections[$section] ?? []), function ($entry) {
                return is_array($entry) && filled($entry['value'] ?? null);
            })));
        }

        return $normalized;
    }
}
