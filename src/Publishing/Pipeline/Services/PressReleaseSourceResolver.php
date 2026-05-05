<?php

namespace hexa_app_publish\Publishing\Pipeline\Services;

use hexa_app_publish\Discovery\Sources\Services\SourceExtractionService;
use hexa_package_upload_portal\Upload\Media\Models\UploadedFile;

class PressReleaseSourceResolver
{
    public function __construct(
        private SourceExtractionService $sourceExtraction,
        private PressReleaseDocumentTextExtractor $documentExtractor,
        private PressReleaseWorkflowService $workflow
    ) {}

    public function resolve(array $pressRelease): array
    {
        $state = $this->workflow->normalizeState($pressRelease);
        $log = [];

        $log[] = $this->entry('info', 'Starting press release source resolution.', [
            'submit_method' => $state['submit_method'],
        ]);

        return match ($state['submit_method']) {
            'content-dump' => $this->resolveContentDump($state, $log),
            'upload-documents' => $this->resolveDocuments($state, $log),
            'public-url' => $this->resolvePublicUrl($state, $log),
            'notion-podcast' => $this->resolveNotionPodcast($state, $log),
            'notion-book' => $this->resolveNotionBook($state, $log),
            default => [
                'success' => false,
                'source_text' => '',
                'label' => '',
                'preview' => '',
                'log' => array_merge($log, [$this->entry('error', 'Unsupported submit method.')]),
                'message' => 'Unsupported submit method.',
            ],
        };
    }

    private function resolveContentDump(array $state, array $log): array
    {
        $text = trim((string) ($state['content_dump'] ?? ''));
        $log[] = $this->entry('info', 'Using submitted content dump.', [
            'characters' => strlen($text),
        ]);

        if ($text === '') {
            $log[] = $this->entry('error', 'Content dump is empty.');

            return [
                'success' => false,
                'source_text' => '',
                'label' => 'Content Dump',
                'preview' => '',
                'log' => $log,
                'message' => 'Content dump is empty.',
            ];
        }

        return [
            'success' => true,
            'source_text' => $text,
            'label' => 'Content Dump',
            'preview' => mb_substr($text, 0, 1000),
            'log' => $log,
            'message' => 'Resolved source from content dump.',
        ];
    }

    private function resolveDocuments(array $state, array $log): array
    {
        $files = UploadedFile::whereIn('id', collect($state['document_files'] ?? [])->pluck('id')->filter()->all())
            ->where('status', '!=', 'deleted')
            ->orderBy('created_at')
            ->get();

        if ($files->isEmpty()) {
            $log[] = $this->entry('error', 'No uploaded press release documents are available.');

            return [
                'success' => false,
                'source_text' => '',
                'label' => 'Uploaded Documents',
                'preview' => '',
                'log' => $log,
                'message' => 'No uploaded documents found.',
            ];
        }

        $chunks = [];
        foreach ($files as $file) {
            $result = $this->documentExtractor->extract($file);
            $log[] = $this->entry($result['success'] ? 'success' : 'warning', 'Processed document: ' . $file->original_name, [
                'method' => $result['method'] ?? 'unknown',
                'message' => $result['message'] ?? '',
            ]);

            if ($result['success'] && !empty($result['text'])) {
                $chunks[] = "=== {$file->original_name} ===\n" . trim($result['text']);
            }
        }

        $text = trim(implode("\n\n", $chunks));
        if ($text === '') {
            $log[] = $this->entry('error', 'No readable text could be extracted from the uploaded documents.');

            return [
                'success' => false,
                'source_text' => '',
                'label' => 'Uploaded Documents',
                'preview' => '',
                'log' => $log,
                'message' => 'No readable text extracted from uploaded documents.',
            ];
        }

        return [
            'success' => true,
            'source_text' => $text,
            'label' => 'Uploaded Documents',
            'preview' => mb_substr($text, 0, 1000),
            'log' => $log,
            'message' => 'Resolved source from uploaded documents.',
        ];
    }

    private function resolvePublicUrl(array $state, array $log): array
    {
        $url = trim((string) ($state['public_url'] ?? ''));
        if ($url === '') {
            $log[] = $this->entry('error', 'Public URL is empty.');

            return [
                'success' => false,
                'source_text' => '',
                'label' => 'Public URL',
                'preview' => '',
                'log' => $log,
                'message' => 'Public URL is empty.',
            ];
        }

        $log[] = $this->entry('info', 'Scraping public URL.', [
            'url' => $url,
            'method' => $state['public_url_method'],
        ]);

        $result = $this->sourceExtraction->extract($url, [
            'method' => $state['public_url_method'] ?? 'auto',
        ]);

        $log[] = $this->entry($result['success'] ? 'success' : 'error', $result['message'] ?? 'URL scrape completed.', [
            'url' => $url,
            'word_count' => $result['word_count'] ?? 0,
        ]);

        return [
            'success' => (bool) $result['success'],
            'source_text' => trim((string) ($result['text'] ?? '')),
            'label' => 'Public URL',
            'preview' => mb_substr((string) ($result['text'] ?? ''), 0, 1000),
            'log' => $log,
            'message' => $result['message'] ?? '',
        ];
    }

    private function resolveNotionPodcast(array $state, array $log): array
    {
        $text = trim((string) ($state['resolved_source_text'] ?? $state['content_dump'] ?? ''));
        $title = trim((string) (($state['notion_episode']['title'] ?? '') ?: 'Notion Podcast Episode'));

        $log[] = $this->entry('info', 'Using imported Notion podcast episode.', [
            'episode_id' => $state['notion_episode']['id'] ?? null,
            'title' => $title,
        ]);

        if ($text === '') {
            $log[] = $this->entry('error', 'No imported Notion episode content is available yet.');

            return [
                'success' => false,
                'source_text' => '',
                'label' => 'Notion Podcast Episode',
                'preview' => '',
                'log' => $log,
                'message' => 'Select a Notion podcast episode before continuing.',
            ];
        }

        return [
            'success' => true,
            'source_text' => $text,
            'label' => 'Notion Podcast Episode · ' . $title,
            'preview' => mb_substr($text, 0, 1000),
            'log' => $log,
            'message' => 'Resolved source from imported Notion podcast episode.',
        ];
    }

    private function resolveNotionBook(array $state, array $log): array
    {
        $text = trim((string) ($state['resolved_source_text'] ?? $state['content_dump'] ?? ''));
        $title = trim((string) (($state['notion_book']['title'] ?? '') ?: 'Notion Book'));

        $log[] = $this->entry('info', 'Using imported Notion book.', [
            'book_id' => $state['notion_book']['id'] ?? null,
            'title' => $title,
        ]);

        if ($text === '') {
            $log[] = $this->entry('error', 'No imported Notion book content is available yet.');

            return [
                'success' => false,
                'source_text' => '',
                'label' => 'Notion Book',
                'preview' => '',
                'log' => $log,
                'message' => 'Select a Notion person and related book before continuing.',
            ];
        }

        return [
            'success' => true,
            'source_text' => $text,
            'label' => 'Notion Book · ' . $title,
            'preview' => mb_substr($text, 0, 1000),
            'log' => $log,
            'message' => 'Resolved source from imported Notion book.',
        ];
    }

    private function entry(string $type, string $message, array $context = []): array
    {
        return [
            'type' => $type,
            'message' => $message,
            'timestamp' => now()->toIso8601String(),
            'context' => $context,
        ];
    }
}
