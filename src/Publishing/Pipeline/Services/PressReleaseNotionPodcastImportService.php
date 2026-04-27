<?php

namespace hexa_app_publish\Publishing\Pipeline\Services;

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
                'message' => 'Podcast Interviews database is not configured in Notion.',
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
            $properties = $page['properties'] ?? [];
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

        $liveLinks = $this->normalizeUrlList($this->firstValue($episodeProperties, ['Live Link/s', 'Live Links', 'Live Link', 'Episode URL']));
        $youtubeUrl = $this->firstYoutubeUrl($liveLinks);
        $podcastAssets = $this->normalizeUrlList($episodeProperties['Podcast Assets'] ?? []);
        $guestName = $this->guestNames($guestPages);
        $episodeDate = $this->formatDateValue($this->firstValue($episodeProperties, ['Schedule', 'Publish Date', 'Date', 'Entry Creation Date']));
        $details = [
            'date' => $episodeDate ?: now()->format('F j, Y'),
            'location' => 'Miami, Florida',
            'contact' => $guestName !== '' ? $guestName : trim((string) ($this->firstValue($episodeProperties, ['Host', 'Guest']) ?: '')),
            'contact_url' => $this->preferredContactUrl($guestPages, $liveLinks),
        ];

        $missing = [];
        if (empty($guestIds)) {
            $missing[] = 'Guest relation missing on the selected episode.';
        }
        if (empty($liveLinks)) {
            $missing[] = 'Live Link/s missing on the selected episode.';
        }
        if (empty($podcastAssets)) {
            $missing[] = 'Podcast Assets missing on the selected episode.';
        }
        if ($episodeDate === '') {
            $missing[] = 'Schedule / publish date missing on the selected episode. Dateline will fall back to the current date unless you override it.';
        }
        if ($details['contact_url'] === '') {
            $missing[] = 'Guest website / contact URL missing.';
        }
        if ($this->guestBio($guestPages) === '') {
            $missing[] = 'Guest biography is thin or missing in the related People record.';
        }

        $sourceText = $this->buildSourceText($episodeTitle, $episodeProperties, $guestPages, $liveLinks, $podcastAssets, $youtubeUrl, $details, $missing);
        $detectedPhotos = $this->buildDetectedPhotos($episodeTitle, $episodeProperties, $guestPages, $podcastAssets);

        return [
            'success' => true,
            'message' => 'Podcast episode imported from Notion.',
            'source_text' => $sourceText,
            'preview' => Str::limit($sourceText, 1200, '...'),
            'label' => 'Notion Podcast Episode · ' . $episodeTitle,
            'details' => $details,
            'detected_photos' => $detectedPhotos,
            'selected_episode' => [
                'id' => $pageId,
                'title' => $episodeTitle,
                'schedule' => $details['date'],
                'guest' => $guestName,
                'live_links' => $liveLinks,
                'youtube_url' => $youtubeUrl,
                'podcast_assets' => $podcastAssets,
                'record_url' => $episodePage['url'] ?? null,
            ],
            'selected_guest' => [
                'name' => $guestName,
                'record_ids' => $guestIds,
                'record_url' => $guestPages[0]['url'] ?? null,
                'bio' => $this->guestBio($guestPages),
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

    private function buildSourceText(string $episodeTitle, array $episodeProperties, array $guestPages, array $liveLinks, array $podcastAssets, ?string $youtubeUrl, array $details, array $missing): string
    {
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
                    'Full Name', 'Name', 'Title / Job Title', 'Company', 'Current Company', 'Official Website', 'Company Website URL',
                    'Biography (Short)', 'Biography (Full)', 'Description', 'Mission Statement', 'Occupations', 'Awards', 'Products and Services',
                    'Personal LinkedIn URL', 'Personal Twitter URL', 'Personal Instagram URL', 'Personal Facebook URL', 'Wikipedia URL',
                    'Primary Email', 'Public Email', 'Telephone', 'Location', 'Location of Birth', 'Country of Citizenship', 'Featured Image URL', 'Personal Photos'
                ]);
            }
        }

        $parts[] = "=== Required Structural Cues ===\n"
            . "- Publication: Hexa PR Wire\n"
            . "- Podcast: The Michael Peres Podcast\n"
            . "- Dateline format: {$details['location']} (Hexa PR Wire - {$details['date']}) - opening announcement paragraph. If date is blank, infer it from the episode schedule if possible.\n"
            . "- Sections after the intro body should include: About the guest, About Michael Peres, About The Michael Peres Podcast, Contact Information.\n"
            . "- If a YouTube URL is provided, include one responsive YouTube embed iframe in the body using the embed URL form.\n"
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

    private function buildDetectedPhotos(string $episodeTitle, array $episodeProperties, array $guestPages, array $podcastAssets): array
    {
        $candidates = [];
        foreach ($podcastAssets as $url) {
            if (!$this->looksLikeRenderableImageUrl($url)) {
                continue;
            }
            $candidates[] = [
                'url' => $url,
                'thumbnail_url' => $url,
                'alt_text' => $episodeTitle,
                'caption' => 'Podcast asset for ' . $episodeTitle,
                'source' => 'notion-podcast-assets',
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
                    ];
                }
            }
        }

        $deduped = [];
        $seen = [];
        foreach ($candidates as $candidate) {
            $url = trim((string) ($candidate['url'] ?? ''));
            if ($url === '' || isset($seen[$url])) {
                continue;
            }
            $seen[$url] = true;
            $deduped[] = $candidate;
        }

        return array_slice($deduped, 0, 12);
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
                $urls[] = $item;
            }
        }

        return array_values(array_unique($urls));
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
        return str_contains($url, 'googleusercontent.com') || str_contains($url, 'ytimg.com') || str_contains($url, 'cdn');
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
