<?php

namespace hexa_app_publish\Publishing\Pipeline\Http\Controllers;

use hexa_app_publish\Publishing\Articles\Models\PublishArticle;
use hexa_app_publish\Publishing\Pipeline\Http\Requests\PressReleaseDetectFieldsRequest;
use hexa_app_publish\Publishing\Pipeline\Http\Requests\PressReleaseDetectPhotosRequest;
use hexa_app_publish\Publishing\Pipeline\Http\Requests\PressReleaseDocumentUploadRequest;
use hexa_app_publish\Publishing\Pipeline\Services\PipelineStateService;
use hexa_app_publish\Publishing\Pipeline\Services\PressReleaseFieldDetectionService;
use hexa_app_publish\Publishing\Pipeline\Services\PressReleasePhotoDetectionService;
use hexa_app_publish\Publishing\Pipeline\Services\PressReleaseSourceResolver;
use hexa_app_publish\Publishing\Pipeline\Services\PressReleaseWorkflowService;
use hexa_core\Http\Controllers\Controller;
use hexa_package_upload_portal\Upload\Core\Services\UploadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

class PressReleaseWorkflowController extends Controller
{
    public function __construct(
        private PipelineStateService $stateService,
        private PressReleaseWorkflowService $workflow,
        private PressReleaseSourceResolver $sourceResolver,
        private PressReleaseFieldDetectionService $fieldDetection,
        private PressReleasePhotoDetectionService $photoDetection,
        private UploadService $uploadService
    ) {}

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
