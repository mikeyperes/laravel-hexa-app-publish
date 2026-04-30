<?php

namespace hexa_app_publish\Publishing\Pipeline\Services;

use hexa_package_google_drive\Services\GoogleDriveService;
use hexa_package_notion\Services\NotionService;
use Illuminate\Support\Str;

class PressReleaseNotionPodcastImportService
{
    public function __construct(
        private NotionService $notion
    ) {}

    public function searchEpisodes(string $query = '', int $limit = 10): array
    {
        $databaseId = $this->podcastDatabaseId();
        if ($databaseId === '') {
            return [
                'success' => false,
                'message' => 'Podcast database is not configured in Notion.',
                'records' => [],
            ];
        }

        $query = trim($query);
        if ($query !== '') {
            $result = $this->notion->searchDatabaseEntries($databaseId, $query, $limit);
            if (!($result['success'] ?? false)) {
                return [
                    'success' => false,
                    'message' => $result['error'] ?? 'Failed to search podcast episodes.',
                    'records' => [],
                ];
            }

            return [
                'success' => true,
                'message' => 'Episodes loaded.',
                'records' => array_map(fn (array $page) => $this->normalizeEpisodeRecord($page), array_values($result['records'] ?? [])),
            ];
        }

        $schemaResult = $this->notion->getDatabaseSchema($databaseId);
        if (!($schemaResult['success'] ?? false)) {
            return [
                'success' => false,
                'message' => $schemaResult['error'] ?? 'Failed to load podcast database schema.',
                'records' => [],
            ];
        }

        $result = $this->notion->queryDatabase($databaseId, [], [['property' => 'Schedule', 'direction' => 'descending']], max(1, min($limit, 15)));
        if (!($result['success'] ?? false)) {
            return [
                'success' => false,
                'message' => $result['error'] ?? 'Failed to load recent podcast episodes.',
                'records' => [],
            ];
        }

        $titleField = $this->notion->resolveTitlePropertyName($schemaResult['schema'] ?? []);
        $records = [];
        foreach (($result['results'] ?? []) as $page) {
            $records[] = $this->normalizeEpisodeRecord($page, $titleField);
        }

        return [
            'success' => true,
            'message' => 'Recent episodes loaded.',
            'records' => $records,
        ];
    }

    public function importEpisode(string $pageId): array
    {
        $pageId = trim($pageId);
        if ($pageId === '') {
            return [
                'success' => false,
                'message' => 'No episode was selected.',
            ];
        }

        $episodeParsed = $this->notion->getPage($pageId);
        $episodeRaw = $this->notion->getPageRaw($pageId);

        if (!($episodeParsed['success'] ?? false) || !($episodeRaw['success'] ?? false)) {
            return [
                'success' => false,
                'message' => $episodeParsed['error'] ?? $episodeRaw['error'] ?? 'Failed to load the Notion episode.',
            ];
        }

        $episodePage = $episodeParsed['page'] ?? [];
        $episodeProperties = $episodePage['properties'] ?? [];
        $episodeTitle = (string) ($this->firstValue($episodeProperties, ['Name', 'Title']) ?: 'Untitled Podcast Episode');
        $guestProperty = $this->guestRelationProperty();
        $guestIds = array_values(array_filter(array_map(
            fn ($relation) => is_array($relation) ? (string) ($relation['id'] ?? '') : '',
            (array) (($episodeRaw['page']['properties'][$guestProperty]['relation'] ?? []) ?: [])
        )));

        $guestPages = [];
        foreach ($guestIds as $guestId) {
            $guestParsed = $this->notion->getPage($guestId);
            if ($guestParsed['success'] ?? false) {
                $guestPages[] = $guestParsed['page'] ?? [];
            }
        }

        $liveLinks = $this->normalizeUrlList($this->firstValue($episodeProperties, ['Live Link/s', 'Live Links', 'Live Link', 'Episode URL', 'Website Episode URL']));
        $youtubeUrl = $this->resolveYoutubeUrl($episodeProperties, $liveLinks);
        $youtubeEmbedUrl = $this->youtubeEmbedUrl($youtubeUrl);
        $podcastAssets = $this->normalizeUrlList($episodeProperties['Podcast Assets'] ?? []);
        $guestName = $this->guestNames($guestPages);
        $personUrl = $this->preferredPersonUrl($guestPages);
        $companyName = $this->guestCompanyName($guestPages);
        $companyUrl = $this->preferredCompanyUrl($guestPages);
        $driveFolderUrl = $this->firstDriveFolderUrl($podcastAssets);
        $drivePhotos = $this->fetchDrivePhotos($driveFolderUrl, $guestName, $episodeTitle);
        $episodeFeaturedImage = $this->preferredEpisodeFeaturedMedia($episodeProperties, $podcastAssets, $youtubeUrl);
        $episodeFeaturedImageUrl = (string) ($episodeFeaturedImage['url'] ?? '');
        $inlineGuestImage = $this->preferredInlineGuestImage($guestPages, $drivePhotos, $episodeFeaturedImageUrl);
        $inlineGuestImageUrl = (string) ($inlineGuestImage['url'] ?? '');
        $episodeDate = $this->formatDateValue($this->firstValue($episodeProperties, ['Schedule', 'Publish Date', 'Date', 'Entry Creation Date']));
        $details = [
            'date' => $episodeDate ?: now()->format('F j, Y'),
            'location' => 'Miami, Florida',
            'contact' => $guestName !== '' ? $guestName : trim((string) ($this->firstValue($episodeProperties, ['Host', 'Guest']) ?: '')),
            'contact_url' => $this->preferredContactUrl($guestPages, $liveLinks),
        ];
        $linkTargets = [
            'person_name' => $guestName,
            'person_url' => $personUrl,
            'company_name' => $companyName,
            'company_url' => $companyUrl,
            'episode_url' => $liveLinks[0] ?? '',
            'youtube_url' => $youtubeUrl,
            'youtube_embed_url' => $youtubeEmbedUrl,
            'featured_image_url' => $episodeFeaturedImageUrl,
            'inline_guest_image_url' => $inlineGuestImageUrl,
            'contact_url' => $details['contact_url'],
        ];

        $missing = [];
        if (empty($guestIds)) {
            $missing[] = 'Guest relation missing on the selected episode.';
        }
        if (empty($liveLinks)) {
            $missing[] = 'Live Link/s missing on the selected episode.';
        }
        if ($youtubeUrl === null) {
            $missing[] = 'YouTube URL missing on the selected episode.';
        }
        if (empty($podcastAssets)) {
            $missing[] = 'Podcast Assets missing on the selected episode.';
        }
        if ($driveFolderUrl === '') {
            $missing[] = 'Podcast Assets does not include a Google Drive folder URL.';
        }
        if ($episodeDate === '') {
            $missing[] = 'Schedule / publish date missing on the selected episode. Dateline will fall back to the current date unless you override it.';
        }
        if ($details['contact_url'] === '') {
            $missing[] = 'Guest website / contact URL missing.';
        }
        if ($personUrl === '') {
            $missing[] = 'Guest profile link missing. Add a public personal URL in the related People record.';
        }
        if ($companyName === '' || $companyUrl === '') {
            $missing[] = 'Guest company name or company URL missing in the related People record.';
        }
        if ($episodeFeaturedImageUrl === '') {
            $missing[] = 'Featured Image URL missing on the selected podcast episode.';
        }
        if ($inlineGuestImageUrl === '') {
            $missing[] = 'No inline guest/client image could be resolved from Podcast Assets or the People record.';
        }
        if ($this->guestBio($guestPages) === '') {
            $missing[] = 'Guest biography is thin or missing in the related People record.';
        }

        $sourceText = $this->buildSourceText(
            $episodeTitle,
            $episodeProperties,
            $guestPages,
            $liveLinks,
            $podcastAssets,
            $youtubeUrl,
            $details,
            $missing,
            $linkTargets
        );
        $detectedPhotos = $this->buildDetectedPhotos(
            $episodeTitle,
            $episodeProperties,
            $guestPages,
            $podcastAssets,
            $drivePhotos,
            $episodeFeaturedImageUrl,
            $inlineGuestImageUrl
        );

        return [
            'success' => true,
            'message' => 'Podcast episode imported from Notion.',
            'source_text' => $sourceText,
            'preview' => Str::limit($sourceText, 1200, '...'),
            'label' => 'Notion Podcast Episode · ' . $episodeTitle,
            'details' => $details,
            'source_fields' => $this->buildSourceFields(
                $episodeProperties,
                $guestPages,
                $linkTargets,
                $episodeFeaturedImage,
                $inlineGuestImage
            ),
            'detected_photos' => $detectedPhotos,
            'selected_episode' => [
                'id' => $pageId,
                'title' => $episodeTitle,
                'schedule' => $details['date'],
                'guest' => $guestName,
                'live_links' => $liveLinks,
                'youtube_url' => $youtubeUrl,
                'youtube_embed_url' => $youtubeEmbedUrl,
                'podcast_assets' => $podcastAssets,
                'drive_folder_url' => $driveFolderUrl,
                'featured_image_url' => $episodeFeaturedImageUrl,
                'featured_image_source_field' => (string) ($episodeFeaturedImage['field'] ?? ''),
                'record_url' => $episodePage['url'] ?? null,
            ],
            'selected_guest' => [
                'name' => $guestName,
                'record_ids' => $guestIds,
                'record_url' => $guestPages[0]['url'] ?? null,
                'bio' => $this->guestBio($guestPages),
                'person_url' => $personUrl,
                'company_name' => $companyName,
                'company_url' => $companyUrl,
                'inline_photo_url' => $inlineGuestImageUrl,
                'inline_photo_source_field' => (string) ($inlineGuestImage['field'] ?? ''),
                'job_title' => $this->guestJobTitle($guestPages),
            ],
            'missing_fields' => $missing,
        ];
    }

    private function podcastDatabaseId(): string
    {
        return trim((string) config('notion.profile_relations.person.podcast_interviews.database_id', ''));
    }

    private function guestRelationProperty(): string
    {
        return trim((string) config('notion.profile_relations.person.podcast_interviews.target_relation_property', 'Guest')) ?: 'Guest';
    }

    private function buildEpisodeSubtitle(array $properties): string
    {
        $parts = [];
        $status = trim((string) ($this->firstValue($properties, ['Status']) ?: ''));
        $schedule = $this->formatDateValue($this->firstValue($properties, ['Schedule', 'Publish Date', 'Entry Creation Date']));
        $live = $this->normalizeUrlList($this->firstValue($properties, ['Live Link/s', 'Live Links', 'Episode URL']));

        if ($status !== '') {
            $parts[] = 'Status: ' . $status;
        }
        if ($schedule !== '') {
            $parts[] = $schedule;
        }
        if (!empty($live)) {
            $parts[] = count($live) . ' live link(s)';
        }

        return implode(' • ', $parts);
    }

    private function normalizeEpisodeRecord(array $page, ?string $titleField = null): array
    {
        $properties = $page['properties'] ?? [];
        if (empty($properties) && isset($page['title'])) {
            return [
                'id' => $page['id'] ?? null,
                'title' => (string) ($page['title'] ?? 'Untitled Podcast Episode'),
                'subtitle' => trim((string) preg_replace('/\s*•\s*YouTube Video ID:.*$/u', '', (string) ($page['subtitle'] ?? ''))),
                'url' => $page['url'] ?? null,
                'last_edited_time' => $page['last_edited_time'] ?? null,
            ];
        }

        return [
            'id' => $page['id'] ?? null,
            'title' => (string) ($this->firstValue($properties, ['Name', 'Title']) ?: $this->notion->extractDisplayTitleFromParsedPage($page, $titleField ?: 'Name') ?: 'Untitled Podcast Episode'),
            'subtitle' => $this->buildEpisodeSubtitle($properties),
            'url' => $page['url'] ?? null,
            'last_edited_time' => $page['last_edited_time'] ?? null,
        ];
    }

    private function buildSourceText(
        string $episodeTitle,
        array $episodeProperties,
        array $guestPages,
        array $liveLinks,
        array $podcastAssets,
        ?string $youtubeUrl,
        array $details,
        array $missing,
        array $linkTargets
    ): string {
        $parts = [];
        $parts[] = "=== Podcast Press Release Mission ===\nThis is a Hexa PR Wire press release for a Michael Peres Podcast episode. Write it as a formal release announcing the guest appearance and what the discussion covers. Use only the facts below.";

        $parts[] = "=== Episode Record ===\n" . $this->renderPropertyBlock($episodeProperties, [
            'Status', 'Schedule', 'Name', 'Podcast Name', 'Season', 'Episode Number', 'Duration', 'Short Summary', 'Episode Summary', 'Excerpt',
            'Episode Notes / Content', 'Questions', 'Submission Questions', 'Topics to avoid', 'Episode Categories', 'Episode Tags',
            'Live Link/s', 'Website Episode URL', 'Website Episode Slug', 'YouTube URL', 'Spotify Episode URL', 'Apple Podcast Episode URL',
            'RSS URL', 'Press Release Links', 'Additional Resources', 'Additional Information', 'Featured Image URL', 'Thumbnail', 'Photos',
            'Additional Image URLs', 'Podcast Assets'
        ]);

        if (!empty($liveLinks)) {
            $parts[] = "=== Episode Links ===\n" . implode("\n", array_map(fn (string $url) => '- ' . $url, $liveLinks));
        }

        if ($youtubeUrl) {
            $parts[] = "=== YouTube Episode URL ===\n" . $youtubeUrl;
        }

        if (!empty($podcastAssets)) {
            $parts[] = "=== Podcast Assets ===\n" . implode("\n", array_map(fn (string $url) => '- ' . $url, $podcastAssets));
        }

        if (!empty($guestPages)) {
            foreach ($guestPages as $index => $guestPage) {
                $guestTitle = $this->displayTitle($guestPage);
                $guestProperties = $guestPage['properties'] ?? [];
                $parts[] = "=== Guest Profile " . ($index + 1) . ": {$guestTitle} ===\n" . $this->renderPropertyBlock($guestProperties, [
                    'Full Name', 'Name', 'Title / Job Title', 'Business/Company Name', 'Company Name', 'Company', 'Current Company', 'Official Website', 'Company Website URL',
                    'Biography (Short)', 'Biography (Full)', 'Description', 'Mission Statement', 'Occupations', 'Awards', 'Products and Services',
                    'Personal LinkedIn URL', 'Personal Twitter URL', 'Personal Instagram URL', 'Personal Facebook URL', 'Wikipedia URL',
                    'Primary Email', 'Public Email', 'Telephone', 'Location', 'Location of Birth', 'Country of Citizenship', 'Featured Image URL', 'Personal Photos'
                ]);
            }
        }

        $parts[] = "=== Canonical Link Targets ===\n"
            . 'Person Name: ' . ($linkTargets['person_name'] ?? '') . "\n"
            . 'Person URL: ' . ($linkTargets['person_url'] ?? '') . "\n"
            . 'Company Name: ' . ($linkTargets['company_name'] ?? '') . "\n"
            . 'Company URL: ' . ($linkTargets['company_url'] ?? '') . "\n"
            . 'Episode URL: ' . ($linkTargets['episode_url'] ?? '') . "\n"
            . 'YouTube URL: ' . ($linkTargets['youtube_url'] ?? '') . "\n"
            . 'YouTube Embed URL: ' . ($linkTargets['youtube_embed_url'] ?? '') . "\n"
            . 'Featured Image URL: ' . ($linkTargets['featured_image_url'] ?? '') . "\n"
            . 'Preferred Inline Guest Image URL: ' . ($linkTargets['inline_guest_image_url'] ?? '') . "\n"
            . 'Contact URL: ' . ($linkTargets['contact_url'] ?? '');

        $parts[] = "=== Required Structural Cues ===\n"
            . "- Publication: Hexa PR Wire\n"
            . "- Podcast: The Michael Peres Podcast\n"
            . "- Dateline format: {$details['location']} (Hexa PR Wire - {$details['date']}) - opening announcement paragraph. If date is blank, infer it from the episode schedule if possible.\n"
            . "- Sections after the intro body should include: About the guest, About Michael Peres, About The Michael Peres Podcast, Contact Information.\n"
            . "- If a YouTube URL or embed URL is provided, include one responsive YouTube embed iframe in the body.\n"
            . "- The first mention of the guest/person must be linked to the Person URL when provided.\n"
            . "- The first mention of the guest's company must be linked to the Company URL when provided.\n"
            . "- Use the Episode Featured Image URL as the featured image. Do not substitute a generic stock or search image when a real episode thumbnail is provided.\n"
            . "- Include exactly one inline guest/client image in the body when a preferred inline guest image URL is provided.\n"
            . "- Use the guest's actual organization, title, and biography from the related Notion Person record. Do not invent missing credentials.\n"
            . "- Preserve concrete discussion topics from the episode record and guest context.\n"
            . "- If supporting external URLs are clearly present above, use them. Otherwise do not invent or guess links.";

        if (!empty($missing)) {
            $parts[] = "=== Known Data Gaps ===\n" . implode("\n", array_map(fn (string $line) => '- ' . $line, $missing));
        }

        return trim(implode("\n\n", array_filter($parts)));
    }

    private function renderPropertyBlock(array $properties, array $preferredKeys = [], bool $includeRemaining = false): string
    {
        $lines = [];
        $seen = [];

        foreach ($preferredKeys as $key) {
            if (!array_key_exists($key, $properties)) {
                continue;
            }
            $rendered = $this->renderPropertyLine($key, $properties[$key]);
            if ($rendered !== null) {
                $lines[] = $rendered;
                $seen[$key] = true;
            }
        }

        if (!$includeRemaining) {
            return implode("\n", $lines);
        }

        foreach ($properties as $key => $value) {
            if (isset($seen[$key])) {
                continue;
            }
            if (in_array($key, ['created_time', 'last_edited_time', 'Guest', 'Podcast Interviews'], true)) {
                continue;
            }
            $rendered = $this->renderPropertyLine((string) $key, $value);
            if ($rendered !== null) {
                $lines[] = $rendered;
            }
        }

        return implode("\n", $lines);
    }

    private function renderPropertyLine(string $key, mixed $value): ?string
    {
        if (!$this->hasValue($value)) {
            return null;
        }

        if (is_array($value)) {
            $flat = [];
            foreach ($value as $item) {
                if (is_array($item)) {
                    foreach ($item as $nested) {
                        if ($this->hasValue($nested)) {
                            $flat[] = (string) $nested;
                        }
                    }
                } elseif ($this->hasValue($item)) {
                    $flat[] = (string) $item;
                }
            }
            $flat = array_values(array_unique(array_filter(array_map('trim', $flat))));
            $flat = array_values(array_filter($flat, fn (string $item) => !preg_match('/^\d+\s+linked record(?:s)?$/i', $item)));
            if (empty($flat)) {
                return null;
            }
            return $key . ': ' . implode(', ', $flat);
        }

        $value = trim((string) $value);
        if ($value === '' || preg_match('/^\d+\s+linked record(?:s)?$/i', $value)) {
            return null;
        }

        return $key . ': ' . $value;
    }

    private function buildDetectedPhotos(
        string $episodeTitle,
        array $episodeProperties,
        array $guestPages,
        array $podcastAssets,
        array $drivePhotos,
        string $episodeFeaturedImageUrl,
        string $inlineGuestImageUrl
    ): array {
        $candidates = [];

        if ($episodeFeaturedImageUrl !== '') {
            $candidates[] = [
                'url' => $episodeFeaturedImageUrl,
                'thumbnail_url' => $episodeFeaturedImageUrl,
                'alt_text' => $episodeTitle,
                'caption' => $episodeTitle,
                'source' => 'notion-episode-media',
                'source_label' => 'Notion Episode Thumbnail',
                'role' => 'featured',
                'download_url' => $episodeFeaturedImageUrl,
                'view_url' => $episodeFeaturedImageUrl,
            ];
        }

        foreach ($drivePhotos as $photo) {
            $url = trim((string) ($photo['url'] ?? ''));
            if ($url === '') {
                continue;
            }

            $role = $url === $episodeFeaturedImageUrl ? 'featured' : ($url === $inlineGuestImageUrl ? 'inline' : 'inline');
            $candidates[] = [
                'url' => $url,
                'thumbnail_url' => (string) ($photo['thumbnail_url'] ?? $url),
                'alt_text' => (string) ($photo['alt_text'] ?? $episodeTitle),
                'caption' => (string) ($photo['caption'] ?? ($role === 'featured' ? $episodeTitle : $episodeTitle . ' guest photo')),
                'source' => (string) ($photo['source'] ?? 'google-drive'),
                'source_label' => (string) ($photo['source_label'] ?? 'Google Drive Podcast Asset'),
                'role' => $role,
                'download_url' => (string) ($photo['download_url'] ?? $url),
                'view_url' => (string) ($photo['view_url'] ?? $url),
            ];
        }

        foreach ($podcastAssets as $url) {
            if (!$this->looksLikeRenderableImageUrl((string) $url)) {
                continue;
            }
            $candidates[] = [
                'url' => (string) $url,
                'thumbnail_url' => (string) $url,
                'alt_text' => $episodeTitle,
                'caption' => 'Podcast asset for ' . $episodeTitle,
                'source' => 'notion-podcast-assets',
                'source_label' => 'Notion Podcast Asset',
                'role' => ((string) $url === $episodeFeaturedImageUrl) ? 'featured' : 'inline',
                'download_url' => (string) $url,
                'view_url' => (string) $url,
            ];
        }

        foreach (($episodeProperties ?? []) as $key => $value) {
            if (!preg_match('/featured image|thumbnail|photo|image/i', (string) $key)) {
                continue;
            }
            foreach ($this->normalizeUrlList($value) as $url) {
                if (!$this->looksLikeRenderableImageUrl($url)) {
                    continue;
                }
                $candidates[] = [
                    'url' => $url,
                    'thumbnail_url' => $url,
                    'alt_text' => $episodeTitle,
                    'caption' => $episodeTitle,
                    'source' => 'notion-episode-media',
                    'source_label' => 'Notion Episode Media',
                    'role' => ($url === $episodeFeaturedImageUrl) ? 'featured' : 'inline',
                    'download_url' => $url,
                    'view_url' => $url,
                ];
            }
        }

        foreach ($guestPages as $guestPage) {
            $guestTitle = $this->displayTitle($guestPage);
            foreach (($guestPage['properties'] ?? []) as $key => $value) {
                if (!preg_match('/photo|image|headshot|portrait|thumbnail/i', (string) $key)) {
                    continue;
                }
                foreach ($this->normalizeUrlList($value) as $url) {
                    if (!$this->looksLikeRenderableImageUrl($url)) {
                        continue;
                    }
                    $candidates[] = [
                        'url' => $url,
                        'thumbnail_url' => $url,
                        'alt_text' => $guestTitle,
                        'caption' => $guestTitle . ' on The Michael Peres Podcast',
                        'source' => 'notion-guest-media',
                        'source_label' => 'Notion Guest Media',
                        'role' => ($url === $inlineGuestImageUrl) ? 'inline' : 'inline',
                        'download_url' => $url,
                        'view_url' => $url,
                    ];
                }
            }
        }

        $featured = [];
        $inline = [];
        $seen = [];
        foreach ($candidates as $candidate) {
            $url = trim((string) ($candidate['url'] ?? ''));
            if ($url === '' || isset($seen[$url])) {
                continue;
            }
            $seen[$url] = true;
            if (($candidate['role'] ?? '') === 'featured') {
                $featured[] = $candidate;
            } else {
                $inline[] = $candidate;
            }
        }

        return array_slice(array_merge($featured, $inline), 0, 12);
    }

    private function preferredContactUrl(array $guestPages, array $liveLinks): string
    {
        $guestPreferredFields = ['Official Website', 'Company Website URL', 'Personal LinkedIn URL', 'Wikipedia URL', 'Personal Twitter URL'];
        foreach ($guestPages as $guestPage) {
            $properties = $guestPage['properties'] ?? [];
            foreach ($guestPreferredFields as $field) {
                $value = $this->firstValue($properties, [$field]);
                $urls = $this->normalizeUrlList($value);
                if (!empty($urls)) {
                    return $urls[0];
                }
            }
        }

        return $liveLinks[0] ?? '';
    }

    private function guestBio(array $guestPages): string
    {
        foreach ($guestPages as $guestPage) {
            $properties = $guestPage['properties'] ?? [];
            $value = $this->firstValue($properties, ['Biography (Short)', 'Biography (Full)', 'Description', 'Mission Statement', 'Biography']);
            if ($this->hasValue($value)) {
                return trim((string) $value);
            }
        }

        return '';
    }

    private function guestNames(array $guestPages): string
    {
        $names = [];
        foreach ($guestPages as $guestPage) {
            $title = $this->displayTitle($guestPage);
            if ($title !== '') {
                $names[] = $title;
            }
        }

        return implode(', ', array_values(array_unique(array_filter($names))));
    }

    private function guestJobTitle(array $guestPages): string
    {
        foreach ($guestPages as $guestPage) {
            $properties = $guestPage['properties'] ?? [];
            $value = $this->firstValue($properties, ['Title / Job Title', 'Job Title', 'Position']);
            if ($this->hasValue($value)) {
                return trim((string) $value);
            }
        }

        return '';
    }

    private function displayTitle(array $page): string
    {
        return trim((string) ($this->notion->extractDisplayTitleFromParsedPage($page, 'Full Name') ?: $this->notion->extractDisplayTitleFromParsedPage($page, 'Name') ?: ''));
    }

    private function firstValue(array $properties, array $keys): mixed
    {
        foreach ($keys as $key) {
            if (!array_key_exists($key, $properties)) {
                continue;
            }
            $value = $properties[$key];
            if ($this->hasValue($value)) {
                return $value;
            }
        }

        return null;
    }

    private function hasValue(mixed $value): bool
    {
        if (is_array($value)) {
            foreach ($value as $item) {
                if ($this->hasValue($item)) {
                    return true;
                }
            }
            return false;
        }

        if ($value === null) {
            return false;
        }

        return trim((string) $value) !== '';
    }

    private function normalizeUrlList(mixed $value): array
    {
        $items = is_array($value) ? $value : preg_split('/[\s,]+/', (string) $value, -1, PREG_SPLIT_NO_EMPTY);
        $urls = [];
        foreach ($items ?: [] as $item) {
            $item = trim((string) $item);
            if ($item === '') {
                continue;
            }
            if (preg_match('/^www\./i', $item)) {
                $item = 'https://' . $item;
            }
            if (preg_match('/^https?:\/\//i', $item)) {
                $urls[] = rtrim($item, ".,);");
            }
        }

        return array_values(array_unique($urls));
    }

    private function resolveYoutubeUrl(array $episodeProperties, array $liveLinks): ?string
    {
        $explicit = $this->normalizeUrlList($this->firstValue($episodeProperties, ['YouTube URL', 'Youtube URL', 'YouTube']));
        $url = $this->firstYoutubeUrl($explicit);
        if ($url !== null) {
            return $url;
        }

        return $this->firstYoutubeUrl($liveLinks);
    }

    private function firstYoutubeUrl(array $urls): ?string
    {
        foreach ($urls as $url) {
            if (preg_match('/(?:youtube\.com|youtu\.be)/i', $url)) {
                return $url;
            }
        }

        return null;
    }

    private function youtubeEmbedUrl(?string $url): ?string
    {
        $url = trim((string) $url);
        if ($url === '') {
            return null;
        }

        $parts = parse_url($url);
        if (!is_array($parts)) {
            return null;
        }

        $host = strtolower((string) ($parts['host'] ?? ''));
        $path = trim((string) ($parts['path'] ?? ''), '/');
        parse_str((string) ($parts['query'] ?? ''), $query);

        $videoId = '';
        if ($host === 'youtu.be') {
            $videoId = $path;
        } elseif (str_contains($host, 'youtube.com')) {
            if ($path === 'watch') {
                $videoId = trim((string) ($query['v'] ?? ''));
            } elseif (str_starts_with($path, 'embed/')) {
                $videoId = trim(substr($path, 6));
            } elseif (str_starts_with($path, 'shorts/')) {
                $videoId = trim(substr($path, 7));
            }
        }

        $videoId = preg_replace('/[^A-Za-z0-9_-]/', '', (string) $videoId);
        if ($videoId === '') {
            return null;
        }

        return 'https://www.youtube.com/embed/' . $videoId;
    }

    private function preferredPersonUrl(array $guestPages): string
    {
        $fields = ['Personal LinkedIn URL', 'Wikipedia URL', 'Personal Twitter URL', 'Personal Instagram URL', 'YouTube URL', 'Official Website'];
        foreach ($guestPages as $guestPage) {
            $properties = $guestPage['properties'] ?? [];
            foreach ($fields as $field) {
                $urls = $this->normalizeUrlList($this->firstValue($properties, [$field]));
                if (!empty($urls)) {
                    return $urls[0];
                }
            }
        }

        return '';
    }

    private function guestCompanyName(array $guestPages): string
    {
        $fields = ['Business/Company Name', 'Company Name', 'Company', 'Current Company', 'Organization', 'Employer'];
        foreach ($guestPages as $guestPage) {
            $properties = $guestPage['properties'] ?? [];
            $value = $this->firstValue($properties, $fields);
            if ($this->hasValue($value)) {
                return trim((string) $value);
            }

            $jobTitle = trim((string) ($this->firstValue($properties, ['Title / Job Title', 'Job Title', 'Position']) ?: ''));
            if ($jobTitle !== '' && preg_match('/\b(?:founder|co-founder|ceo|president|owner|director|host)\s+(?:of|at)\s+(.+)$/iu', $jobTitle, $match)) {
                return trim($match[1], " 	
 ,.");
            }
        }

        return '';
    }

    private function preferredCompanyUrl(array $guestPages): string
    {
        $fields = ['Company Website URL', 'Official Website', 'Crunchbase URL', 'MuckRack URL', 'F6S URL'];
        foreach ($guestPages as $guestPage) {
            $properties = $guestPage['properties'] ?? [];
            foreach ($fields as $field) {
                $urls = $this->normalizeUrlList($this->firstValue($properties, [$field]));
                if (!empty($urls)) {
                    return $urls[0];
                }
            }
        }

        return '';
    }

    private function firstDriveFolderUrl(array $urls): string
    {
        foreach ($urls as $url) {
            $url = trim((string) $url);
            if ($url !== '' && str_contains($url, 'drive.google.com/drive/folders')) {
                return $url;
            }
        }

        foreach ($urls as $url) {
            $url = trim((string) $url);
            if ($url !== '' && str_contains($url, 'drive.google.com')) {
                return $url;
            }
        }

        return '';
    }

    private function fetchDrivePhotos(string $driveFolderUrl, string $guestName, string $episodeTitle): array
    {
        $driveFolderUrl = trim($driveFolderUrl);
        if ($driveFolderUrl === '') {
            return [];
        }

        try {
            $drive = app(GoogleDriveService::class);
            $reference = $drive->resolveFolderReference($driveFolderUrl);
            if (!$reference) {
                return [];
            }

            $result = $drive->listPhotos($reference['id'], true, $reference['resourceKey'] ?? null);
            if (!($result['success'] ?? false)) {
                return [];
            }

            $photos = [];
            foreach (($result['photos'] ?? []) as $photo) {
                if (!is_array($photo)) {
                    continue;
                }
                $url = trim((string) ($photo['web_content_link'] ?? $photo['thumbnail_link'] ?? $photo['web_view_link'] ?? ''));
                if ($url === '') {
                    continue;
                }

                $name = trim((string) ($photo['name'] ?? 'Podcast asset'));
                $alt = $guestName !== '' && stripos($name, $guestName) !== false ? $guestName : ($guestName !== '' ? $guestName . ' podcast asset' : $episodeTitle);
                $photos[] = [
                    'url' => $url,
                    'thumbnail_url' => (string) ($photo['thumbnail_link'] ?? $url),
                    'alt_text' => $alt,
                    'caption' => $guestName !== '' ? $guestName . ' on The Michael Peres Podcast' : ('Podcast asset for ' . $episodeTitle),
                    'source' => 'google-drive',
                    'source_label' => 'Google Drive Podcast Asset',
                    'download_url' => (string) ($photo['web_content_link'] ?? $url),
                    'view_url' => (string) ($photo['web_view_link'] ?? $url),
                    'filename' => $name,
                    'mime_type' => (string) ($photo['mime_type'] ?? ''),
                    'width' => $photo['width'] ?? null,
                    'height' => $photo['height'] ?? null,
                ];
            }

            return array_values($photos);
        } catch (\Throwable $e) {
            return [];
        }
    }

    private function preferredEpisodeFeaturedMedia(array $episodeProperties, array $podcastAssets, ?string $youtubeUrl): array
    {
        foreach (['Featured Image URL', 'Thumbnail', 'Image URL', 'Attachment URL', 'Additional Image URLs'] as $field) {
            foreach ($this->normalizeUrlList($this->firstValue($episodeProperties, [$field])) as $url) {
                if ($this->looksLikeRenderableImageUrl($url)) {
                    return [
                        'url' => $url,
                        'field' => $field,
                        'source_label' => 'Episode record',
                    ];
                }
            }
        }

        foreach ($podcastAssets as $url) {
            if ($this->looksLikeRenderableImageUrl((string) $url)) {
                return [
                    'url' => (string) $url,
                    'field' => 'Podcast Assets',
                    'source_label' => 'Podcast assets',
                ];
            }
        }

        $embedUrl = $this->youtubeEmbedUrl($youtubeUrl);
        if (is_string($embedUrl) && preg_match('~/embed/([A-Za-z0-9_-]+)$~', $embedUrl, $matches)) {
            return [
                'url' => 'https://img.youtube.com/vi/' . $matches[1] . '/hqdefault.jpg',
                'field' => 'YouTube URL',
                'source_label' => 'YouTube thumbnail fallback',
            ];
        }

        return ['url' => '', 'field' => '', 'source_label' => ''];
    }

    private function preferredInlineGuestImage(array $guestPages, array $drivePhotos, string $featuredImageUrl = ''): array
    {
        foreach ($guestPages as $guestPage) {
            foreach (($guestPage['properties'] ?? []) as $key => $value) {
                if (!preg_match('/photo|image|headshot|portrait|thumbnail/i', (string) $key)) {
                    continue;
                }
                foreach ($this->normalizeUrlList($value) as $url) {
                    if ($this->looksLikeRenderableImageUrl($url) && $url !== $featuredImageUrl) {
                        return [
                            'url' => $url,
                            'field' => (string) $key,
                            'source_label' => 'Linked Person record',
                        ];
                    }
                }
            }
        }

        foreach ($drivePhotos as $photo) {
            $url = trim((string) ($photo['url'] ?? ''));
            if ($url !== '' && $url !== $featuredImageUrl) {
                return [
                    'url' => $url,
                    'field' => 'Podcast Assets',
                    'source_label' => 'Google Drive fallback',
                ];
            }
        }

        return ['url' => '', 'field' => '', 'source_label' => ''];
    }

    private function buildSourceFields(
        array $episodeProperties,
        array $guestPages,
        array $linkTargets,
        array $episodeFeaturedImage,
        array $inlineGuestImage
    ): array {
        $guestProperties = $guestPages[0]['properties'] ?? [];

        return [
            'episode' => $this->extractSourceFieldEntries($episodeProperties, [
                'Name',
                'Schedule',
                'Short Summary',
                'Episode Summary',
                'Excerpt',
                'Episode Notes / Content',
                'Questions',
                'Submission Questions',
                'Guest Bio',
                'Additional Information',
            ]),
            'guest' => $this->extractSourceFieldEntries($guestProperties, [
                'Full Name',
                'Title / Job Title',
                'Business/Company Name',
                'Biography (Short)',
                'Biography (Full)',
                'Official Website',
                'Personal LinkedIn URL',
                'Company Website URL',
            ]),
            'enforcement' => array_values(array_filter([
                $this->sourceFieldEntry('Guest link target', $linkTargets['person_url'] ?? ''),
                $this->sourceFieldEntry('Company link target', $linkTargets['company_url'] ?? ''),
                $this->sourceFieldEntry('YouTube URL', $linkTargets['youtube_url'] ?? ''),
                $this->sourceFieldEntry(
                    'Episode featured image URL',
                    $linkTargets['featured_image_url'] ?? '',
                    (string) ($episodeFeaturedImage['field'] ?? '')
                ),
                $this->sourceFieldEntry(
                    'Inline guest image URL',
                    $linkTargets['inline_guest_image_url'] ?? '',
                    (string) ($inlineGuestImage['field'] ?? '')
                ),
            ])),
        ];
    }

    private function extractSourceFieldEntries(array $properties, array $fields): array
    {
        $entries = [];

        foreach ($fields as $field) {
            $value = $this->stringifyPropertyValue($properties[$field] ?? null);
            if ($value === '') {
                continue;
            }

            $entries[] = $this->sourceFieldEntry($field, $value);
        }

        return $entries;
    }

    private function sourceFieldEntry(string $field, string $value, string $sourceField = ''): ?array
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        return [
            'field' => $field,
            'value' => $value,
            'source_field' => trim($sourceField),
        ];
    }

    private function stringifyPropertyValue(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }

        if (is_scalar($value)) {
            return trim((string) $value);
        }

        if (is_array($value)) {
            $items = [];
            foreach ($value as $item) {
                $string = $this->stringifyPropertyValue($item);
                if ($string !== '') {
                    $items[] = $string;
                }
            }

            return implode("\n", array_values(array_unique($items)));
        }

        return '';
    }

    private function looksLikeRenderableImageUrl(string $url): bool
    {
        $url = strtolower(trim($url));
        if ($url === '') {
            return false;
        }
        if (str_contains($url, 'drive.google.com/drive/folders')) {
            return false;
        }
        if (preg_match('/\.(jpg|jpeg|png|webp|gif)(\?.*)?$/i', $url)) {
            return true;
        }

        return str_contains($url, 'googleusercontent.com')
            || str_contains($url, 'ytimg.com')
            || str_contains($url, '/wp-content/uploads/')
            || str_contains($url, 'cloudfront.net')
            || str_contains($url, 'cdn');
    }

    private function formatDateValue(mixed $value): string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }

        try {
            return \Carbon\Carbon::parse($value)->format('F j, Y');
        } catch (\Throwable $e) {
            return $value;
        }
    }
}
