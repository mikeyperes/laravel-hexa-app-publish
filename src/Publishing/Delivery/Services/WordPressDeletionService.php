<?php

namespace hexa_app_publish\Publishing\Delivery\Services;

use hexa_app_publish\Publishing\Articles\Models\PublishArticle;
use hexa_app_publish\Publishing\Articles\Services\ArticleActivityService;
use hexa_app_publish\Publishing\Sites\Models\PublishSite;
use hexa_package_whm\Models\HostingAccount;
use hexa_package_whm\Models\WhmServer;
use hexa_package_wptoolkit\Services\WpToolkitService;
use Illuminate\Support\Facades\Http;

/**
 * ArticleDeleteService — reusable article + WP content deletion.
 *
 * Handles: local DB delete, WP post delete, WP media delete.
 * Supports both SSH (WP Toolkit) and REST API sites.
 * Returns step-by-step log for real-time UI feedback.
 */
class WordPressDeletionService
{
    protected WpToolkitService $wptoolkit;
    protected ArticleActivityService $activities;

    /**
     * @param WpToolkitService $wptoolkit
     */
    public function __construct(WpToolkitService $wptoolkit, ArticleActivityService $activities)
    {
        $this->wptoolkit = $wptoolkit;
        $this->activities = $activities;
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

        // Capture URLs before deletion for 404 verification
        $postUrl = $article->wp_post_url;

        if ($hasWpContent) {
            $log[] = ['type' => 'step', 'message' => 'Deleting WordPress content...'];

            $site = PublishSite::find($article->publish_site_id);
            if (!$site) {
                $log[] = ['type' => 'error', 'message' => 'Site not found — skipping WP delete.'];
            } else {
                $connectionType = $site->connection_type ?? 'wptoolkit';
                $isSsh = ($connectionType === 'wptoolkit');
                $isRest = !$isSsh && $site->wp_username && $site->wp_application_password;

                if ($isSsh) {
                    $log = array_merge($log, $this->deleteSsh($site, $article));
                } elseif ($isRest) {
                    $log = array_merge($log, $this->deleteRest($site, $article));
                } else {
                    $log[] = ['type' => 'error', 'message' => 'No valid SSH or REST credentials — skipping WP delete.'];
                }
            }
        }

        // Collect all deleted URLs for 404 verification
        $deletedUrls = [];
        if ($postUrl) {
            $deletedUrls[] = ['label' => 'Post', 'url' => $postUrl];
        }
        $wpImages = $article->wp_images ?? [];
        foreach ($wpImages as $img) {
            $mediaUrl = $img['media_url'] ?? $img['source_url'] ?? null;
            if ($mediaUrl) {
                $deletedUrls[] = ['label' => 'Media: ' . ($img['filename'] ?? 'image'), 'url' => $mediaUrl];
            }
        }

        // Move article to deleted/archive state instead of removing the DB row.
        $log[] = ['type' => 'step', 'message' => 'Archiving local article #' . $article->id . '...'];
        $beforeStatus = $article->status;
        $article->update([
            'status' => 'deleted',
            'wp_status' => $hasWpContent ? 'deleted' : $article->wp_status,
            'notes' => trim(implode("\n\n", array_filter([
                (string) $article->notes,
                '[Archived ' . now()->toDateTimeString() . '] Article removed from active listings and kept for audit history.',
            ]))),
        ]);
        $this->activities->record($article, [
            'activity_group' => 'lifecycle:' . ($article->article_id ?: $article->id),
            'activity_type' => 'lifecycle',
            'stage' => 'article',
            'substage' => 'archived',
            'status' => 'deleted',
            'success' => true,
            'title' => $article->title,
            'url' => $postUrl,
            'message' => 'Article moved to deleted archive.',
            'meta' => [
                'before_status' => $beforeStatus,
                'after_status' => $article->status,
                'had_wordpress_content' => $hasWpContent,
                'deleted_urls' => $deletedUrls,
            ],
        ]);
        $log[] = ['type' => 'success', 'message' => 'Article moved to deleted archive and preserved for audits.'];

        // Add deleted URLs for verification
        if (!empty($deletedUrls)) {
            $log[] = ['type' => 'info', 'message' => 'Deleted URLs — click to verify 404:', 'urls' => $deletedUrls];
        }

        hexaLog('publish', 'article_deleted', 'Article #' . $article->id . ' archived' . ($hasWpContent ? ' (including WP content)' : ''));

        return ['success' => true, 'log' => $log];
    }

    /**
     * Delete WP content via SSH (WP Toolkit / WP CLI).
     *
     * @param PublishSite $site
     * @param PublishArticle $article
     * @return array
     */
    private function deleteSsh(PublishSite $site, PublishArticle $article): array
    {
        $log = [];
        $resolved = $this->resolveServer($site);
        if (!$resolved['server']) {
            $log[] = ['type' => 'error', 'message' => 'Server not found — skipping WP delete.'];
            return $log;
        }

        $server = $resolved['server'];
        $installId = $site->wordpress_install_id;

        // Delete WP media first (referenced by the post)
        $wpImages = $article->wp_images ?? [];
        if (!empty($wpImages)) {
            $log[] = ['type' => 'step', 'message' => 'Deleting ' . count($wpImages) . ' media attachment(s) via SSH...'];
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
        $log[] = ['type' => 'step', 'message' => 'Deleting WP post #' . $article->wp_post_id . ' via SSH...'];
        $postResult = $this->wptoolkit->wpCliDeletePost($server, $installId, (int) $article->wp_post_id);
        $log[] = [
            'type' => $postResult['success'] ? 'success' : 'error',
            'message' => 'Post #' . $article->wp_post_id . ': ' . $postResult['message'],
        ];

        return $log;
    }

    /**
     * Delete WP content via REST API.
     *
     * @param PublishSite $site
     * @param PublishArticle $article
     * @return array
     */
    private function deleteRest(PublishSite $site, PublishArticle $article): array
    {
        $log = [];
        $baseUrl = rtrim($site->url, '/');

        // Delete WP media first
        $wpImages = $article->wp_images ?? [];
        if (!empty($wpImages)) {
            $log[] = ['type' => 'step', 'message' => 'Deleting ' . count($wpImages) . ' media attachment(s) via REST...'];
            foreach ($wpImages as $img) {
                $mediaId = $img['media_id'] ?? null;
                if ($mediaId) {
                    $mediaUrl = $img['media_url'] ?? $img['source_url'] ?? '';
                    $filename = $img['filename'] ?? '';
                    try {
                        $resp = Http::withBasicAuth($site->wp_username, $site->wp_application_password)
                            ->timeout(15)
                            ->delete($baseUrl . '/wp-json/wp/v2/media/' . $mediaId, ['force' => true]);
                        $log[] = [
                            'type' => $resp->successful() ? 'success' : 'error',
                            'message' => 'Media #' . $mediaId . ($filename ? ' (' . $filename . ')' : '') . ($mediaUrl ? ' — ' . $mediaUrl : '') . ': ' . ($resp->successful() ? 'Deleted.' : 'HTTP ' . $resp->status()),
                        ];
                    } catch (\Exception $e) {
                        $log[] = ['type' => 'error', 'message' => 'Media #' . $mediaId . ': ' . $e->getMessage()];
                    }
                }
            }
        }

        // Delete WP post
        $postUrl = $article->wp_post_url ?: ($baseUrl . '/?p=' . $article->wp_post_id);
        $log[] = ['type' => 'step', 'message' => 'Deleting WP post #' . $article->wp_post_id . ' (' . $postUrl . ') via REST...'];
        try {
            $resp = Http::withBasicAuth($site->wp_username, $site->wp_application_password)
                ->timeout(15)
                ->delete($baseUrl . '/wp-json/wp/v2/posts/' . $article->wp_post_id, ['force' => true]);
            $log[] = [
                'type' => $resp->successful() ? 'success' : 'error',
                'message' => 'Post #' . $article->wp_post_id . ' (' . $postUrl . '): ' . ($resp->successful() ? 'Deleted from WordPress.' : 'HTTP ' . $resp->status()),
            ];
        } catch (\Exception $e) {
            $log[] = ['type' => 'error', 'message' => 'Post #' . $article->wp_post_id . ': ' . $e->getMessage()];
        }

        return $log;
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
