<?php

namespace hexa_app_publish\Publishing\Delivery\Services;

use hexa_app_publish\Models\PublishSite;
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
        $connectionType = $site->connection_type ?? 'wptoolkit';
        $isSsh = ($connectionType === 'wptoolkit');

        if ($isSsh) {
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
            return ['success' => false, 'message' => "Site '{$site->name}' is missing WP Toolkit configuration.", 'post_id' => null, 'post_url' => null, 'mode' => 'ssh'];
        }

        $date = ($status === 'future' && !empty($options['date'])) ? $options['date'] : null;

        $result = $this->wptoolkit->wpCliCreatePost(
            $resolved['server'],
            $site->wordpress_install_id,
            $title,
            $html,
            $status,
            $options['category_ids'] ?? [],
            $options['tag_ids'] ?? [],
            $date
        );

        if (!$result['success']) {
            return ['success' => false, 'message' => $result['message'] ?? 'SSH publish failed.', 'post_id' => null, 'post_url' => null, 'mode' => 'ssh'];
        }

        // SSH returns only post_id — build URL from site
        $postId = $result['data']['post_id'] ?? null;
        $postUrl = $postId ? rtrim($site->url, '/') . '/?p=' . $postId : null;

        return ['success' => true, 'message' => $result['message'] ?? 'Published via SSH.', 'post_id' => $postId, 'post_url' => $postUrl, 'mode' => 'ssh'];
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
            return ['success' => false, 'message' => "Site '{$site->name}' has no WordPress REST credentials.", 'post_id' => null, 'post_url' => null, 'mode' => 'rest'];
        }

        $postData = [
            'title'   => $title,
            'content' => $html,
            'status'  => $status,
        ];
        if (!empty($options['category_ids'])) $postData['categories'] = $options['category_ids'];
        if (!empty($options['tag_ids'])) $postData['tags'] = $options['tag_ids'];
        if ($status === 'future' && !empty($options['date'])) $postData['date'] = $options['date'];

        $result = $this->wp->createPost($site->url, $site->wp_username, $site->wp_application_password, $postData);

        if (!$result['success']) {
            return ['success' => false, 'message' => $result['message'] ?? 'REST publish failed.', 'post_id' => null, 'post_url' => null, 'mode' => 'rest'];
        }

        $postId = $result['data']['post_id'] ?? null;
        $postUrl = $result['data']['post_url'] ?? ($postId ? rtrim($site->url, '/') . '/?p=' . $postId : null);

        return ['success' => true, 'message' => $result['message'] ?? 'Published via REST.', 'post_id' => $postId, 'post_url' => $postUrl, 'mode' => 'rest'];
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
