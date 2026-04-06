<?php

namespace hexa_app_publish\Publishing\Uploads\Console;

use hexa_app_publish\Publishing\Uploads\Services\ArticleUploadService;
use Illuminate\Console\Command;

/**
 * Clean up orphaned temp uploads — article files that were never published.
 *
 * Usage: php artisan publish:cleanup-uploads --days=7
 */
class CleanupOrphanUploadsCommand extends Command
{
    protected $signature = 'publish:cleanup-uploads {--days=7 : Delete temp files older than this many days}';
    protected $description = 'Clean up orphaned article temp uploads that were never published';

    /**
     * @param ArticleUploadService $service
     * @return int
     */
    public function handle(ArticleUploadService $service): int
    {
        $days = (int) $this->option('days');
        $this->info("Cleaning up temp article uploads older than {$days} days...");

        $count = $service->cleanupOrphans($days);

        $this->info("Cleaned up {$count} orphaned file(s).");

        return self::SUCCESS;
    }
}
