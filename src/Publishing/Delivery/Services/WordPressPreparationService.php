<?php

namespace hexa_app_publish\Publishing\Delivery\Services;

use hexa_app_publish\Discovery\Links\Health\Services\LinkHealthService;
use hexa_app_publish\Publishing\Sites\Models\PublishSite;
use hexa_package_whm\Models\HostingAccount;
use hexa_package_whm\Models\WhmServer;
use hexa_package_wordpress\Services\WordPressService;
use hexa_package_wptoolkit\Services\WpToolkitService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * WordPressPreparationService — handles pre-publish WordPress operations.
 *
 * Uploads images, creates categories/tags, replaces URLs in HTML.
 * Supports both SSH (WP Toolkit) and REST API.
 * Accepts a progress callback for SSE streaming.
 */
class WordPressPreparationService
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
     * Prepare content for WordPress: upload images, create categories/tags, validate HTML.
     *
     * @param PublishSite $site
     * @param string $html Article HTML
     * @param array $options {
     *     @type string $title          Article title for filename generation
     *     @type array  $categories     Category names to create/resolve
     *     @type array  $tags           Tag names to create/resolve
     *     @type array  $photo_suggestions  Photo metadata from AI spin
     *     @type int    $draft_id       Draft ID for filename prefix
     * }
     * @param callable|null $onProgress fn(string $type, string $message, array $extra = [])
     * @return array{success: bool, html: string, category_ids: array, tag_ids: array, wp_images: array}
     */
    public function prepare(PublishSite $site, string $html, array $options = [], ?callable $onProgress = null): array
    {
        $send = $onProgress ?? function () {};
        $mode = $site->connection_type === 'wptoolkit' ? 'wptoolkit' : 'rest';
        $connectionMode = $mode === 'wptoolkit' ? 'wptoolkit' : 'rest';
        $connectionLabel = $mode === 'wptoolkit' ? 'WP Toolkit' : 'REST API';
        $existingUploads = $this->buildExistingUploadMap($options['existing_uploads'] ?? []);

        $this->emitProgress($send, 'info', "Connecting to {$site->name} via {$connectionLabel}...", 'connection', 'start', [
            'site_name' => $site->name,
            'connection_mode' => $connectionMode,
            'connection_label' => $connectionLabel,
        ]);

        // Validate credentials
        if ($mode === 'rest' && (!$site->wp_username || !$site->wp_application_password)) {
            $this->emitProgress($send, 'error', "Site '{$site->name}' has no WordPress credentials configured.", 'connection', 'credentials');
            return ['success' => false, 'html' => $html, 'category_ids' => [], 'tag_ids' => [], 'wp_images' => []];
        }

        $server = null;
        $installId = null;
        if ($mode === 'wptoolkit') {
            $resolved = $this->resolveServer($site);
            $server = $resolved['server'];
            $installId = $site->wordpress_install_id;
            if (!$server || !$installId) {
                $this->emitProgress($send, 'error', "Missing WP Toolkit server or install ID.", 'connection', 'resolve_server');
                return ['success' => false, 'html' => $html, 'category_ids' => [], 'tag_ids' => [], 'wp_images' => []];
            }
            $connectionMode = $this->wptoolkit->connectionMode($server);
            $connectionLabel = $this->wptoolkit->connectionLabel($server);
            $this->emitProgress($send, 'success', "{$connectionLabel} server resolved: {$server->hostname}", 'connection', 'resolve_server', [
                'hostname' => $server->hostname,
                'install_id' => $installId,
                'connection_mode' => $connectionMode,
                'connection_label' => $connectionLabel,
            ]);
        } else {
            $this->emitProgress($send, 'success', "REST API credentials verified for {$site->wp_username}", 'connection', 'credentials', [
                'username' => $site->wp_username,
            ]);
        }

        // Step 0b: Clean HTML first (no SSH needed, instant)
        $this->emitProgress($send, 'info', "Cleaning HTML...", 'html', 'sanitize');
        $html = $this->sanitizeHtml($html);
        $this->emitProgress($send, 'success', "HTML cleaned for WordPress", 'html', 'sanitize');

        // Step 0a: Author + SSH warmup (with timeout protection)
        $author = $site->default_author;
        if ($mode === 'wptoolkit' && $server && $installId) {
            $connectMessage = $connectionMode === 'local'
                ? "Launching local WP Toolkit on {$server->hostname}..."
                : "Connecting SSH to {$server->hostname}...";
            $connectSubstage = $connectionMode === 'local' ? 'local_bootstrap' : 'ssh_connect';
            $this->emitProgress($send, 'info', $connectMessage, 'connection', $connectSubstage, [
                'hostname' => $server->hostname,
                'connection_mode' => $connectionMode,
                'connection_label' => $connectionLabel,
            ]);
            $sshError = null;
            try {
                $ssh = $this->wptoolkit->getConnection($server);
            } catch (\Throwable $e) {
                $sshError = $e->getMessage();
                $ssh = ['success' => false, 'error' => $sshError];
            }
            if ($ssh['success']) {
                $connectedMessage = $connectionMode === 'local'
                    ? "Local WP Toolkit ready on {$server->hostname}"
                    : "SSH connected to {$server->hostname}";
                $this->emitProgress($send, 'success', $connectedMessage, 'connection', $connectSubstage, [
                    'hostname' => $server->hostname,
                    'connection_mode' => $connectionMode,
                    'connection_label' => $connectionLabel,
                ]);
            } else {
                $this->emitProgress($send, 'error', "WP Toolkit connection failed: " . ($ssh['error'] ?? $sshError ?? 'unknown'), 'connection', $connectSubstage, [
                    'hostname' => $server->hostname,
                    'connection_mode' => $connectionMode,
                    'connection_label' => $connectionLabel,
                ]);
                return ['success' => false, 'html' => $html, 'category_ids' => [], 'tag_ids' => [], 'wp_images' => [], 'message' => 'WP Toolkit connection failed'];
            }
            if ($author) {
                $this->emitProgress($send, 'success', "Author: {$author} (will be set during publish)", 'connection', 'author', [
                    'author' => $author,
                ]);
            } else {
                $this->emitProgress($send, 'warning', "No default author — post will use WP default author.", 'connection', 'author');
            }
        } else {
            if ($author) {
                $this->emitProgress($send, 'success', "Author: {$author}", 'connection', 'author', [
                    'author' => $author,
                ]);
            } else {
                $this->emitProgress($send, 'warning', "No default author configured for {$site->name} — post will use WP default author.", 'connection', 'author');
            }
        }

        // Step 1: Upload images
        [$html, $wpImages] = $this->uploadImages($site, $html, $mode, $server, $installId, $options, $send);

        // Step 1b: Upload featured image
        $featuredMediaId = null;
        $featuredWpUrl = null;
        $featuredMeta = $options['featured_meta'] ?? null;
        if ($featuredMeta) {
            $featuredUrl = $this->normalizeMediaSourceUrl((string) ($options['featured_url'] ?? ''));
            if ($featuredUrl) {
                // Check if featured image was already uploaded in a prior prepare
                $existingFeatured = $existingUploads[$featuredUrl] ?? null;
                $existingFeaturedId = $options['existing_featured_media_id'] ?? null;
                if ($existingFeatured && !empty($existingFeatured['media_id'])) {
                    $featuredMediaId = $existingFeatured['media_id'];
                    $featuredWpUrl = $existingFeatured['media_url'] ?? null;
                    $wpImages[] = array_merge($existingFeatured, ['is_featured' => true]);
                    $this->emitProgress($send, 'success', "Featured: Already on WordPress (media_id: {$featuredMediaId})\nWP: " . Str::limit($featuredWpUrl ?? '', 100), 'media', 'featured_duplicate', [
                        'media_id' => $featuredMediaId,
                        'wp_url' => $featuredWpUrl,
                    ]);
                } elseif ($existingFeaturedId) {
                    // Featured was uploaded in a previous prepare (media ID persisted in state)
                    $featuredMediaId = (int) $existingFeaturedId;
                    $this->emitProgress($send, 'success', "Featured: Already on WordPress (media_id: {$featuredMediaId})", 'media', 'featured_duplicate', [
                        'media_id' => $featuredMediaId,
                    ]);
                } else {
                $fDomain = parse_url($featuredUrl, PHP_URL_HOST) ?: 'unknown';
                $fSource = match(true) {
                    str_contains($fDomain, 'pexels') => 'Pexels',
                    str_contains($fDomain, 'unsplash') => 'Unsplash',
                    str_contains($fDomain, 'pixabay') => 'Pixabay',
                    str_contains($fDomain, 'cdn.') => 'CDN/External',
                    default => 'External URL',
                };
                $this->emitProgress($send, 'info', "Uploading featured image — Source: {$fSource} ({$fDomain})", 'media', 'featured_start', [
                    'source_type' => $fSource,
                    'source_domain' => $fDomain,
                ]);
                $this->emitProgress($send, 'info', "  URL: " . Str::limit($featuredUrl, 120), 'media', 'featured_source', [
                    'source_url' => $featuredUrl,
                ]);
                $fFilename = $featuredMeta['filename'] ?? 'featured';
                $ext = pathinfo(parse_url($featuredUrl, PHP_URL_PATH), PATHINFO_EXTENSION) ?: 'jpg';
                $fFilename = Str::endsWith($fFilename, '.' . $ext) ? $fFilename : $fFilename . '.' . $ext;

                $uploadResult = $this->uploadWithRetry(
                    $site, $mode, $server, $installId,
                    $featuredUrl, $fFilename,
                    $featuredMeta['alt_text'] ?? '', $featuredMeta['caption'] ?? '', $send
                );

                if ($uploadResult['success'] && !empty($uploadResult['data']['media_id'])) {
                    $featuredMediaId = $uploadResult['data']['media_id'];
                    $featuredWpUrl = $uploadResult['data']['media_url'] ?? null;
                    $wpImages[] = array_merge($uploadResult['data'], ['is_featured' => true, 'source_url' => $featuredUrl, 'filename' => $fFilename]);
                    if ($mode === 'rest') {
                        $this->setHexaMeta($site, (int) $featuredMediaId, $options['draft_id'] ?? 0);
                    }
                    $this->emitProgress($send, 'success', "Featured image uploaded: media_id={$featuredMediaId}", 'media', 'featured_uploaded', [
                        'media_id' => $featuredMediaId,
                        'wp_url' => $featuredWpUrl,
                        'wp_image' => $uploadResult['data'],
                    ]);
                } else {
                    $this->emitProgress($send, 'error', "Featured image upload failed: " . ($uploadResult['message'] ?? 'unknown'), 'media', 'featured_failed');
                }
                } // end else (not duplicate)
            } else {
                $this->emitProgress($send, 'warning', "No featured image URL provided", 'media', 'featured_missing');
            }
        }

        // Step 2: Create categories
        $categoryIds = $this->createCategories($site, $mode, $server, $installId, $options['categories'] ?? [], $send);

        // Step 3: Create tags
        $tagIds = $this->createTags($site, $mode, $server, $installId, $options['tags'] ?? [], $send);

        $this->emitProgress($send, 'info', 'Checking article links for 404s...', 'integrity', 'links_start');
        $linkHealth = app(LinkHealthService::class)->sanitizeHtmlAnchors($html, 'prepare');
        $html = $linkHealth['html'];

        if (($linkHealth['updated'] ?? 0) > 0) {
            $this->emitProgress($send, 'success', 'Canonicalized ' . $linkHealth['updated'] . ' redirected link(s).', 'integrity', 'links_canonicalized', [
                'updated_count' => $linkHealth['updated'],
            ]);
        }

        if (($linkHealth['removed'] ?? 0) > 0) {
            $this->emitProgress($send, 'warning', 'Removed ' . $linkHealth['removed'] . ' broken link(s) before publish.', 'integrity', 'links_removed', [
                'removed_count' => $linkHealth['removed'],
            ]);
        }

        if (($linkHealth['failed'] ?? 0) > 0) {
            $this->emitProgress($send, 'warning', 'Skipped ' . $linkHealth['failed'] . ' link probe(s) because the remote check failed.', 'integrity', 'links_probe_failed', [
                'failed_count' => $linkHealth['failed'],
            ]);
        }

        // Step 4: Integrity check — validate HTML structure, photos, and cleanup
        $this->emitProgress($send, 'info', "Running integrity check...", 'integrity', 'start');
        $integrityIssues = [];

        // Check for leftover photo placeholders
        if (preg_match('/<div[^>]*photo-placeholder/i', $html)) {
            $integrityIssues[] = 'Unprocessed photo placeholder div found';
            $html = preg_replace('/<div[^>]*class="[^"]*photo-placeholder[^"]*"[^>]*>.*?<\/div>/is', '', $html);
        }
        if (preg_match('/Loading photo/i', $html)) {
            $integrityIssues[] = '"Loading photo..." text found in content';
            $html = preg_replace('/<span[^>]*>Loading photo[^<]*<\/span>/i', '', $html);
            $html = preg_replace('/<p[^>]*>\s*<span[^>]*>Loading photo[^<]*<\/span>\s*<\/p>/i', '', $html);
        }

        // Check for broken img tags (no src or empty src)
        if (preg_match('/<img[^>]*src\s*=\s*["\']["\'][^>]*>/i', $html)) {
            $integrityIssues[] = 'Image with empty src found';
            $html = preg_replace('/<img[^>]*src\s*=\s*["\']["\'][^>]*>/i', '', $html);
        }

        // Check for leftover editor artifacts
        if (preg_match('/contenteditable/i', $html)) {
            $integrityIssues[] = 'contenteditable attribute found';
            $html = preg_replace('/\s+contenteditable="[^"]*"/i', '', $html);
        }
        if (preg_match('/x-data|x-show|x-cloak|x-model|@click/i', $html)) {
            $integrityIssues[] = 'Alpine.js directives found in content';
            $html = preg_replace('/\s+(?:x-[\w.-]+|@[\w.-]+(?:\.\w+)*|:[\w-]+)\s*=\s*"[^"]*"/i', '', $html);
            $html = preg_replace('/\s+x-cloak/i', '', $html);
        }

        if (($linkHealth['removed'] ?? 0) > 0) {
            $integrityIssues[] = $linkHealth['removed'] . ' broken link(s) removed before publish';
        }
        if (($linkHealth['failed'] ?? 0) > 0) {
            $integrityIssues[] = $linkHealth['failed'] . ' link probe(s) could not be verified';
        }

        // Check for broken/unclosed tags
        $openTags = preg_match_all('/<(p|div|figure|figcaption|h[1-6]|ul|ol|li|blockquote|table|tr|td|th|thead|tbody)\b[^>]*>/i', $html);
        $closeTags = preg_match_all('/<\/(p|div|figure|figcaption|h[1-6]|ul|ol|li|blockquote|table|tr|td|th|thead|tbody)>/i', $html);
        if ($openTags !== $closeTags) {
            $integrityIssues[] = "Tag imbalance: {$openTags} opening vs {$closeTags} closing tags";
        }

        // Check all images have alt text
        preg_match_all('/<img[^>]+>/i', $html, $allImgs);
        $noAlt = 0;
        foreach ($allImgs[0] ?? [] as $imgTag) {
            if (!preg_match('/alt\s*=\s*"[^"]+"/i', $imgTag)) $noAlt++;
        }
        if ($noAlt > 0) {
            $integrityIssues[] = "{$noAlt} image(s) missing alt text";
        }

        // Clean up empty paragraphs
        $html = preg_replace('/<p[^>]*>\s*<\/p>/i', '', $html);

        // Report
        $htmlValid = !empty(trim(strip_tags($html)));
        if (empty($integrityIssues)) {
            $this->emitProgress($send, 'success', "Integrity check passed — HTML clean, all photos processed", 'integrity', 'complete');
        } else {
            foreach ($integrityIssues as $issue) {
                $this->emitProgress($send, 'warning', "Integrity: {$issue} (auto-fixed)", 'integrity', 'issue');
            }
            $this->emitProgress($send, $htmlValid ? 'success' : 'error', 'Integrity check complete — ' . count($integrityIssues) . ' issue(s) auto-fixed', 'integrity', 'complete', [
                'issue_count' => count($integrityIssues),
            ]);
        }

        return [
            'success'           => $htmlValid,
            'integrity_issues'  => $integrityIssues,
            'html'              => $html,
            'category_ids'      => $categoryIds,
            'tag_ids'           => $tagIds,
            'wp_images'         => $wpImages,
            'featured_media_id' => $featuredMediaId,
            'featured_wp_url'   => $featuredWpUrl,
        ];
    }

    /**
     * Upload images from HTML to WordPress, replace URLs.
     *
     * @param PublishSite $site
     * @param string $html
     * @param string $mode
     * @param WhmServer|null $server
     * @param string|null $installId
     * @param array $options
     * @param callable $send
     * @return array [html, wpImages]
     */
    private function uploadImages(PublishSite $site, string $html, string $mode, ?WhmServer $server, ?string $installId, array $options, callable $send): array
    {
        preg_match_all('/<img[^>]+src\s*=\s*["\']([^"\']+)["\'][^>]*>/i', $html, $imgMatches);
        $imageUrls = array_unique($imgMatches[1] ?? []);
        $imageMap = [];
        $wpImages = [];
        $imgIndex = 0;

        if (empty($imageUrls)) {
            $this->emitProgress($send, 'step', "No images to upload", 'media', 'inline_none');
            return [$html, $wpImages];
        }

        $this->emitProgress($send, 'info', "Uploading " . count($imageUrls) . " image(s)...", 'media', 'inline_start', [
            'image_count' => count($imageUrls),
        ]);
        $articleTitle = $options['title'] ?? 'article';
        $draftId = $options['draft_id'] ?? 0;

        // Build photo metadata lookup from photo_suggestions (legacy) + photo_meta (new)
        $photoMeta = [];
        foreach ($options['photo_suggestions'] ?? [] as $ps) {
            $photoUrl = $this->normalizeMediaSourceUrl((string) ($ps['autoPhoto']['url_large'] ?? ''));
            if ($photoUrl !== '') {
                $photoMeta[$photoUrl] = $ps;
            }
        }
        // Indexed photo_meta from pipeline (ordered, matched by position)
        $photoMetaIndexed = $options['photo_meta'] ?? [];

        // Build duplicate detection map — key by ALL known URLs for each photo
        // After first prepare, HTML has WP URLs (sized), so we match source, media, inline, and all sizes
        $existingUploads = $this->buildExistingUploadMap($options['existing_uploads'] ?? []);

        foreach ($imageUrls as $rawImgUrl) {
            $imgUrl = $this->normalizeMediaSourceUrl($rawImgUrl);
            if ($imgUrl === '') {
                $imgUrl = $rawImgUrl;
            }

            if (str_starts_with($imgUrl, rtrim($site->url, '/'))) {
                $imgIndex++;
                $existingMedia = $existingUploads[$imgUrl] ?? null;
                $mediaId = $existingMedia['media_id'] ?? '?';
                $this->emitProgress($send, 'success', "Uploading photo {$imgIndex}/" . count($imageUrls) . ": Already on WordPress (media_id: {$mediaId})\nWP: " . Str::limit($imgUrl, 100), 'media', 'inline_duplicate', [
                    'photo_index' => $imgIndex,
                    'photo_total' => count($imageUrls),
                    'source_url' => $imgUrl,
                    'media_id' => $mediaId,
                ]);
                continue;
            }

            // Duplicate detection: skip if this URL was already uploaded in a prior prepare
            if (isset($existingUploads[$imgUrl])) {
                $existing = $existingUploads[$imgUrl];
                $imageMap[$rawImgUrl] = $existing['inline_url'] ?? $existing['media_url'];
                $wpImages[] = array_merge($existing, ['source_url' => $existing['source_url'] ?? $imgUrl, 'skipped_duplicate' => true]);
                $imgIndex++;
                $this->emitProgress($send, 'success', "Skipped — already on WordPress (media_id: " . ($existing['media_id'] ?? '?') . ")\nWP: " . Str::limit($existing['inline_url'] ?? $existing['media_url'] ?? '', 100), 'media', 'inline_duplicate', [
                    'photo_index' => $imgIndex,
                    'photo_total' => count($imageUrls),
                    'media_id' => $existing['media_id'] ?? null,
                    'source_url' => $imgUrl,
                ]);
                continue;
            }

            $ps = $photoMeta[$imgUrl] ?? null;
            $pm = $photoMetaIndexed[$imgIndex] ?? null;
            $altText = $pm['alt_text'] ?? $ps['alt_text'] ?? '';
            $caption = $pm['caption'] ?? $ps['caption'] ?? '';
            $seoName = $pm['filename'] ?? $ps['suggestedFilename'] ?? '';

            if (!$altText && preg_match('/<img[^>]+src\s*=\s*["\']' . preg_quote($rawImgUrl, '/') . '["\'][^>]*alt\s*=\s*["\']([^"\']*)["\'][^>]*>/i', $html, $altMatch)) {
                $altText = $altMatch[1];
            }

            $slugBase = $seoName ?: Str::limit(Str::slug($altText ?: $articleTitle, '-'), 60, '');
            $ext = pathinfo(parse_url($imgUrl, PHP_URL_PATH), PATHINFO_EXTENSION) ?: 'jpg';
            $filenamePattern = \hexa_core\Models\Setting::getValue('wp_photo_filename_pattern', 'hexa_{draft_id}_{seo_name}');
            $properFilename = str_replace(
                ['{draft_id}', '{seo_name}', '{index}', '{article_slug}', '{date}', '{post_id}'],
                [$draftId, $slugBase, $imgIndex + 1, Str::limit(Str::slug($articleTitle, '-'), 40, ''), date('Ymd'), ''],
                $filenamePattern
            ) . '.' . $ext;
            $imgIndex++;

            $sourceDomain = parse_url($imgUrl, PHP_URL_HOST) ?: 'unknown';
            $sourceType = match(true) {
                str_contains($sourceDomain, 'pexels') => 'Pexels',
                str_contains($sourceDomain, 'unsplash') => 'Unsplash',
                str_contains($sourceDomain, 'pixabay') => 'Pixabay',
                str_contains($sourceDomain, 'apollo') || str_contains($sourceDomain, 'cdn.') => 'CDN/External',
                str_contains($imgUrl, 'blob:') => 'Local Upload',
                default => 'External URL',
            };
            $this->emitProgress($send, 'step', "Uploading photo {$imgIndex}/" . count($imageUrls) . ": {$properFilename}", 'media', 'inline_uploading', [
                'photo_index' => $imgIndex,
                'photo_total' => count($imageUrls),
                'filename' => $properFilename,
                'source_url' => $imgUrl,
            ]);
            $this->emitProgress($send, 'info', "  Source: {$sourceType} ({$sourceDomain})", 'media', 'inline_source', [
                'photo_index' => $imgIndex,
                'photo_total' => count($imageUrls),
                'source_domain' => $sourceDomain,
                'source_type' => $sourceType,
            ]);
            $this->emitProgress($send, 'info', "  URL: " . Str::limit($imgUrl, 120), 'media', 'inline_source', [
                'photo_index' => $imgIndex,
                'photo_total' => count($imageUrls),
                'source_url' => $imgUrl,
            ]);
            if ($altText) {
                $this->emitProgress($send, 'info', "  Alt: {$altText}", 'media', 'inline_meta', [
                    'photo_index' => $imgIndex,
                    'photo_total' => count($imageUrls),
                ]);
            }
            if ($caption) {
                $this->emitProgress($send, 'info', "  Caption: " . Str::limit($caption, 100), 'media', 'inline_meta', [
                    'photo_index' => $imgIndex,
                    'photo_total' => count($imageUrls),
                ]);
            }

            $uploadResult = $this->uploadWithRetry(
                $site, $mode, $server, $installId,
                $imgUrl, $properFilename, $altText, $caption, $send
            );

            if ($uploadResult['success'] && !empty($uploadResult['data']['media_url'])) {
                // Use configured inline size (default medium_large) instead of full
                $inlineSize = \hexa_core\Models\Setting::getValue('wp_inline_photo_size', 'medium_large');
                $sizes = $uploadResult['data']['sizes'] ?? [];
                $sizedUrl = $sizes[$inlineSize] ?? $sizes['medium_large'] ?? $sizes['large'] ?? $uploadResult['data']['media_url'];
                $imageMap[$rawImgUrl] = $sizedUrl;
                $wpImg = array_merge($uploadResult['data'], [
                    'source_url'  => $imgUrl,
                    'inline_url'  => $sizedUrl,
                    'filename'    => $properFilename,
                    'alt_text'    => $altText,
                    'caption'     => $caption,
                    'file_size'   => $uploadResult['data']['file_size'] ?? null,
                ]);
                $wpImages[] = $wpImg;
                // Set hexa meta on the attachment for filtering
                if (!empty($wpImg['media_id']) && $mode === 'rest') {
                    $this->setHexaMeta($site, (int) $wpImg['media_id'], $options['draft_id'] ?? 0);
                }
                $this->emitProgress($send, 'success', "Uploaded — media_id: " . ($wpImg['media_id'] ?? '?') . " | " . ($wpImg['file_size'] ? round($wpImg['file_size'] / 1024) . ' KB' : '') . " | " . $properFilename . "\nWP: " . Str::limit($sizedUrl, 100), 'media', 'inline_uploaded', [
                    'photo_index' => $imgIndex,
                    'photo_total' => count($imageUrls),
                    'media_id' => $wpImg['media_id'] ?? null,
                    'wp_image' => $wpImg,
                ]);
            } else {
                $this->emitProgress($send, 'error', "  Failed: {$properFilename} — " . ($uploadResult['message'] ?? 'unknown error'), 'media', 'inline_failed', [
                    'photo_index' => $imgIndex,
                    'photo_total' => count($imageUrls),
                    'filename' => $properFilename,
                ]);
            }
        }

        $this->emitProgress($send, 'success', count($imageMap) . "/" . count($imageUrls) . " images uploaded", 'media', 'inline_complete', [
            'uploaded_count' => count($imageMap),
            'image_total' => count($imageUrls),
        ]);

        // Replace image URLs in HTML
        if (!empty($imageMap)) {
            foreach ($imageMap as $oldUrl => $newUrl) {
                $html = str_replace($oldUrl, $newUrl, $html);
            }
            $this->emitProgress($send, 'success', count($imageMap) . " image URL(s) replaced in HTML", 'media', 'replace_html', [
                'replaced_count' => count($imageMap),
            ]);
        }

        return [$html, $wpImages];
    }

    /**
     * Create or resolve WordPress categories.
     *
     * @param PublishSite $site
     * @param string $mode
     * @param WhmServer|null $server
     * @param string|null $installId
     * @param array $categories
     * @param callable $send
     * @return array Category IDs
     */
    private function createCategories(PublishSite $site, string $mode, ?WhmServer $server, ?string $installId, array $categories, callable $send): array
    {
        return $this->resolveTaxonomyIds($site, $mode, $server, $installId, $categories, 'categories', $send);
    }

    /**
     * Create or resolve WordPress tags.
     *
     * @param PublishSite $site
     * @param string $mode
     * @param WhmServer|null $server
     * @param string|null $installId
     * @param array $tags
     * @param callable $send
     * @return array Tag IDs
     */
    private function createTags(PublishSite $site, string $mode, ?WhmServer $server, ?string $installId, array $tags, callable $send): array
    {
        return $this->resolveTaxonomyIds($site, $mode, $server, $installId, $tags, 'tags', $send);
    }

    /**
     * Create or resolve WordPress taxonomy terms.
     *
     * @param PublishSite $site
     * @param string $mode
     * @param WhmServer|null $server
     * @param string|null $installId
     * @param array $terms
     * @param string $taxonomy categories|tags
     * @param callable $send
     * @return array
     */
    private function resolveTaxonomyIds(PublishSite $site, string $mode, ?WhmServer $server, ?string $installId, array $terms, string $taxonomy, callable $send): array
    {
        if (empty($terms)) {
            $this->emitProgress($send, 'step', "No {$taxonomy} to create", 'taxonomy', $taxonomy . '_none');
            return [];
        }

        $this->emitProgress($send, 'info', "Creating " . count($terms) . " {$taxonomy}...", 'taxonomy', $taxonomy . '_start', [
            'term_total' => count($terms),
        ]);

        if ($mode === 'wptoolkit') {
            $batchResult = match ($taxonomy) {
                'categories' => $this->wptoolkit->wpCliBatchCategories($server, $installId, $terms),
                'tags' => $this->wptoolkit->wpCliBatchTags($server, $installId, $terms),
                default => ['term_ids' => []],
            };

            $termIds = $batchResult['term_ids'] ?? [];
            $termDetails = $batchResult['term_details'] ?? [];
            // Report per-term results with existed/created status
            foreach ($termDetails as $td) {
                $name = $td['name'] ?? '?';
                $id = $td['id'] ?? 0;
                $existed = $td['existed'] ?? false;
                $error = $td['error'] ?? null;
                if ($error) {
                    $this->emitProgress($send, 'warning', ucfirst($taxonomy) . ": '{$name}' — failed: {$error}", 'taxonomy', $taxonomy . '_term_failed', [
                        'term_name' => $name,
                    ]);
                } elseif ($existed) {
                    $this->emitProgress($send, 'success', ucfirst($taxonomy) . ": '{$name}' — already exists (id: {$id})", 'taxonomy', $taxonomy . '_term_ready', [
                        'term_name' => $name,
                        'term_id' => $id,
                    ]);
                } else {
                    $this->emitProgress($send, 'success', ucfirst($taxonomy) . ": '{$name}' — created (id: {$id})", 'taxonomy', $taxonomy . '_term_ready', [
                        'term_name' => $name,
                        'term_id' => $id,
                    ]);
                }
            }
            $this->emitProgress($send, 'success', count($termIds) . "/" . count($terms) . " {$taxonomy} ready", 'taxonomy', $taxonomy . '_complete', [
                'term_ready_count' => count($termIds),
                'term_total' => count($terms),
            ]);

            return $termIds;
        }

        $existingTerms = match ($taxonomy) {
            'categories' => $this->wp->getCategories($site->url, $site->wp_username, $site->wp_application_password),
            'tags' => $this->wp->getTags($site->url, $site->wp_username, $site->wp_application_password),
            default => ['success' => false, 'data' => []],
        };

        $existingTermMap = [];
        if ($existingTerms['success']) {
            foreach ($existingTerms['data'] as $term) {
                $existingTermMap[strtolower($term['name'])] = $term['id'];
            }
        }

        $termIds = [];
        foreach ($terms as $termName) {
            $termNameLower = strtolower(trim($termName));
            if (isset($existingTermMap[$termNameLower])) {
                $termIds[] = $existingTermMap[$termNameLower];
                continue;
            }

            $createdId = $this->createTaxonomyViaRest(
                $site->url,
                $site->wp_username,
                $site->wp_application_password,
                $taxonomy,
                $termName
            );

            if ($createdId) {
                $termIds[] = $createdId;
            }
        }

        $this->emitProgress($send, 'success', count($termIds) . "/" . count($terms) . " {$taxonomy} ready", 'taxonomy', $taxonomy . '_complete', [
            'term_ready_count' => count($termIds),
            'term_total' => count($terms),
        ]);

        return $termIds;
    }

    /**
     * Create a taxonomy term via REST API.
     *
     * @param string $siteUrl
     * @param string $username
     * @param string $appPassword
     * @param string $type categories|tags
     * @param string $name
     * @return int|null
     */
    private function createTaxonomyViaRest(string $siteUrl, string $username, string $appPassword, string $type, string $name): ?int
    {
        try {
            $resp = Http::withBasicAuth($username, $appPassword)
                ->timeout(10)
                ->post(rtrim($siteUrl, '/') . '/wp-json/wp/v2/' . $type, ['name' => $name]);
            if ($resp->successful()) return $resp->json('id');
        } catch (\Exception $e) {
            // Silently fail — category/tag creation is non-critical
        }
        return null;
    }

    /**
     * Resolve WHM server for SSH sites.
     *
     * @param PublishSite $site
     * @return array{server: WhmServer|null, account: HostingAccount|null}
     */
    /**
     * Set _hexa_upload meta on a WordPress attachment for filtering.
     *
     * @param PublishSite $site
     * @param int $mediaId
     * @param int $draftId
     */
    private function setHexaMeta(PublishSite $site, int $mediaId, int $draftId): void
    {
        try {
            Http::withBasicAuth($site->wp_username, $site->wp_application_password)
                ->timeout(10)
                ->post(rtrim($site->url, '/') . '/wp-json/wp/v2/media/' . $mediaId, [
                    'meta' => [
                        '_hexa_upload'   => true,
                        '_hexa_draft_id' => $draftId,
                    ],
                ]);
        } catch (\Exception $e) {
            // Non-critical — meta is for filtering only
        }
    }

    /**
     * Upload an image with smart retry — tries alternate URL formats on failure.
     *
     * Reports each attempt via $send callback so the prepare checklist shows progress.
     * Returns the first successful upload result, or the last failure.
     *
     * @param PublishSite $site
     * @param string $mode 'ssh' or 'rest'
     * @param WhmServer|null $server
     * @param string|null $installId
     * @param string $imageUrl Original image URL
     * @param string $filename Target filename
     * @param string $altText
     * @param string $caption
     * @param callable $send SSE progress callback
     * @return array Upload result with 'success', 'data', 'message', 'attempts'
     */
    private function uploadWithRetry(
        PublishSite $site, string $mode, ?WhmServer $server, ?string $installId,
        string $imageUrl, string $filename, string $altText, string $caption, callable $send
    ): array {
        $urlVariants = $this->buildUrlVariants($imageUrl);
        $attempts = [];
        $attemptNum = 0;

        foreach ($urlVariants as $variant) {
            $attemptNum++;
            $label = $variant['label'];
            $url = $variant['url'];
            $tryFilename = $variant['filename'] ?? $filename;

            if ($attemptNum > 1) {
                $this->emitProgress($send, 'info', "Retry #{$attemptNum}: {$label}", 'media', 'upload_retry', [
                    'attempt' => $attemptNum,
                    'attempt_label' => $label,
                ]);
            }

            if ($mode === 'wptoolkit') {
                $result = $this->wptoolkit->wpCliUploadMedia($server, $installId, $url, $tryFilename, $altText, $caption, $altText);
            } else {
                $result = $this->wp->uploadMedia($site->url, $site->wp_username, $site->wp_application_password, $url, $tryFilename, $altText);
            }

            $attempts[] = ['label' => $label, 'url' => $url, 'success' => $result['success'], 'message' => $result['message'] ?? ''];

            if ($result['success'] && !empty($result['data']['media_url'] ?? $result['data']['media_id'] ?? null)) {
                $result['attempts'] = $attempts;
                return $result;
            }

            $failMsg = $result['message'] ?? 'unknown error';
            $this->emitProgress($send, 'warning', "Retry #{$attemptNum} ({$label}) failed: " . Str::limit($failMsg, 80), 'media', 'upload_retry_failed', [
                'attempt' => $attemptNum,
                'attempt_label' => $label,
            ]);
        }

        return [
            'success' => false,
            'message' => "All {$attemptNum} download attempts failed",
            'attempts' => $attempts,
        ];
    }

    /**
     * Build URL variants to try when downloading an image.
     *
     * @param string $originalUrl
     * @return array<array{label: string, url: string, filename?: string}>
     */
    private function buildUrlVariants(string $originalUrl): array
    {
        $originalUrl = $this->normalizeMediaSourceUrl($originalUrl);
        $variants = [];

        $parsed = parse_url($originalUrl);
        $host = $parsed['host'] ?? '';
        $path = $parsed['path'] ?? '';
        $query = $parsed['query'] ?? '';
        $scheme = ($parsed['scheme'] ?? 'https') . '://' . $host;

        // Known CDN domains — don't swap extensions, use query-param variants instead
        $isCdn = str_contains($host, 'pexels') || str_contains($host, 'unsplash')
            || str_contains($host, 'pixabay') || str_contains($host, 'cdn.');

        if ($isCdn) {
            // Source-specific format variants
            if (str_contains($host, 'pexels')) {
                $variants[] = ['label' => 'Pexels small', 'url' => $scheme . $path . '?auto=compress&cs=tinysrgb&w=1200'];
                $variants[] = ['label' => 'Pexels compress only', 'url' => $scheme . $path . '?auto=compress'];
            } elseif (str_contains($host, 'unsplash')) {
                $variants[] = ['label' => 'Unsplash JPG', 'url' => $scheme . $path . '?fm=jpg&q=80&w=1200'];
                $variants[] = ['label' => 'Unsplash raw', 'url' => $scheme . $path . '?fm=jpg&q=90'];
            } elseif (str_contains($host, 'pixabay')) {
                $variants[] = ['label' => 'Pixabay direct', 'url' => $scheme . $path];
            }

            // Strip resize/transform params, keep the base image path
            if ($query) {
                $variants[] = ['label' => 'Strip CDN params', 'url' => $scheme . $path];
            }

            $variants[] = ['label' => 'Direct URL', 'url' => $originalUrl];
        } else {
            $variants[] = ['label' => 'Direct URL', 'url' => $originalUrl];

            // Non-CDN: strip query params + try alternate extensions
            if ($query) {
                $variants[] = ['label' => 'Strip query params', 'url' => $scheme . $path];
            }
            $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            $ext = preg_replace('/[^a-z].*/', '', $ext);
            $baseUrl = $scheme . preg_replace('/\.[^.\/]+$/', '', $path);

            foreach (['jpg', 'jpeg', 'webp', 'png'] as $altExt) {
                if ($altExt === $ext) continue;
                $variants[] = ['label' => "Swap to .{$altExt}", 'url' => $baseUrl . '.' . $altExt, 'filename' => pathinfo($path, PATHINFO_FILENAME) . '.' . $altExt];
            }
        }

        $unique = [];
        foreach ($variants as $variant) {
            $unique[$variant['url']] = $variant;
        }

        return array_values($unique);
    }

    private function buildExistingUploadMap(array $existingUploads): array
    {
        $map = [];

        foreach ($existingUploads as $existingUpload) {
            if (empty($existingUpload['media_url']) && empty($existingUpload['source_url'])) {
                continue;
            }

            $this->rememberExistingUploadUrl($map, $existingUpload, $existingUpload['source_url'] ?? null);
            $this->rememberExistingUploadUrl($map, $existingUpload, $existingUpload['media_url'] ?? null);
            $this->rememberExistingUploadUrl($map, $existingUpload, $existingUpload['inline_url'] ?? null);

            foreach ($existingUpload['sizes'] ?? [] as $sizeUrl) {
                $this->rememberExistingUploadUrl($map, $existingUpload, $sizeUrl);
            }
        }

        return $map;
    }

    private function rememberExistingUploadUrl(array &$map, array $existingUpload, mixed $url): void
    {
        if (!is_string($url) || trim($url) === '') {
            return;
        }

        $raw = trim($url);
        $map[$raw] = $existingUpload;

        $normalized = $this->normalizeMediaSourceUrl($raw);
        if ($normalized !== '') {
            $map[$normalized] = $existingUpload;
        }
    }

    private function normalizeMediaSourceUrl(string $url): string
    {
        return trim(html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    private function emitProgress(callable $send, string $type, string $message, string $stage, string $substage, array $extra = []): void
    {
        $send($type, $message, array_merge([
            'stage' => $stage,
            'substage' => $substage,
        ], $extra));
    }

    private function resolveServer(PublishSite $site): array
    {
        $account = HostingAccount::find($site->hosting_account_id);
        $server = $account ? WhmServer::find($account->whm_server_id) : null;
        return ['server' => $server, 'account' => $account];
    }

    /**
     * Sanitize HTML for WordPress — strip editor artifacts, placeholders, and UI elements.
     *
     * @param string $html
     * @return string
     */
    private function sanitizeHtml(string $html): string
    {
        // Convert photo placeholders into clean <figure> tags
        // Match: <div class="photo-placeholder" ...> ... <img ...> ... </div>
        $html = preg_replace_callback(
            '/<div[^>]*class="[^"]*photo-placeholder[^"]*"[^>]*>(.*?)<\/div>/is',
            function ($match) {
                $inner = $match[1];
                // Extract the <img> tag
                $img = '';
                if (preg_match('/<img[^>]+>/i', $inner, $imgMatch)) {
                    $img = $imgMatch[0];
                    // Clean inline styles from img, keep src and alt
                    $img = preg_replace('/\s+style="[^"]*"/i', '', $img);
                    // Extract alt from data-caption or existing alt
                    $alt = '';
                    if (preg_match('/data-caption="([^"]*)"/i', $match[0], $capMatch)) {
                        $alt = html_entity_decode($capMatch[1], ENT_QUOTES, 'UTF-8');
                    }
                    if (!$alt && preg_match('/alt="([^"]*)"/i', $img, $altMatch)) {
                        $alt = $altMatch[1];
                    }
                    // Ensure alt attribute
                    if ($alt && !preg_match('/alt="/i', $img)) {
                        $img = str_replace('<img ', '<img alt="' . htmlspecialchars($alt, ENT_QUOTES) . '" ', $img);
                    } elseif ($alt) {
                        $img = preg_replace('/alt="[^"]*"/i', 'alt="' . htmlspecialchars($alt, ENT_QUOTES) . '"', $img);
                    }
                }
                // Extract caption text from data-caption or <p> tags (skip the button labels)
                $caption = '';
                if (preg_match('/data-caption="([^"]*)"/i', $match[0], $capMatch)) {
                    $caption = html_entity_decode($capMatch[1], ENT_QUOTES, 'UTF-8');
                }
                if (!$caption) {
                    // Get second <p> as caption (first is search term)
                    if (preg_match_all('/<p[^>]*>([^<]+)<\/p>/i', $inner, $pMatches) && count($pMatches[1]) > 1) {
                        $caption = trim($pMatches[1][1]);
                    }
                }

                if (!$img) return ''; // No image found — remove entirely

                $figure = '<figure>';
                $figure .= $img;
                if ($caption) $figure .= '<figcaption>' . htmlspecialchars($caption, ENT_QUOTES) . '</figcaption>';
                $figure .= '</figure>';
                return $figure;
            },
            $html
        );

        // Remove any remaining editor action buttons/spans
        $html = preg_replace('/<span[^>]*class="[^"]*photo-(?:view|confirm|change|remove)[^"]*"[^>]*>.*?<\/span>/is', '', $html);
        $html = preg_replace('/<button[^>]*>.*?<\/button>/is', '', $html);

        // Remove "Loading photo..." placeholders that escaped the div cleanup
        $html = preg_replace('/<span[^>]*>Loading photo[^<]*<\/span>/i', '', $html);
        $html = preg_replace('/<p[^>]*>\s*<span[^>]*>Loading photo[^<]*<\/span>\s*<\/p>/i', '', $html);
        // Remove orphaned photo placeholder divs without the class (TinyMCE sometimes strips it)
        $html = preg_replace('/<div[^>]*>\s*<span[^>]*>Loading photo[^<]*<\/span>\s*<\/div>/i', '', $html);

        // Remove Alpine.js directives from any remaining elements
        $html = preg_replace('/\s+(?:x-[\w.-]+|@[\w.-]+(?:\.\w+)*|:[\w-]+)\s*=\s*"[^"]*"/i', '', $html);
        $html = preg_replace('/\s+x-cloak/i', '', $html);

        // Remove editor data-* attributes
        $html = preg_replace('/\s+data-(?:photo|editor|placeholder|suggestion|idx|confirmed|removed|search|caption)[a-z-]*\s*=\s*"[^"]*"/i', '', $html);
        $html = preg_replace('/\s+contenteditable="[^"]*"/i', '', $html);

        // Remove inline styles from divs that were photo containers (keep content)
        $html = preg_replace('/\s+style="[^"]*border:\s*2px\s+(?:solid|dashed)\s+#[a-f0-9]+[^"]*"/i', '', $html);

        // Clean up empty wrappers
        $html = preg_replace('/<figure[^>]*>\s*<\/figure>/is', '', $html);
        $html = preg_replace('/<figcaption[^>]*>\s*<\/figcaption>/is', '', $html);

        // Remove trailing invisible Unicode characters and empty block elements
        $html = preg_replace('/[\x{200B}-\x{200D}\x{FEFF}]+/u', '', $html);
        $html = preg_replace('/<(p|div|span)>\s*<\/\1>/i', '', $html);

        // Clean up excessive whitespace
        $html = preg_replace('/\n{3,}/', "\n\n", $html);

        return trim($html);
    }
}
