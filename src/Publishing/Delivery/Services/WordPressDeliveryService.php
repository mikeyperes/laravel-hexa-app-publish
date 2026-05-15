<?php

namespace hexa_app_publish\Publishing\Delivery\Services;

use hexa_app_publish\Publishing\Sites\Models\PublishSite;
use hexa_package_whm\Models\HostingAccount;
use hexa_package_whm\Models\WhmServer;

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


    public function createPost(PublishSite $site, string $title, string $html, string $status = 'draft', array $options = []): array
    {
        $options = $this->normalizeSyndicationOptions($site, $options);

        if ($this->usesWpToolkit($site)) {
            return $this->createViaSsh($site, $title, $html, $status, $options);
        }

        return $this->createViaRest($site, $title, $html, $status, $options);
    }

    public function updatePost(PublishSite $site, int $postId, string $title, string $html, string $status = 'draft', array $options = []): array
    {
        $options = $this->normalizeSyndicationOptions($site, $options);

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
        $transportMode = $this->wordpress()->connectionMode($this->wpTarget($site));
        $publicationTaxonomy = (string) ($options['publication_taxonomy'] ?? 'publication');
        $isRequiredPressReleaseSyndication = $site->is_press_release_source && trim((string) ($options["article_type"] ?? "")) === "press-release";
        $expectedPublicationTerms = array_values(array_unique(array_filter(array_map("intval", (array) ($options["publication_term_ids"] ?? [])))));
        if ($isRequiredPressReleaseSyndication && $expectedPublicationTerms === []) {
            return $this->failure("Select at least one publication syndication source before preparing or publishing this press release.", $transportMode, [
                "expected_publication_term_ids" => [],
            ]);
        }

        $date = ($status === 'future' && !empty($options['date'])) ? $options['date'] : null;

        $result = $this->wordpress()->createPost($this->wpTarget($site), $title, $html, $status, $this->buildPostData($title, $html, $status, $options));

        if (!$result['success']) {
            return $this->failure($result['message'] ?? 'WP Toolkit publish failed.', $transportMode);
        }

        $postId = $result['data']['post_id'] ?? null;

        $linksPayload = null;
        if ($postId) {
            $pressReleaseSync = $this->syncHexaPressReleaseLinks(
                $site,
                $resolved['server'],
                (int) $postId,
                $publicationTaxonomy,
                $options,
                $result['data']['post_url'] ?? null
            );
            if (!empty($pressReleaseSync['warning'])) {
                $result['message'] = trim(($result['message'] ?? 'Post created.') . ' Warning: ' . $pressReleaseSync['warning']);
            }
            $linksPayload = $pressReleaseSync['links'] ?? null;
            if ($isRequiredPressReleaseSyndication) {
                $assignedPublicationTerms = array_values(array_unique(array_filter(array_map("intval", (array) ($linksPayload["publication_term_ids"] ?? [])))));
                sort($expectedPublicationTerms);
                sort($assignedPublicationTerms);
                if (!empty($pressReleaseSync["warning"]) || $assignedPublicationTerms !== $expectedPublicationTerms) {
                    $this->wordpress()->updatePost($this->wpTarget($site), (int) $postId, ["status" => "draft"]);
                    return $this->failure(!empty($pressReleaseSync["warning"]) ? (string) $pressReleaseSync["warning"] : "WordPress did not retain all selected publication syndication sources for this press release.", $transportMode, [
                        "post_id" => (int) $postId,
                        "post_url" => $result["data"]["post_url"] ?? null,
                        "post_status" => "draft",
                        "expected_publication_term_ids" => $expectedPublicationTerms,
                        "assigned_publication_term_ids" => $assignedPublicationTerms,
                    ]);
                }
            }
        }

        return $this->success(
            $site,
            $transportMode,
            $result['message'] ?? ('Published via WP Toolkit (' . $transportMode . ').'),
            $postId,
            $result['data']['post_url'] ?? null,
            $result['data']['post_status'] ?? $status,
            $result['data']['post_date'] ?? $date,
            [
                'links_injected' => $linksPayload,
            ]
        );
    }

    private function updateViaSsh(PublishSite $site, int $postId, string $title, string $html, string $status, array $options): array
    {
        $resolved = $this->resolveServer($site);
        if (!$resolved['server'] || !$site->wordpress_install_id) {
            return $this->failure("Site '{$site->name}' is missing WP Toolkit configuration.", self::MODE_WPTOOLKIT);
        }
        $transportMode = $this->wordpress()->connectionMode($this->wpTarget($site));
        $publicationTaxonomy = (string) ($options['publication_taxonomy'] ?? 'publication');
        $isRequiredPressReleaseSyndication = $site->is_press_release_source && trim((string) ($options["article_type"] ?? "")) === "press-release";
        $expectedPublicationTerms = array_values(array_unique(array_filter(array_map("intval", (array) ($options["publication_term_ids"] ?? [])))));
        if ($isRequiredPressReleaseSyndication && $expectedPublicationTerms === []) {
            return $this->failure("Select at least one publication syndication source before preparing or publishing this press release.", $transportMode, [
                "expected_publication_term_ids" => [],
            ]);
        }

        $postData = $this->buildPostData($title, $html, $status, $options);
        $result = $this->wordpress()->updatePost($this->wpTarget($site), $postId, $this->buildPostData($title, $html, $status, $options));

        if (!$result['success']) {
            return $this->failure($result['message'] ?? 'WP Toolkit update failed.', $transportMode);
        }

        $linksPayload = null;
        $pressReleaseSync = $this->syncHexaPressReleaseLinks(
            $site,
            $resolved['server'],
            $postId,
            $publicationTaxonomy,
            $options,
            $result['data']['post_url'] ?? null
        );
        if (!empty($pressReleaseSync['warning'])) {
            $result['message'] = trim(($result['message'] ?? 'Post updated.') . ' Warning: ' . $pressReleaseSync['warning']);
        }
        $linksPayload = $pressReleaseSync['links'] ?? null;
        if ($isRequiredPressReleaseSyndication) {
            $assignedPublicationTerms = array_values(array_unique(array_filter(array_map("intval", (array) ($linksPayload["publication_term_ids"] ?? [])))));
            sort($expectedPublicationTerms);
            sort($assignedPublicationTerms);
            if (!empty($pressReleaseSync["warning"]) || $assignedPublicationTerms !== $expectedPublicationTerms) {
                $this->wordpress()->updatePost($this->wpTarget($site), (int) $postId, ["status" => "draft"]);
                return $this->failure(!empty($pressReleaseSync["warning"]) ? (string) $pressReleaseSync["warning"] : "WordPress did not retain all selected publication syndication sources for this press release.", $transportMode, [
                    "post_id" => (int) $postId,
                    "post_url" => $result["data"]["post_url"] ?? null,
                    "post_status" => "draft",
                    "expected_publication_term_ids" => $expectedPublicationTerms,
                    "assigned_publication_term_ids" => $assignedPublicationTerms,
                ]);
            }
        }

        return $this->success(
            $site,
            $transportMode,
            $result['message'] ?? ('Updated via WP Toolkit (' . $transportMode . ').'),
            $result['data']['post_id'] ?? $postId,
            $result['data']['post_url'] ?? null,
            $result['data']['post_status'] ?? $status,
            $result['data']['post_date'] ?? null,
            [
                'links_injected' => $linksPayload,
            ]
        );
    }

    private function inspectViaSsh(PublishSite $site, int $postId): array
    {
        $resolved = $this->resolveServer($site);
        if (!$resolved['server'] || !$site->wordpress_install_id) {
            return $this->failure("Site '{$site->name}' is missing WP Toolkit configuration.", self::MODE_WPTOOLKIT);
        }
        $transportMode = $this->wordpress()->connectionMode($this->wpTarget($site));
        $result = $this->wordpress()->getPost($this->wpTarget($site), $postId);
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
        $result = $this->wordpress()->createPost($this->wpTarget($site), $title, $html, $status, $postData);

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
        $result = $this->wordpress()->updatePost($this->wpTarget($site), $postId, $postData);

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

        $result = $this->wordpress()->getPost($this->wpTarget($site), $postId);
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

    private function normalizeSyndicationOptions(PublishSite $site, array $options): array
    {
        $articleType = trim((string) ($options['article_type'] ?? ''));
        $hasPublicationTerms = !empty($options['publication_term_ids']);
        if (!$site->is_press_release_source || ($articleType !== 'press-release' && !$hasPublicationTerms)) {
            unset($options['publication_term_ids'], $options['publication_taxonomy']);
            return $options;
        }

        $options['publication_term_ids'] = array_values(array_unique(array_filter(array_map('intval', (array) ($options['publication_term_ids'] ?? [])))));
        $options['publication_taxonomy'] = 'publication';

        return $options;
    }

    private function resolveSyndicationTaxonomy(PublishSite $site): array
    {
        $fallback = [
            'taxonomy' => 'publication',
            'label' => 'Publications',
            'hierarchical' => true,
        ];

        if (!$this->usesWpToolkit($site)) {
            return $fallback;
        }

        $resolved = $this->resolveServer($site);
        if (!$resolved['server'] || !$site->wordpress_install_id) {
            return $fallback;
        }

        $result = $this->wordpress()->resolvePreferredTaxonomy($this->wpTarget($site), ["publication", "category"]);

        if (!($result['success'] ?? false)) {
            return $fallback;
        }

        return [
            'taxonomy' => (string) ($result['taxonomy'] ?? 'publication'),
            'label' => (string) ($result['label'] ?? 'Publications'),
            'hierarchical' => (bool) ($result['hierarchical'] ?? true),
        ];
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

        $publicationTaxonomy = (string) ($options['publication_taxonomy'] ?? 'publication');
        if (!empty($options['publication_term_ids']) && $publicationTaxonomy !== 'category') {
            $postData[$publicationTaxonomy] = array_values($options['publication_term_ids']);
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

    private function failure(string $message, string $mode, array $extra = []): array
    {
        return array_merge([
            'success' => false,
            'message' => $message,
            'post_id' => null,
            'post_url' => null,
            'post_status' => null,
            'post_date' => null,
            'mode' => $mode,
        ], $extra);
    }

    private function success(PublishSite $site, string $mode, string $message, ?int $postId, ?string $postUrl = null, ?string $postStatus = null, ?string $postDate = null, array $extra = []): array
    {
        $postUrl = $this->normalizePostUrl($postUrl)
            ?: ($postId ? rtrim($site->url, '/') . '/?p=' . $postId : null);

        return array_merge([
            'success' => true,
            'message' => $message,
            'post_id' => $postId,
            'post_url' => $postUrl,
            'post_status' => $postStatus,
            'post_date' => $postDate,
            'mode' => $mode,
        ], $extra);
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

    private function syncHexaPressReleaseLinks(PublishSite $site, ?WhmServer $server, int $postId, string $publicationTaxonomy, array $options, ?string $postUrl = null): array
    {
        $termIds = array_values(array_unique(array_filter(array_map('intval', (array) ($options['publication_term_ids'] ?? [])))));
        $isPressRelease = trim((string) ($options['article_type'] ?? '')) === 'press-release';

        if (
            !$server
            || !$site->wordpress_install_id
            || !$site->is_press_release_source
            || (!$isPressRelease && empty($termIds))
            || $publicationTaxonomy === 'category'
            || $postId <= 0
        ) {
            return ['links' => null, 'warning' => null];
        }
        $postUrl = $this->normalizePostUrl($postUrl);

        $php = '$postId=' . (int) $postId . ';'
            . '$taxonomy=' . var_export($publicationTaxonomy, true) . ';'
            . '$providedTermIds=' . var_export($termIds, true) . ';'
            . '$forcedPermalink=' . var_export((string) ($postUrl ?? ''), true) . ';'
            . '$fieldRefs=["link_output"=>"field_64a72abb02037","link_output_html"=>"field_652cb84e99150","link_output_standard"=>"field_64a004af275a4","non_featured_standard_urls"=>"field_65100cf3201c7"];'
            . '$emit=function(array $payload){echo "HEXA_PR_LINKS:".wp_json_encode($payload);};'
            . '$title=html_entity_decode((string) get_the_title($postId), ENT_QUOTES, "UTF-8");'
            . '$slug=(string) get_post_field("post_name",$postId);'
            . '$permalink=trim($forcedPermalink)!=="" ? trim($forcedPermalink) : (string) get_permalink($postId);'
            . 'if ($slug === "") { $emit(["success"=>false,"message"=>"Post slug missing.","links"=>null]); return; }'
            . 'global $wpdb;'
            . '$taxonomyExists = taxonomy_exists($taxonomy);'
            . 'if (empty($providedTermIds)) {'
            . '  if ($taxonomyExists) { $providedTermIds = get_terms(["taxonomy"=>$taxonomy,"hide_empty"=>false,"fields"=>"ids"]); if (is_wp_error($providedTermIds)) { $providedTermIds = []; } }'
            . '  else {'
            . '    $providedTermIds = $wpdb->get_col($wpdb->prepare("SELECT tt.term_id FROM {$wpdb->term_relationships} tr INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id WHERE tr.object_id = %d AND tt.taxonomy = %s", $postId, $taxonomy));'
            . '  }'
            . '}'
            . '$providedTermIds = array_values(array_unique(array_map("intval", array_filter((array) $providedTermIds))));'
            . 'if ($taxonomyExists && !empty($providedTermIds)) { $assigned = wp_set_object_terms($postId, $providedTermIds, $taxonomy, false); if (is_wp_error($assigned)) { $emit(["success"=>false,"message"=>$assigned->get_error_message(),"links"=>null]); return; } }'
            . '$termIdsForLinks = $providedTermIds;'
            . 'if ($taxonomyExists) { $resolvedIds = wp_get_object_terms($postId, $taxonomy, ["fields" => "ids"]); if (!is_wp_error($resolvedIds) && !empty($resolvedIds)) { $termIdsForLinks = array_values(array_map("intval", (array) $resolvedIds)); } }'
            . 'elseif (empty($termIdsForLinks)) { $termIdsForLinks = array_values(array_map("intval", (array) $wpdb->get_col($wpdb->prepare("SELECT tt.term_id FROM {$wpdb->term_relationships} tr INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id WHERE tr.object_id = %d AND tt.taxonomy = %s", $postId, $taxonomy)))); }'
            . '$terms = [];'
            . 'foreach ($termIdsForLinks as $termId) {'
            . '  $row = $wpdb->get_row($wpdb->prepare("SELECT t.term_id, t.name FROM {$wpdb->terms} t INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_id = t.term_id WHERE t.term_id = %d AND tt.taxonomy = %s LIMIT 1", (int) $termId, $taxonomy));'
            . '  if ($row) { $terms[] = $row; }'
            . '}'
            . 'usort($terms, static function($a,$b){ return strcasecmp((string) ($a->name ?? ""), (string) ($b->name ?? "")); });'
            . '$sourceRows=[]; $newSourceRows=[];'
            . 'foreach ($terms as $term) {'
            . '  $publicationPostId = (int) $wpdb->get_var($wpdb->prepare("SELECT meta_value FROM {$wpdb->termmeta} WHERE term_id = %d AND meta_key = %s LIMIT 1", (int) $term->term_id, "publication"));'
            . '  $prefix = trim((string) get_post_meta($publicationPostId, "url_press_release_prefix", true));'
            . '  $baseUrl = trim((string) get_post_meta($publicationPostId, "url", true));'
            . '  if ($prefix === "" && $baseUrl !== "") { $prefix = rtrim($baseUrl, "/") . "/press-release/"; }'
            . '  if ($prefix === "") { continue; }'
            . '  $url = rtrim($prefix, "/") . "/" . $slug;'
            . '  $row = ["term_id" => (int) $term->term_id, "name" => (string) $term->name, "url" => $url, "publication_post_id" => $publicationPostId, "new_source" => (bool) get_post_meta($publicationPostId, "new_source", true)];'
            . '  if ($row["new_source"]) { $newSourceRows[] = $row; } else { $sourceRows[] = $row; }'
            . '}'
            . '$plainLines = static function(array $rows){ $lines=[]; foreach ($rows as $row) { $lines[] = $row["name"] . ": " . $row["url"]; } return implode(" <br /><br />", $lines); };'
            . '$htmlLines = static function(array $rows){ $lines=[]; foreach ($rows as $row) { $url = esc_url($row["url"]); $label = esc_html($row["name"]); $lines[] = $label . ": <a href=\"" . $url . "\" target=\"_blank\" title=\"" . esc_attr($row["url"]) . "\">" . esc_html($row["url"]) . "</a>"; } return implode(" <br /><br />", $lines); };'
            . '$pressReleaseLinePlain = "PRESS RELEASE: " . $permalink;'
            . '$pressReleaseLineHtml = "PRESS RELEASE: <a href=\"" . esc_url($permalink) . "\" target=\"_blank\" title=\"" . esc_attr($permalink) . "\">" . esc_html($permalink) . "</a>";'
            . '$plain = $title . "\\n<br /><br />" . $pressReleaseLinePlain . " <br /><br />NOTE: Please allow 1 hour for links to go live";'
            . '$html = esc_html($title) . "\\n<br /><br />" . $pressReleaseLineHtml . " <br /><br />NOTE: Please allow 1 hour for links to go live";'
            . '$standardPlain = $plainLines($sourceRows);'
            . '$standardHtml = $htmlLines($sourceRows);'
            . '$newPlain = $plainLines($newSourceRows);'
            . '$newHtml = $htmlLines($newSourceRows);'
            . 'if ($standardPlain !== "") { $plain .= "<br /><br /><br /><br />SOURCES---<br /><br />" . $standardPlain; $html .= "<br /><br /><br /><br />SOURCES---<br /><br />" . $standardHtml; }'
            . 'if ($newPlain !== "") { $plain .= "<br /><br /><br /><br />NEW SOURCES---<br /><br />" . $newPlain; $html .= "<br /><br /><br /><br />NEW SOURCES---<br /><br />" . $newHtml; }'
            . '$nonFeaturedHtml = trim($standardHtml . ($standardHtml !== "" && $newHtml !== "" ? " <br /><br />" : "") . $newHtml);'
            . 'update_post_meta($postId, "link_output", $plain);'
            . 'update_post_meta($postId, "link_output_html", $html);'
            . 'update_post_meta($postId, "link_output_standard", $html);'
            . 'update_post_meta($postId, "non_featured_standard_urls", $nonFeaturedHtml);'
            . 'foreach ($fieldRefs as $metaKey => $fieldKey) { update_post_meta($postId, "_" . $metaKey, $fieldKey); }'
            . '$links = ['
            . '  "type" => "press_release_links",'
            . '  "title" => $title,'
            . '  "permalink" => $permalink,'
            . '  "link_output" => $plain,'
            . '  "link_output_html" => $html,'
            . '  "link_output_standard" => $html,'
            . '  "non_featured_standard_urls" => $nonFeaturedHtml,'
            . '  "plain" => $plain,'
            . '  "html" => $html,'
            . '  "standard_sources" => $sourceRows,'
            . '  "new_sources" => $newSourceRows,'
            . '  "publication_term_ids" => $termIdsForLinks,'
            . '];'
            . '$emit(["success"=>true,"message"=>"Press release links synced.","links"=>$links]);';

        $result = $this->wordpress()->evaluatePhp($this->wpTarget($site), $php);
        if (!($result['success'] ?? false)) {
            return [
                'links' => null,
                'warning' => $result['message'] ?? 'Failed to sync press release links.',
            ];
        }

        foreach (explode("\n", (string) ($result['stdout'] ?? '')) as $line) {
            $line = trim($line);
            if (!str_contains($line, 'HEXA_PR_LINKS:')) {
                continue;
            }
            $json = substr($line, strpos($line, 'HEXA_PR_LINKS:') + 14);
            $payload = json_decode(trim($json), true);
            if (!is_array($payload)) {
                continue;
            }

            return [
                'links' => is_array($payload['links'] ?? null) ? $payload['links'] : null,
                'warning' => !($payload['success'] ?? false) ? (string) ($payload['message'] ?? 'Failed to sync press release links.') : null,
            ];
        }

        return [
            'links' => null,
            'warning' => 'Failed to parse synced press release links output.',
        ];
    }

    private function wordpress(): \hexa_package_wordpress\Services\WordPressManagerService
    {
        return app(\hexa_package_wordpress\Services\WordPressManagerService::class);
    }

    /**
     * @return array<string, mixed>
     */
    private function wpTarget(PublishSite $site): array
    {
        return app(\hexa_app_publish\Publishing\Sites\Services\PublishSiteWordPressTargetFactory::class)->fromSite($site);
    }
}
