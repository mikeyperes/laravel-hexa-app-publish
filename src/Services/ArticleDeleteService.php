<?php

namespace hexa_app_publish\Services;

use hexa_app_publish\Models\PublishArticle;
use hexa_app_publish\Models\PublishSite;
use hexa_package_whm\Models\HostingAccount;
use hexa_package_whm\Models\WhmServer;
use hexa_package_wptoolkit\Services\WpToolkitService;

/**
 * ArticleDeleteService — reusable article + WP content deletion.
 *
 * Handles: local DB delete, WP post delete, WP media delete.
 * Returns step-by-step log for real-time UI feedback.
 */
class ArticleDeleteService
{
    protected WpToolkitService $wptoolkit;

    /**
     * @param WpToolkitService $wptoolkit
     */
    public function __construct(WpToolkitService $wptoolkit)
    {
        $this->wptoolkit = $wptoolkit;
    }

    /**
     * Delete an article and optionally its WordPress content.
     * Returns an array of log entries for real-time display.
     *
     * @param PublishArticle $article
     * @return array{success: bool, log: array}
     */
    public function delete(PublishArticle $article): array
    {
        $log = [];
        $hasWpContent = !empty($article->wp_post_id) && !empty($article->publish_site_id);

        if ($hasWpContent) {
            $log[] = ['type' => 'step', 'message' => 'Deleting WordPress content...'];

            $site = PublishSite::find($article->publish_site_id);
            if (!$site) {
                $log[] = ['type' => 'error', 'message' => 'Site not found — skipping WP delete.'];
            } else {
                $resolved = $this->resolveServer($site);
                if (!$resolved['server']) {
                    $log[] = ['type' => 'error', 'message' => 'Server not found — skipping WP delete.'];
                } else {
                    $server = $resolved['server'];
                    $account = $resolved['account'];
                    $installId = $site->wordpress_install_id;

                    // Delete WP media first (referenced by the post)
                    $wpImages = $article->wp_images ?? [];
                    if (!empty($wpImages)) {
                        $log[] = ['type' => 'step', 'message' => 'Deleting ' . count($wpImages) . ' media attachment(s)...'];
                        foreach ($wpImages as $img) {
                            $mediaId = $img['media_id'] ?? null;
                            if ($mediaId) {
                                $result = $this->wptoolkit->wpCliDeleteMedia($server, $installId, (int) $mediaId);
                                $log[] = [
                                    'type' => $result['success'] ? 'success' : 'error',
                                    'message' => 'Media #' . $mediaId . ': ' . $result['message'],
                                ];
                            }
                        }
                    }

                    // Delete WP post
                    $log[] = ['type' => 'step', 'message' => 'Deleting WP post #' . $article->wp_post_id . '...'];
                    $postResult = $this->wptoolkit->wpCliDeletePost($server, $installId, (int) $article->wp_post_id);
                    $log[] = [
                        'type' => $postResult['success'] ? 'success' : 'error',
                        'message' => 'Post #' . $article->wp_post_id . ': ' . $postResult['message'],
                    ];
                }
            }
        }

        // Delete from local DB
        $log[] = ['type' => 'step', 'message' => 'Deleting local article #' . $article->id . '...'];
        $article->delete();
        $log[] = ['type' => 'success', 'message' => 'Article deleted from database.'];

        hexaLog('publish', 'article_deleted', 'Article #' . $article->id . ' deleted' . ($hasWpContent ? ' (including WP content)' : ''));

        return ['success' => true, 'log' => $log];
    }

    /**
     * Resolve WHM server for a WP Toolkit site.
     *
     * @param PublishSite $site
     * @return array{server: WhmServer|null, account: HostingAccount|null}
     */
    private function resolveServer(PublishSite $site): array
    {
        $account = HostingAccount::find($site->hosting_account_id);
        $server = $account ? WhmServer::find($account->whm_server_id) : null;
        return ['server' => $server, 'account' => $account];
    }
}
