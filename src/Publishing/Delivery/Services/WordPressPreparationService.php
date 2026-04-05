<?php

namespace hexa_app_publish\Publishing\Delivery\Services;

use hexa_app_publish\Models\PublishSite;
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

        // Step 1: Upload images
        [$html, $wpImages] = $this->uploadImages($site, $html, $mode, $server, $installId, $options, $send);

        // Step 2: Create categories
        $categoryIds = $this->createCategories($site, $mode, $server, $installId, $options['categories'] ?? [], $send);

        // Step 3: Create tags
        $tagIds = $this->createTags($site, $mode, $server, $installId, $options['tags'] ?? [], $send);

        // Step 4: Validate HTML
        $send('info', "Validating HTML...");
        $htmlValid = !empty(trim(strip_tags($html)));
        $send($htmlValid ? 'success' : 'error', $htmlValid ? 'HTML valid' : 'HTML is empty after processing');

        return [
            'success'      => $htmlValid,
            'html'         => $html,
            'category_ids' => $categoryIds,
            'tag_ids'      => $tagIds,
            'wp_images'    => $wpImages,
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

            $send('step', "Uploading photo {$imgIndex}/" . count($imageUrls) . ": {$properFilename}");
            $send('info', "  Source: " . Str::limit($imgUrl, 100));
            if ($altText) $send('info', "  Alt: {$altText}");
            if ($caption) $send('info', "  Caption: " . Str::limit($caption, 100));

            if ($mode === 'ssh') {
                $uploadResult = $this->wptoolkit->wpCliUploadMedia($server, $installId, $imgUrl, $properFilename, $altText, $caption, $altText);
            } else {
                $uploadResult = $this->wp->uploadMedia($site->url, $site->wp_username, $site->wp_application_password, $imgUrl, $properFilename, $altText);
            }

            if ($uploadResult['success'] && !empty($uploadResult['data']['media_url'])) {
                $imageMap[$imgUrl] = $uploadResult['data']['media_url'];
                $wpImg = $uploadResult['data'];
                $wpImages[] = $wpImg;
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
        if (empty($categories)) {
            $send('step', "No categories to create");
            return [];
        }

        $send('info', "Creating " . count($categories) . " categories...");
        $categoryIds = [];

        if ($mode === 'ssh') {
            $batchResult = $this->wptoolkit->wpCliBatchCategories($server, $installId, $categories);
            $categoryIds = $batchResult['term_ids'] ?? [];
        } else {
            $existingCats = $this->wp->getCategories($site->url, $site->wp_username, $site->wp_application_password);
            $existingCatMap = [];
            if ($existingCats['success']) {
                foreach ($existingCats['data'] as $cat) $existingCatMap[strtolower($cat['name'])] = $cat['id'];
            }
            foreach ($categories as $catName) {
                $catNameLower = strtolower(trim($catName));
                if (isset($existingCatMap[$catNameLower])) {
                    $categoryIds[] = $existingCatMap[$catNameLower];
                } else {
                    $catResult = $this->createTaxonomyViaRest($site->url, $site->wp_username, $site->wp_application_password, 'categories', $catName);
                    if ($catResult) $categoryIds[] = $catResult;
                }
            }
        }

        $send('success', count($categoryIds) . "/" . count($categories) . " categories ready");
        return $categoryIds;
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
        if (empty($tags)) {
            $send('step', "No tags to create");
            return [];
        }

        $send('info', "Creating " . count($tags) . " tags...");
        $tagIds = [];

        if ($mode === 'ssh') {
            $batchResult = $this->wptoolkit->wpCliBatchTags($server, $installId, $tags);
            $tagIds = $batchResult['term_ids'] ?? [];
        } else {
            $existingTags = $this->wp->getTags($site->url, $site->wp_username, $site->wp_application_password);
            $existingTagMap = [];
            if ($existingTags['success']) {
                foreach ($existingTags['data'] as $tag) $existingTagMap[strtolower($tag['name'])] = $tag['id'];
            }
            foreach ($tags as $tagName) {
                $tagNameLower = strtolower(trim($tagName));
                if (isset($existingTagMap[$tagNameLower])) {
                    $tagIds[] = $existingTagMap[$tagNameLower];
                } else {
                    $tagResult = $this->createTaxonomyViaRest($site->url, $site->wp_username, $site->wp_application_password, 'tags', $tagName);
                    if ($tagResult) $tagIds[] = $tagResult;
                }
            }
        }

        $send('success', count($tagIds) . "/" . count($tags) . " tags ready");
        return $tagIds;
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
    private function resolveServer(PublishSite $site): array
    {
        $account = HostingAccount::find($site->hosting_account_id);
        $server = $account ? WhmServer::find($account->whm_server_id) : null;
        return ['server' => $server, 'account' => $account];
    }
}
