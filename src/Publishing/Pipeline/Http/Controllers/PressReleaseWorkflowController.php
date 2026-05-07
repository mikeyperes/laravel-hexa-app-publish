<?php

namespace hexa_app_publish\Publishing\Pipeline\Http\Controllers;

use hexa_app_publish\Publishing\Articles\Models\PublishArticle;
use hexa_app_publish\Publishing\Pipeline\Http\Requests\PressReleaseDetectFieldsRequest;
use hexa_app_publish\Publishing\Pipeline\Http\Requests\PressReleaseImportNotionEpisodeRequest;
use hexa_app_publish\Publishing\Pipeline\Http\Requests\PressReleaseDetectPhotosRequest;
use hexa_app_publish\Publishing\Pipeline\Http\Requests\PressReleaseSearchNotionEpisodesRequest;
use hexa_app_publish\Publishing\Pipeline\Http\Requests\PressReleaseDocumentUploadRequest;
use hexa_app_publish\Publishing\Pipeline\Services\PipelineStateService;
use hexa_app_publish\Publishing\Pipeline\Services\PressReleaseFieldDetectionService;
use hexa_app_publish\Publishing\Pipeline\Services\PressReleasePhotoDetectionService;
use hexa_app_publish\Publishing\Pipeline\Services\PressReleaseNotionBookImportService;
use hexa_app_publish\Publishing\Pipeline\Services\PressReleaseNotionPodcastImportService;
use hexa_app_publish\Publishing\Pipeline\Services\PressReleaseSourceResolver;
use hexa_app_publish\Publishing\Pipeline\Services\PressReleaseWorkflowService;
use hexa_core\Http\Controllers\Controller;
use hexa_package_upload_portal\Upload\Core\Services\UploadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PressReleaseWorkflowController extends Controller
{
    public function __construct(
        private PipelineStateService $stateService,
        private PressReleaseWorkflowService $workflow,
        private PressReleaseSourceResolver $sourceResolver,
        private PressReleaseFieldDetectionService $fieldDetection,
        private PressReleasePhotoDetectionService $photoDetection,
        private PressReleaseNotionBookImportService $notionBookImport,
        private PressReleaseNotionPodcastImportService $notionPodcastImport,
        private UploadService $uploadService
    ) {}

    public function searchNotionEpisodes(PressReleaseSearchNotionEpisodesRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $this->resolveDraft((int) $validated['draft_id']);

        $result = $this->notionPodcastImport->searchEpisodes(
            (string) ($validated['query'] ?? ''),
            (int) ($validated['limit'] ?? 10)
        );

        return response()->json([
            'success' => (bool) ($result['success'] ?? false),
            'message' => $result['message'] ?? '',
            'records' => $result['records'] ?? [],
        ], ($result['success'] ?? false) ? 200 : 422);
    }

    public function smartSearchNotionEpisodes(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'draft_id' => ['required', 'integer'],
            'q' => ['nullable', 'string'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:15'],
        ]);

        $this->resolveDraft((int) $validated['draft_id']);

        $result = $this->notionPodcastImport->searchEpisodes(
            (string) ($validated['q'] ?? ''),
            (int) ($validated['limit'] ?? 10)
        );

        if (!($result['success'] ?? false)) {
            return response()->json([]);
        }

        return response()->json(array_values($result['records'] ?? []));
    }

    public function smartSearchNotionPeople(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'draft_id' => ['required', 'integer'],
            'q' => ['nullable', 'string'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:15'],
        ]);

        $this->resolveDraft((int) $validated['draft_id']);

        $result = $this->notionBookImport->searchPeople(
            (string) ($validated['q'] ?? ''),
            (int) ($validated['limit'] ?? 10)
        );

        if (!($result['success'] ?? false)) {
            return response()->json([]);
        }

        return response()->json(array_values($result['records'] ?? []));
    }

    public function listNotionPersonBooks(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'draft_id' => ['required', 'integer'],
            'person_id' => ['required', 'string', 'max:255'],
        ]);

        $draft = $this->resolveDraft((int) $validated['draft_id']);
        $payload = $this->stateService->payload($draft);
        $pressRelease = $this->workflow->normalizeState($payload['pressRelease'] ?? []);

        $result = $this->notionBookImport->listRelatedBooks((string) $validated['person_id']);
        if (!($result['success'] ?? false)) {
            return response()->json([
                'success' => false,
                'message' => $result['message'] ?? 'Failed to load the related books for that Notion person.',
            ], 422);
        }

        $pressRelease['submit_method'] = 'notion-book';
        $pressRelease['notion_person'] = $result['selected_person'] ?? [];
        $pressRelease['notion_book_options'] = $result['records'] ?? [];
        $pressRelease['notion_book'] = [];
        $pressRelease['notion_episode'] = [];
        $pressRelease['notion_guest'] = [];
        $pressRelease['notion_host'] = [];
        $pressRelease['notion_podcast'] = [];
        $pressRelease['resolved_source_text'] = '';
        $pressRelease['resolved_source_preview'] = '';
        $pressRelease['resolved_source_label'] = '';
        $pressRelease['content_dump'] = '';
        $pressRelease['detected_photos'] = [];
        $pressRelease['photo_method'] = 'notion-import';
        $pressRelease['google_drive_url'] = '';
        $pressRelease['notion_missing_fields'] = [];
        $pressRelease['notion_source_fields'] = [
            'person' => array_values(array_filter((array) ($result['source_fields']['person'] ?? []), 'is_array')),
            'book' => [],
            'episode' => [],
            'guest' => [],
            'host' => [],
            'podcast' => [],
            'enforcement' => [],
        ];
        $pressRelease = $this->workflow->appendLog($pressRelease, 'info', 'Loaded related books from the selected Notion person.', [
            'person_id' => $pressRelease['notion_person']['id'] ?? null,
            'person_name' => $pressRelease['notion_person']['name'] ?? null,
            'book_count' => count((array) ($pressRelease['notion_book_options'] ?? [])),
        ]);
        $this->stateService->updatePressRelease($draft, $pressRelease);

        return response()->json([
            'success' => true,
            'message' => $result['message'] ?? 'Related books loaded.',
            'records' => $pressRelease['notion_book_options'],
            'selected_person' => $pressRelease['notion_person'],
            'press_release' => $pressRelease,
        ]);
    }

    public function importNotionBook(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'draft_id' => ['required', 'integer'],
            'person_id' => ['required', 'string', 'max:255'],
            'book_id' => ['required', 'string', 'max:255'],
        ]);

        $draft = $this->resolveDraft((int) $validated['draft_id']);
        $payload = $this->stateService->payload($draft);
        $pressRelease = $this->workflow->normalizeState($payload['pressRelease'] ?? []);

        $result = $this->notionBookImport->importBook(
            (string) $validated['person_id'],
            (string) $validated['book_id']
        );

        if (!($result['success'] ?? false)) {
            return response()->json([
                'success' => false,
                'message' => $result['message'] ?? 'Failed to import the selected Notion book.',
            ], 422);
        }

        $pressRelease['submit_method'] = 'notion-book';
        $pressRelease['resolved_source_text'] = $result['source_text'] ?? '';
        $pressRelease['resolved_source_preview'] = $result['preview'] ?? '';
        $pressRelease['resolved_source_label'] = $result['label'] ?? 'Notion Book';
        $pressRelease['content_dump'] = $result['source_text'] ?? '';
        $pressRelease['details'] = array_replace($pressRelease['details'] ?? [], $result['details'] ?? []);
        $pressRelease['detected_photos'] = $result['detected_photos'] ?? [];
        $pressRelease['photo_method'] = 'notion-import';
        $pressRelease['google_drive_url'] = '';
        $pressRelease['notion_person'] = $result['selected_person'] ?? [];
        $pressRelease['notion_book'] = $result['selected_book'] ?? [];
        $pressRelease['notion_book_options'] = array_values(array_filter((array) ($pressRelease['notion_book_options'] ?? []), 'is_array'));
        $pressRelease['notion_episode'] = [];
        $pressRelease['notion_guest'] = [];
        $pressRelease['notion_host'] = [];
        $pressRelease['notion_podcast'] = [];
        $pressRelease['notion_missing_fields'] = $result['missing_fields'] ?? [];
        $pressRelease['notion_source_fields'] = $result['source_fields'] ?? [
            'person' => [],
            'book' => [],
            'episode' => [],
            'guest' => [],
            'host' => [],
            'podcast' => [],
            'enforcement' => [],
        ];
        $pressRelease = $this->workflow->appendLog($pressRelease, 'success', 'Imported book context from Notion.', [
            'person_id' => $pressRelease['notion_person']['id'] ?? null,
            'person_name' => $pressRelease['notion_person']['name'] ?? null,
            'book_id' => $pressRelease['notion_book']['id'] ?? null,
            'book_title' => $pressRelease['notion_book']['title'] ?? null,
        ]);
        $this->stateService->updatePressRelease($draft, $pressRelease);

        return response()->json([
            'success' => true,
            'message' => $result['message'] ?? 'Book imported from Notion.',
            'press_release' => $pressRelease,
        ]);
    }

    public function importNotionEpisode(PressReleaseImportNotionEpisodeRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $draft = $this->resolveDraft((int) $validated['draft_id']);
        $payload = $this->stateService->payload($draft);
        $pressRelease = $this->workflow->normalizeState($payload['pressRelease'] ?? []);

        $result = $this->notionPodcastImport->importEpisode((string) $validated['page_id']);
        if (!($result['success'] ?? false)) {
            return response()->json([
                'success' => false,
                'message' => $result['message'] ?? 'Failed to import podcast episode from Notion.',
            ], 422);
        }

        $pressRelease['submit_method'] = 'notion-podcast';
        $pressRelease['resolved_source_text'] = $result['source_text'] ?? '';
        $pressRelease['resolved_source_preview'] = $result['preview'] ?? '';
        $pressRelease['resolved_source_label'] = $result['label'] ?? 'Notion Podcast Episode';
        $pressRelease['content_dump'] = $result['source_text'] ?? '';
        $pressRelease['details'] = array_replace($pressRelease['details'] ?? [], $result['details'] ?? []);
        $pressRelease['detected_photos'] = $result['detected_photos'] ?? [];
        $pressRelease['photo_method'] = 'notion-import';
        $pressRelease['google_drive_url'] = '';
        $pressRelease['notion_person'] = [];
        $pressRelease['notion_book'] = [];
        $pressRelease['notion_book_options'] = [];
        $pressRelease['notion_episode'] = $result['selected_episode'] ?? [];
        $pressRelease['notion_guest'] = $result['selected_guest'] ?? [];
        $pressRelease['notion_host'] = $result['selected_host'] ?? [];
        $pressRelease['notion_podcast'] = $result['selected_podcast'] ?? [];
        $pressRelease['notion_missing_fields'] = $result['missing_fields'] ?? [];
        $pressRelease['notion_source_fields'] = $result['source_fields'] ?? [
            'episode' => [],
            'guest' => [],
            'host' => [],
            'podcast' => [],
            'enforcement' => [],
        ];
        $pressRelease = $this->workflow->appendLog($pressRelease, 'success', 'Imported podcast episode from Notion.', [
            'episode_id' => $pressRelease['notion_episode']['id'] ?? null,
            'episode_title' => $pressRelease['notion_episode']['title'] ?? null,
            'guest' => $pressRelease['notion_guest']['name'] ?? null,
            'host' => $pressRelease['notion_host']['name'] ?? null,
            'podcast' => $pressRelease['notion_podcast']['name'] ?? null,
        ]);
        $this->stateService->updatePressRelease($draft, $pressRelease);

        return response()->json([
            'success' => true,
            'message' => $result['message'] ?? 'Podcast episode imported from Notion.',
            'press_release' => $pressRelease,
        ]);
    }

    public function uploadDocuments(PressReleaseDocumentUploadRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $draft = $this->resolveDraft((int) $validated['draft_id']);
        $payload = $this->stateService->payload($draft);
        $pressRelease = $this->workflow->normalizeState($payload['pressRelease'] ?? []);

        $uploaded = [];
        foreach ($request->file('documents', []) as $document) {
            $record = $this->uploadService->upload(
                $document,
                PressReleaseWorkflowService::DOCUMENT_CONTEXT,
                $draft->id,
                auth()->id(),
                true
            );

            $uploaded[] = [
                'id' => $record->id,
                'filename' => $record->filename,
                'original_name' => $record->original_name,
                'size' => $record->size,
                'mime_type' => $record->mime_type,
                'url' => Storage::disk($record->disk)->url($record->path),
                'path' => $record->path,
            ];
        }

        $pressRelease['document_files'] = array_values(array_merge($pressRelease['document_files'], $uploaded));
        $pressRelease = $this->workflow->appendLog($pressRelease, 'success', count($uploaded) . ' press release document(s) uploaded.', [
            'count' => count($uploaded),
        ]);
        $this->stateService->updatePressRelease($draft, $pressRelease);

        return response()->json([
            'success' => true,
            'message' => count($uploaded) . ' document(s) uploaded.',
            'documents' => $pressRelease['document_files'],
            'press_release' => $pressRelease,
        ]);
    }

    public function detectFields(PressReleaseDetectFieldsRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $draft = $this->resolveDraft((int) $validated['draft_id']);
        $payload = $this->stateService->payload($draft);
        $pressRelease = $this->workflow->normalizeState($payload['pressRelease'] ?? []);

        if (in_array(($pressRelease['submit_method'] ?? ''), ['notion-podcast', 'notion-book'], true)) {
            $pressRelease = $this->workflow->appendLog($pressRelease, 'info', 'Skipped AI field detection; using imported Notion details.', [
                'details' => $pressRelease['details'] ?? [],
            ]);
            $this->stateService->updatePressRelease($draft, $pressRelease);

            return response()->json([
                'success' => true,
                'message' => 'Using imported Notion details.',
                'fields' => $pressRelease['details'],
                'press_release' => $pressRelease,
                'resolved_source_preview' => $pressRelease['resolved_source_preview'] ?? '',
            ]);
        }

        $source = $this->sourceResolver->resolve($pressRelease);
        $pressRelease = $this->workflow->replaceLog($pressRelease, $source['log'] ?? []);

        if (!$source['success']) {
            $this->stateService->updatePressRelease($draft, $pressRelease);

            return response()->json([
                'success' => false,
                'message' => $source['message'] ?? 'Unable to resolve press release source.',
                'press_release' => $pressRelease,
            ], 422);
        }

        $pressRelease['resolved_source_text'] = $source['source_text'];
        $pressRelease['resolved_source_preview'] = $source['preview'];
        $pressRelease['resolved_source_label'] = $source['label'];

        $detection = $this->fieldDetection->detect($pressRelease, $source['source_text'], $validated['model'] ?? 'claude-sonnet-4-20250514');
        $pressRelease['details'] = $detection['fields'];
        $pressRelease = $this->workflow->replaceLog($pressRelease, array_merge($pressRelease['activity_log'], $detection['log'] ?? []));

        $this->stateService->updatePressRelease($draft, $pressRelease);

        return response()->json([
            'success' => $detection['success'],
            'message' => $detection['message'],
            'fields' => $pressRelease['details'],
            'press_release' => $pressRelease,
            'resolved_source_preview' => $pressRelease['resolved_source_preview'],
        ], $detection['success'] ? 200 : 422);
    }

    public function detectPhotos(PressReleaseDetectPhotosRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $draft = $this->resolveDraft((int) $validated['draft_id']);
        $payload = $this->stateService->payload($draft);
        $pressRelease = $this->workflow->normalizeState($payload['pressRelease'] ?? []);

        if (in_array(($pressRelease['submit_method'] ?? ''), ['notion-podcast', 'notion-book'], true) && !empty($pressRelease['detected_photos'])) {
            $pressRelease = $this->workflow->appendLog($pressRelease, 'info', 'Using imported Notion media instead of URL photo detection.', [
                'count' => count((array) ($pressRelease['detected_photos'] ?? [])),
            ]);
            $this->stateService->updatePressRelease($draft, $pressRelease);

            return response()->json([
                'success' => true,
                'message' => 'Using imported Notion media.',
                'photos' => $pressRelease['detected_photos'],
                'press_release' => $pressRelease,
            ]);
        }

        $photoUrl = trim((string) ($pressRelease['photo_public_url'] ?: $pressRelease['public_url']));
        if ($photoUrl === '') {
            return response()->json([
                'success' => false,
                'message' => 'No public press release URL is available for photo detection.',
            ], 422);
        }

        $result = $this->photoDetection->detectFromUrl($photoUrl);
        $pressRelease['detected_photos'] = $result['photos'];
        $pressRelease['photo_public_url'] = $photoUrl;
        $pressRelease = $this->workflow->replaceLog($pressRelease, array_merge($pressRelease['activity_log'], $result['log'] ?? []));
        $this->stateService->updatePressRelease($draft, $pressRelease);

        return response()->json([
            'success' => $result['success'],
            'message' => $result['message'],
            'photos' => $pressRelease['detected_photos'],
            'press_release' => $pressRelease,
        ], $result['success'] ? 200 : 422);
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
