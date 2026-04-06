<?php

namespace hexa_app_publish\Publishing\Uploads\Services;

use hexa_package_upload_portal\Upload\Core\Services\UploadService;

/**
 * Article-specific upload lifecycle management.
 * Wraps the generic UploadService with article context logic.
 *
 * - Temp files created during article editing
 * - Cleaned up after successful WordPress publish
 * - Kept for local drafts and scheduled posts
 * - Orphan cleanup for abandoned articles
 */
class ArticleUploadService
{
    public const CONTEXT = 'article';

    public function __construct(
        private UploadService $uploadService
    ) {}

    /**
     * Clean up temp files after successful WordPress publish.
     * Called after article is published or pushed as WP draft.
     *
     * @param int $draftId
     * @param int|null $userId
     * @return int Number of files cleaned
     */
    public function cleanupAfterPublish(int $draftId, ?int $userId = null): int
    {
        $count = $this->uploadService->cleanup(self::CONTEXT, $draftId, $userId);

        if ($count > 0 && function_exists('hexaLog')) {
            hexaLog('publish', 'upload_cleanup', "Cleaned {$count} temp upload(s) for article #{$draftId}");
        }

        return $count;
    }

    /**
     * Get all uploaded files for an article.
     *
     * @param int $draftId
     * @param int|null $userId
     * @return \Illuminate\Support\Collection
     */
    public function getArticleFiles(int $draftId, ?int $userId = null): \Illuminate\Support\Collection
    {
        return $this->uploadService->getFiles(self::CONTEXT, $draftId, $userId);
    }

    /**
     * Clean up orphaned temp files — articles with temp uploads
     * that haven't been published and are older than $daysOld days.
     *
     * @param int $daysOld
     * @return int Number of files cleaned
     */
    public function cleanupOrphans(int $daysOld = 7): int
    {
        $cutoff = now()->subDays($daysOld);

        $orphans = \hexa_package_upload_portal\Upload\Media\Models\UploadedFile::where('context', self::CONTEXT)
            ->where('status', 'temp')
            ->where('created_at', '<', $cutoff)
            ->get();

        $count = 0;
        foreach ($orphans as $file) {
            $this->uploadService->delete($file->id);
            $count++;
        }

        if ($count > 0 && function_exists('hexaLog')) {
            hexaLog('publish', 'upload_orphan_cleanup', "Cleaned {$count} orphaned temp upload(s) older than {$daysOld} days");
        }

        return $count;
    }
}
