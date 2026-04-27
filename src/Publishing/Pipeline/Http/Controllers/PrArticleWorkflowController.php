<?php

namespace hexa_app_publish\Publishing\Pipeline\Http\Controllers;

use hexa_app_publish\Publishing\Articles\Models\PublishArticle;
use hexa_app_publish\Discovery\Sources\Services\SourceExtractionService;
use hexa_core\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PrArticleWorkflowController extends Controller
{
    public function __construct(
        private SourceExtractionService $sourceExtraction
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
