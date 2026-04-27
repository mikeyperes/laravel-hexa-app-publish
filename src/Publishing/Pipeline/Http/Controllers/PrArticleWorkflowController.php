<?php

namespace hexa_app_publish\Publishing\Pipeline\Http\Controllers;

use hexa_app_publish\Publishing\Articles\Models\PublishArticle;
use hexa_app_publish\Discovery\Sources\Services\SourceExtractionService;
use hexa_core\Http\Controllers\Controller;
use hexa_package_google_docs\Services\GoogleDocsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PrArticleWorkflowController extends Controller
{
    public function __construct(
        private SourceExtractionService $sourceExtraction,
        private GoogleDocsService $googleDocs,
    ) {}

    public function importContextUrl(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'draft_id' => 'required|integer|exists:publish_articles,id',
            'url' => 'required|url|max:2000',
        ]);

        $this->resolveDraft((int) $validated['draft_id']);

        $result = $this->sourceExtraction->extract((string) $validated['url'], [
            'method' => 'auto',
            'user_agent' => 'chrome',
            'retries' => 1,
            'timeout' => 20,
            'min_words' => 80,
            'auto_fallback' => true,
        ]);

        if (!($result['success'] ?? false)) {
            return response()->json([
                'success' => false,
                'message' => $result['message'] ?? 'Failed to import article context from the supplied URL.',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Article context imported successfully.',
            'context' => [
                'url' => (string) ($result['url'] ?? $validated['url']),
                'title' => trim((string) ($result['title'] ?? '')),
                'excerpt' => trim((string) ($result['description'] ?? '')),
                'text' => trim((string) ($result['text'] ?? '')),
                'word_count' => (int) ($result['word_count'] ?? 0),
                'image_url' => trim((string) ($result['image'] ?? '')),
            ],
        ]);
    }

    public function importGoogleDocsContext(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'draft_id' => 'required|integer|exists:publish_articles,id',
            'urls' => 'required|array|min:1|max:10',
            'urls.*' => 'required|string|min:20|max:4000',
        ]);

        $this->resolveDraft((int) $validated['draft_id']);

        $documents = [];
        $failures = [];

        foreach (collect($validated['urls'])->map(fn ($url) => trim((string) $url))->filter()->unique()->take(8) as $url) {
            $result = $this->googleDocs->fetchText($url);
            if (!($result['success'] ?? false)) {
                $failures[] = [
                    'url' => $url,
                    'message' => $result['message'] ?? 'Failed to read Google Doc.',
                ];
                continue;
            }

            $plainText = trim((string) ($result['plain_text'] ?? ''));
            if ($plainText === '') {
                $failures[] = [
                    'url' => $url,
                    'message' => 'Google Doc returned empty content.',
                ];
                continue;
            }

            $documents[] = [
                'document_id' => (string) ($result['document_id'] ?? ''),
                'url' => (string) ($result['normalized_url'] ?? $url),
                'export_url' => (string) ($result['export_url'] ?? ''),
                'title' => trim((string) ($result['title'] ?? 'Untitled Google Doc')),
                'preview' => trim((string) ($result['preview'] ?? '')),
                'text' => Str::limit($plainText, 6000, ''),
                'word_count' => (int) preg_match_all('/\S+/u', $plainText),
            ];
        }

        return response()->json([
            'success' => true,
            'message' => $documents === []
                ? 'No readable Google Docs were found in the selected subject context.'
                : count($documents) . ' Google Doc(s) imported for subject context.',
            'documents' => array_values($documents),
            'failures' => $failures,
        ]);
    }

    private function resolveDraft(int $draftId): PublishArticle
    {
        $draft = PublishArticle::findOrFail($draftId);
        $user = auth()->user();

        abort_unless(
            $user && ($user->isAdmin() || $draft->created_by === $user->id || $draft->user_id === $user->id),
            403
        );

        return $draft;
    }
}
