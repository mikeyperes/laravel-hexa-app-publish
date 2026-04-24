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
 */
class WordPressDeliveryService
{
    private const MODE_WPTOOLKIT = 'wptoolkit';
    private const MODE_REST = 'rest';

    protected WpToolkitService $wptoolkit;
    protected WordPressService $wp;

    public function __construct(WpToolkitService $wptoolkit, WordPressService $wp)
    {
        $this->wptoolkit = $wptoolkit;
        $this->wp = $wp;
    }

    public function createPost(PublishSite $site, string $title, string $html, string $status = 'draft', array $options = []): array
    {
        if ($this->usesWpToolkit($site)) {
            return $this->createViaSsh($site, $title, $html, $status, $options);
        }

        return $this->createViaRest($site, $title, $html, $status, $options);
    }

    public function updatePost(PublishSite $site, int $postId, string $title, string $html, string $status = 'draft', array $options = []): array
    {
        if ($this->usesWpToolkit($site)) {
            return $this->updateViaSsh($site, $postId, $title, $html, $status, $options);
        }

        return $this->updateViaRest($site, $postId, $title, $html, $status, $options);
    }

    public function inspectPost(PublishSite $site, int $postId): array
    {
        if ($this->usesWpToolkit($site)) {
            return $this->inspectViaSsh($site, $postId);
        }

        return $this->inspectViaRest($site, $postId);
    }

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
            $result['data']['post_url'] ?? null,
            $result['data']['post_status'] ?? $status,
            $result['data']['post_date'] ?? $date
        );
    }

    private function updateViaSsh(PublishSite $site, int $postId, string $title, string $html, string $status, array $options): array
    {
        $resolved = $this->resolveServer($site);
        if (!$resolved['server'] || !$site->wordpress_install_id) {
            return $this->failure("Site '{$site->name}' is missing WP Toolkit configuration.", self::MODE_WPTOOLKIT);
        }
        $transportMode = $this->wptoolkit->connectionMode($resolved['server']);

        $postData = $this->buildPostData($title, $html, $status, $options);
        $result = $this->wptoolkit->wpCliUpdatePost(
            $resolved['server'],
            $site->wordpress_install_id,
            $postId,
            $postData
        );

        if (!$result['success']) {
            return $this->failure($result['message'] ?? 'WP Toolkit update failed.', $transportMode);
        }

        return $this->success(
            $site,
            $transportMode,
            $result['message'] ?? ('Updated via WP Toolkit (' . $transportMode . ').'),
            $result['data']['post_id'] ?? $postId,
            $result['data']['post_url'] ?? null,
            $result['data']['post_status'] ?? $status,
            $result['data']['post_date'] ?? null
        );
    }

    private function inspectViaSsh(PublishSite $site, int $postId): array
    {
        $resolved = $this->resolveServer($site);
        if (!$resolved['server'] || !$site->wordpress_install_id) {
            return $this->failure("Site '{$site->name}' is missing WP Toolkit configuration.", self::MODE_WPTOOLKIT);
        }
        $transportMode = $this->wptoolkit->connectionMode($resolved['server']);
        $result = $this->wptoolkit->wpCliGetPost($resolved['server'], $site->wordpress_install_id, $postId);
        if (!$result['success']) {
            return $this->failure($result['message'] ?? 'WP Toolkit inspect failed.', $transportMode);
        }

        return $this->success(
            $site,
            $transportMode,
            $result['message'] ?? ('Fetched via WP Toolkit (' . $transportMode . ').'),
            $result['data']['post_id'] ?? $postId,
            $result['data']['post_url'] ?? null,
            $result['data']['post_status'] ?? null,
            $result['data']['post_date'] ?? null
        );
    }

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
            $result['data']['post_url'] ?? null,
            $result['data']['post_status'] ?? $status,
            $result['data']['post_date'] ?? ($postData['date'] ?? null)
        );
    }

    private function updateViaRest(PublishSite $site, int $postId, string $title, string $html, string $status, array $options): array
    {
        if (!$site->wp_username || !$site->wp_application_password) {
            return $this->failure("Site '{$site->name}' has no WordPress REST credentials.", self::MODE_REST);
        }

        $postData = $this->buildPostData($title, $html, $status, $options);
        $result = $this->wp->updatePost($site->url, $site->wp_username, $site->wp_application_password, $postId, $postData);

        if (!$result['success']) {
            return $this->failure($result['message'] ?? 'REST update failed.', self::MODE_REST);
        }

        return $this->success(
            $site,
            self::MODE_REST,
            $result['message'] ?? 'Updated via REST.',
            $result['data']['post_id'] ?? $postId,
            $result['data']['post_url'] ?? null,
            $result['data']['post_status'] ?? $status,
            $result['data']['post_date'] ?? ($postData['date'] ?? null)
        );
    }

    private function inspectViaRest(PublishSite $site, int $postId): array
    {
        if (!$site->wp_username || !$site->wp_application_password) {
            return $this->failure("Site '{$site->name}' has no WordPress REST credentials.", self::MODE_REST);
        }

        $result = $this->wp->getPost($site->url, $site->wp_username, $site->wp_application_password, $postId);
        if (!$result['success']) {
            return $this->failure($result['message'] ?? 'REST inspect failed.', self::MODE_REST);
        }

        return $this->success(
            $site,
            self::MODE_REST,
            $result['message'] ?? 'Fetched via REST.',
            $result['data']['post_id'] ?? $postId,
            $result['data']['post_url'] ?? null,
            $result['data']['post_status'] ?? null,
            $result['data']['post_date'] ?? null
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

        if (!empty($options['excerpt'])) {
            $postData['excerpt'] = $options['excerpt'];
        }

        if (!empty($options['category_ids'])) {
            $postData['categories'] = array_values($options['category_ids']);
        }

        if (!empty($options['tag_ids'])) {
            $postData['tags'] = array_values($options['tag_ids']);
        }

        if (!empty($options['date'])) {
            $postData['date'] = $options['date'];
        }

        if (!empty($options['featured_media_id'])) {
            $postData['featured_media'] = (int) $options['featured_media_id'];
        }

        if (!empty($options['author'])) {
            $postData['author'] = $options['author'];
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
            'post_status' => null,
            'post_date' => null,
            'mode' => $mode,
        ];
    }

    private function success(PublishSite $site, string $mode, string $message, ?int $postId, ?string $postUrl = null, ?string $postStatus = null, ?string $postDate = null): array
    {
        $postUrl = $this->normalizePostUrl($postUrl)
            ?: ($postId ? rtrim($site->url, '/') . '/?p=' . $postId : null);

        return [
            'success' => true,
            'message' => $message,
            'post_id' => $postId,
            'post_url' => $postUrl,
            'post_status' => $postStatus,
            'post_date' => $postDate,
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

    private function resolveServer(PublishSite $site): array
    {
        $account = HostingAccount::find($site->hosting_account_id);
        $server = $account ? WhmServer::find($account->whm_server_id) : null;
        return ['server' => $server, 'account' => $account];
    }
}
