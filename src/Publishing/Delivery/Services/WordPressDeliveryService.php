<?php

namespace hexa_app_publish\Publishing\Delivery\Services;

use hexa_app_publish\Publishing\Sites\Models\PublishSite;
use hexa_package_whm\Models\HostingAccount;
use hexa_package_whm\Models\WhmServer;
use hexa_package_wordpress\Services\WordPressService;
use hexa_package_wptoolkit\Services\WpToolkitService;

/**
 * WordPressDeliveryService — single source of truth for publishing to WordPress.
 *
 * Handles both SSH (WP Toolkit) and REST API sites.
 * Normalizes the result to always include post_id and post_url.
 *
 * Replaces duplicated publish logic from PipelineController::publishToWordpress()
 * and CampaignRunService publish step.
 */
class WordPressDeliveryService
{
    private const MODE_WPTOOLKIT = 'wptoolkit';
    private const MODE_REST = 'rest';

    protected WpToolkitService $wptoolkit;
    protected WordPressService $wp;

    /**
     * @param WpToolkitService $wptoolkit
     * @param WordPressService $wp
     */
    public function __construct(WpToolkitService $wptoolkit, WordPressService $wp)
    {
        $this->wptoolkit = $wptoolkit;
        $this->wp = $wp;
    }

    /**
     * Create a post on a WordPress site (SSH or REST, auto-detected).
     *
     * @param PublishSite $site
     * @param string $title
     * @param string $html
     * @param string $status publish|draft|future
     * @param array $options {
     *     @type array  $category_ids  WP category IDs
     *     @type array  $tag_ids       WP tag IDs
     *     @type string $date          Scheduled date for future posts
     * }
     * @return array{success: bool, message: string, post_id: int|null, post_url: string|null, mode: string}
     */
    public function createPost(PublishSite $site, string $title, string $html, string $status = 'draft', array $options = []): array
    {
        if ($this->usesWpToolkit($site)) {
            return $this->createViaSsh($site, $title, $html, $status, $options);
        }

        return $this->createViaRest($site, $title, $html, $status, $options);
    }

    /**
     * Create post via SSH (WP Toolkit / WP CLI).
     *
     * @param PublishSite $site
     * @param string $title
     * @param string $html
     * @param string $status
     * @param array $options
     * @return array
     */
    private function createViaSsh(PublishSite $site, string $title, string $html, string $status, array $options): array
    {
        $resolved = $this->resolveServer($site);
        if (!$resolved['server'] || !$site->wordpress_install_id) {
            return $this->failure("Site '{$site->name}' is missing WP Toolkit configuration.", self::MODE_WPTOOLKIT);
        }
        $transportMode = $this->wptoolkit->connectionMode($resolved['server']);

        $date = ($status === 'future' && !empty($options['date'])) ? $options['date'] : null;

        $result = $this->wptoolkit->wpCliCreatePost(
            $resolved['server'],
            $site->wordpress_install_id,
            $title,
            $html,
            $status,
            $options['category_ids'] ?? [],
            $options['tag_ids'] ?? [],
            $date,
            $options['author'] ?? $site->default_author ?? null,
            isset($options['featured_media_id']) ? (int) $options['featured_media_id'] : null
        );

        if (!$result['success']) {
            return $this->failure($result['message'] ?? 'WP Toolkit publish failed.', $transportMode);
        }

        $postId = $result['data']['post_id'] ?? null;

        return $this->success(
            $site,
            $transportMode,
            $result['message'] ?? ('Published via WP Toolkit (' . $transportMode . ').'),
            $postId,
            $result['data']['post_url'] ?? null
        );
    }

    /**
     * Create post via WordPress REST API.
     *
     * @param PublishSite $site
     * @param string $title
     * @param string $html
     * @param string $status
     * @param array $options
     * @return array
     */
    private function createViaRest(PublishSite $site, string $title, string $html, string $status, array $options): array
    {
        if (!$site->wp_username || !$site->wp_application_password) {
            return $this->failure("Site '{$site->name}' has no WordPress REST credentials.", self::MODE_REST);
        }

        $postData = $this->buildPostData($title, $html, $status, $options);

        $result = $this->wp->createPost($site->url, $site->wp_username, $site->wp_application_password, $postData);

        if (!$result['success']) {
            return $this->failure($result['message'] ?? 'REST publish failed.', self::MODE_REST);
        }

        return $this->success(
            $site,
            self::MODE_REST,
            $result['message'] ?? 'Published via REST.',
            $result['data']['post_id'] ?? null,
            $result['data']['post_url'] ?? null
        );
    }

    private function usesWpToolkit(PublishSite $site): bool
    {
        return ($site->connection_type ?? 'wptoolkit') === 'wptoolkit';
    }

    private function buildPostData(string $title, string $html, string $status, array $options): array
    {
        $postData = [
            'title' => $title,
            'content' => $html,
            'status' => $status,
        ];

        if (!empty($options['category_ids'])) {
            $postData['categories'] = $options['category_ids'];
        }

        if (!empty($options['tag_ids'])) {
            $postData['tags'] = $options['tag_ids'];
        }

        if ($status === 'future' && !empty($options['date'])) {
            $postData['date'] = $options['date'];
        }

        if (!empty($options['featured_media_id'])) {
            $postData['featured_media'] = (int) $options['featured_media_id'];
        }

        if (!empty($options['author'])) {
            // WP REST expects integer author ID — if numeric, use directly
            if (is_numeric($options['author'])) {
                $postData['author'] = (int) $options['author'];
            }
            // If string username, WP won't accept it in the author field —
            // it must be resolved to an ID during prepare/connection
        }

        return $postData;
    }

    private function failure(string $message, string $mode): array
    {
        return [
            'success' => false,
            'message' => $message,
            'post_id' => null,
            'post_url' => null,
            'mode' => $mode,
        ];
    }

    private function success(PublishSite $site, string $mode, string $message, ?int $postId, ?string $postUrl = null): array
    {
        $postUrl = $this->normalizePostUrl($postUrl)
            ?: ($postId ? rtrim($site->url, '/') . '/?p=' . $postId : null);

        return [
            'success' => true,
            'message' => $message,
            'post_id' => $postId,
            'post_url' => $postUrl,
            'mode' => $mode,
        ];
    }

    private function normalizePostUrl(?string $postUrl): ?string
    {
        $postUrl = trim((string) $postUrl);
        if ($postUrl === '') {
            return null;
        }

        preg_match_all('/https?:\/\/[^\s]+/i', $postUrl, $matches);
        if (!empty($matches[0])) {
            return end($matches[0]) ?: null;
        }

        return str_starts_with($postUrl, 'http') ? $postUrl : null;
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
