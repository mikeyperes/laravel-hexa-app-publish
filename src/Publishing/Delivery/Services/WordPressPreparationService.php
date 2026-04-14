<?php

namespace hexa_app_publish\Publishing\Delivery\Services;

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
        $mode = $site->connection_type === 'wptoolkit' ? 'ssh' : 'rest';

        $send('info', "Connecting to {$site->name} via {$mode}...");

        // Validate credentials
        if ($mode === 'rest' && (!$site->wp_username || !$site->wp_application_password)) {
            $send('error', "Site '{$site->name}' has no WordPress credentials configured.");
            return ['success' => false, 'html' => $html, 'category_ids' => [], 'tag_ids' => [], 'wp_images' => []];
        }

        $server = null;
        $installId = null;
        if ($mode === 'ssh') {
            $resolved = $this->resolveServer($site);
            $server = $resolved['server'];
            $installId = $site->wordpress_install_id;
            if (!$server || !$installId) {
                $send('error', "Missing WP Toolkit server or install ID.");
                return ['success' => false, 'html' => $html, 'category_ids' => [], 'tag_ids' => [], 'wp_images' => []];
            }
            $send('success', "SSH server resolved: {$server->hostname}");
        } else {
            $send('success', "REST API credentials verified for {$site->wp_username}");
        }

        // Step 0a: Verify author exists on WordPress
        $author = $site->default_author;
        if ($author && $mode === 'ssh' && $server && $installId) {
            $send('info', "Verifying author '{$author}' exists on WordPress...");
            $ssh = $this->wptoolkit->getConnection($server);
            if ($ssh['success']) {
                $eid = escapeshellarg((string) $installId);
                $userCheck = trim($ssh['connection']->exec("wp-toolkit --wp-cli -instance-id {$eid} -- user get " . escapeshellarg($author) . " --field=ID 2>/dev/null"));
                $wpUserId = '';
                foreach (explode("\n", $userCheck) as $ul) { $ul = trim($ul); if (is_numeric($ul)) { $wpUserId = $ul; break; } }
                if ($wpUserId) {
                    $send('success', "Author verified: {$author} (WP user ID: {$wpUserId})");
                } else {
                    $send('warning', "Author '{$author}' NOT FOUND on WordPress — post will use default author. Set a valid author in site settings.");
                }
            }
        } elseif (!$author) {
            $send('warning', "No default author configured for {$site->name} — post will use WP default author.");
        }

        // Step 0b: Clean HTML — strip editor artifacts
        $send('info', "Cleaning HTML...");
        $html = $this->sanitizeHtml($html);
        $send('success', "HTML cleaned for WordPress");

        // Step 1: Upload images
        [$html, $wpImages] = $this->uploadImages($site, $html, $mode, $server, $installId, $options, $send);

        // Step 1b: Upload featured image
        $featuredMediaId = null;
        $featuredWpUrl = null;
        $featuredMeta = $options['featured_meta'] ?? null;
        if ($featuredMeta) {
            $featuredUrl = $options['featured_url'] ?? null;
            if ($featuredUrl) {
                $fDomain = parse_url($featuredUrl, PHP_URL_HOST) ?: 'unknown';
                $fSource = match(true) {
                    str_contains($fDomain, 'pexels') => 'Pexels',
                    str_contains($fDomain, 'unsplash') => 'Unsplash',
                    str_contains($fDomain, 'pixabay') => 'Pixabay',
                    str_contains($fDomain, 'cdn.') => 'CDN/External',
                    default => 'External URL',
                };
                $send('info', "Uploading featured image — Source: {$fSource} ({$fDomain})");
                $send('info', "  URL: " . Str::limit($featuredUrl, 120));
                $fFilename = $featuredMeta['filename'] ?? 'featured';
                $ext = pathinfo(parse_url($featuredUrl, PHP_URL_PATH), PATHINFO_EXTENSION) ?: 'jpg';
                $fFilename = Str::endsWith($fFilename, '.' . $ext) ? $fFilename : $fFilename . '.' . $ext;

                if ($mode === 'ssh') {
                    $uploadResult = $this->wptoolkit->wpCliUploadMedia($server, $installId, $featuredUrl, $fFilename, $featuredMeta['alt_text'] ?? '', $featuredMeta['caption'] ?? '', $featuredMeta['alt_text'] ?? '');
                } else {
                    $uploadResult = $this->wp->uploadMedia($site->url, $site->wp_username, $site->wp_application_password, $featuredUrl, $fFilename, $featuredMeta['alt_text'] ?? '');
                }

                if ($uploadResult['success'] && !empty($uploadResult['data']['media_id'])) {
                    $featuredMediaId = $uploadResult['data']['media_id'];
                    $featuredWpUrl = $uploadResult['data']['media_url'] ?? null;
                    $wpImages[] = array_merge($uploadResult['data'], ['is_featured' => true, 'source_url' => $featuredUrl, 'filename' => $fFilename]);
                    if ($mode === 'rest') {
                        $this->setHexaMeta($site, (int) $featuredMediaId, $options['draft_id'] ?? 0);
                    }
                    $send('success', "Featured image uploaded: media_id={$featuredMediaId}", ['wp_image' => $uploadResult['data']]);
                } else {
                    $send('error', "Featured image upload failed: " . ($uploadResult['message'] ?? 'unknown'));
                }
            } else {
                $send('warning', "No featured image URL provided");
            }
        }

        // Step 2: Create categories
        $categoryIds = $this->createCategories($site, $mode, $server, $installId, $options['categories'] ?? [], $send);

        // Step 3: Create tags
        $tagIds = $this->createTags($site, $mode, $server, $installId, $options['tags'] ?? [], $send);

        // Step 4: Integrity check — validate HTML structure, photos, and cleanup
        $send('info', "Running integrity check...");
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
            $send('success', "Integrity check passed — HTML clean, all photos processed");
        } else {
            foreach ($integrityIssues as $issue) {
                $send('warning', "Integrity: {$issue} (auto-fixed)");
            }
            $send($htmlValid ? 'success' : 'error', 'Integrity check complete — ' . count($integrityIssues) . ' issue(s) auto-fixed');
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
            $send('step', "No images to upload");
            return [$html, $wpImages];
        }

        $send('info', "Uploading " . count($imageUrls) . " image(s)...");
        $articleTitle = $options['title'] ?? 'article';
        $draftId = $options['draft_id'] ?? 0;

        // Build photo metadata lookup from photo_suggestions (legacy) + photo_meta (new)
        $photoMeta = [];
        foreach ($options['photo_suggestions'] ?? [] as $ps) {
            if (!empty($ps['autoPhoto']['url_large'])) {
                $photoMeta[$ps['autoPhoto']['url_large']] = $ps;
            }
        }
        // Indexed photo_meta from pipeline (ordered, matched by position)
        $photoMetaIndexed = $options['photo_meta'] ?? [];

        foreach ($imageUrls as $imgUrl) {
            if (str_starts_with($imgUrl, rtrim($site->url, '/'))) continue;

            $ps = $photoMeta[$imgUrl] ?? null;
            $pm = $photoMetaIndexed[$imgIndex] ?? null;
            $altText = $pm['alt_text'] ?? $ps['alt_text'] ?? '';
            $caption = $pm['caption'] ?? $ps['caption'] ?? '';
            $seoName = $pm['filename'] ?? $ps['suggestedFilename'] ?? '';

            if (!$altText && preg_match('/<img[^>]+src\s*=\s*["\']' . preg_quote($imgUrl, '/') . '["\'][^>]*alt\s*=\s*["\']([^"\']*)["\'][^>]*>/i', $html, $altMatch)) {
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
            $send('step', "Uploading photo {$imgIndex}/" . count($imageUrls) . ": {$properFilename}");
            $send('info', "  Source: {$sourceType} ({$sourceDomain})");
            $send('info', "  URL: " . Str::limit($imgUrl, 120));
            if ($altText) $send('info', "  Alt: {$altText}");
            if ($caption) $send('info', "  Caption: " . Str::limit($caption, 100));

            if ($mode === 'ssh') {
                $uploadResult = $this->wptoolkit->wpCliUploadMedia($server, $installId, $imgUrl, $properFilename, $altText, $caption, $altText);
            } else {
                $uploadResult = $this->wp->uploadMedia($site->url, $site->wp_username, $site->wp_application_password, $imgUrl, $properFilename, $altText);
            }

            if ($uploadResult['success'] && !empty($uploadResult['data']['media_url'])) {
                $imageMap[$imgUrl] = $uploadResult['data']['media_url'];
                $wpImg = array_merge($uploadResult['data'], [
                    'source_url'  => $imgUrl,
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
                $send('success', "  Uploaded: media_id=" . ($wpImg['media_id'] ?? '?') . " | " . ($wpImg['file_size'] ? round($wpImg['file_size'] / 1024) . ' KB' : '') . " | " . Str::limit($wpImg['media_url'] ?? '', 80), ['wp_image' => $wpImg]);
            } else {
                $send('error', "  Failed: {$properFilename} — " . ($uploadResult['message'] ?? 'unknown error'));
            }
        }

        $send('success', count($imageMap) . "/" . count($imageUrls) . " images uploaded");

        // Replace image URLs in HTML
        if (!empty($imageMap)) {
            foreach ($imageMap as $oldUrl => $newUrl) {
                $html = str_replace($oldUrl, $newUrl, $html);
            }
            $send('success', count($imageMap) . " image URL(s) replaced in HTML");
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
            $send('step', "No {$taxonomy} to create");
            return [];
        }

        $send('info', "Creating " . count($terms) . " {$taxonomy}...");

        if ($mode === 'ssh') {
            $batchResult = match ($taxonomy) {
                'categories' => $this->wptoolkit->wpCliBatchCategories($server, $installId, $terms),
                'tags' => $this->wptoolkit->wpCliBatchTags($server, $installId, $terms),
                default => ['term_ids' => []],
            };

            $termIds = $batchResult['term_ids'] ?? [];
            $send('success', count($termIds) . "/" . count($terms) . " {$taxonomy} ready");

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

        $send('success', count($termIds) . "/" . count($terms) . " {$taxonomy} ready");

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
