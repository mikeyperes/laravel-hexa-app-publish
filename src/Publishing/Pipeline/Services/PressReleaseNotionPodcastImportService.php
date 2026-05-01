<?php

namespace hexa_app_publish\Publishing\Pipeline\Services;

use hexa_package_google_drive\Services\GoogleDriveService;
use hexa_package_notion\Services\NotionService;
use Illuminate\Support\Facades\Http;
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
        $transcriptIntro = $this->youtubeTranscriptIntro($youtubeUrl, 300);
        $podcastAssets = $this->normalizeUrlList($episodeProperties['Podcast Assets'] ?? []);
        $guestName = $this->guestNames($guestPages);
        $personUrl = $this->preferredPersonUrl($guestPages);
        $companyName = $this->guestCompanyName($guestPages);
        $companyUrl = $this->preferredCompanyUrl($guestPages);
        $driveFolderUrl = $this->firstDriveFolderUrl($podcastAssets);
        $drivePhotos = $this->fetchDrivePhotos($driveFolderUrl, $guestName, $episodeTitle, 'Google Drive Episode Asset', 'notion-episode-drive');
        $guestDriveFolderUrl = $this->firstGuestDriveFolderUrl($guestPages);
        $guestDrivePhotos = $this->fetchDrivePhotos($guestDriveFolderUrl, $guestName, $episodeTitle, 'Linked Person Drive Photo', 'notion-guest-drive');
        $episodeFeaturedImage = $this->preferredEpisodeFeaturedMedia($episodeProperties, $podcastAssets, $drivePhotos, $youtubeUrl);
        $episodeFeaturedImageUrl = (string) ($episodeFeaturedImage['url'] ?? '');
        $inlineGuestImage = $this->preferredInlineGuestImage($guestPages, $guestDrivePhotos, $drivePhotos, $episodeFeaturedImageUrl);
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
            $missing[] = 'No inline guest/client image could be resolved from the linked People record or podcast assets.';
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
            $linkTargets,
            $transcriptIntro
        );
        $detectedPhotos = $this->buildDetectedPhotos(
            $episodeTitle,
            $episodeProperties,
            $guestPages,
            $podcastAssets,
            $guestDrivePhotos,
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
                $inlineGuestImage,
                $transcriptIntro
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
                'transcript_intro' => (string) ($transcriptIntro['text'] ?? ''),
                'transcript_language' => (string) ($transcriptIntro['language'] ?? ''),
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
                'drive_folder_url' => $guestDriveFolderUrl,
                'drive_photo_count' => count($guestDrivePhotos),
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
        array $linkTargets,
        array $transcriptIntro = []
    ): string {
        $parts = [];
        $parts[] = "=== Podcast Press Release Mission ===
This is a Hexa PR Wire press release for a Michael Peres Podcast episode. Lead with the guest's real background, role, company, and why the appearance matters. Use the guest biography before the episode discussion. Keep the episode summary conservative and grounded in the source material below. Do not overclaim what was discussed. If the transcript, notes, or episode summary do not explicitly support a talking point, do not invent it.";

        if (!empty($guestPages)) {
            foreach ($guestPages as $index => $guestPage) {
                $guestTitle = $this->displayTitle($guestPage);
                $guestProperties = $guestPage['properties'] ?? [];
                $parts[] = "=== Guest Profile " . ($index + 1) . ": {$guestTitle} ===
" . $this->renderPropertyBlock($guestProperties, [
                    'Full Name', 'Name', 'Title / Job Title', 'Business/Company Name', 'Company Name', 'Company', 'Current Company', 'Official Website', 'Company Website URL',
                    'Biography (Short)', 'Biography (Full)', 'Description', 'Mission Statement', 'Occupations', 'Awards', 'Products and Services',
                    'Personal LinkedIn URL', 'Personal Twitter URL', 'Personal Instagram URL', 'Personal Facebook URL', 'Wikipedia URL',
                    'Primary Email', 'Public Email', 'Telephone', 'Location', 'Location of Birth', 'Country of Citizenship', 'Featured Image URL', 'Personal Photos'
                ]);
            }
        }

        $parts[] = "=== Episode Record ===
" . $this->renderPropertyBlock($episodeProperties, [
            'Status', 'Schedule', 'Name', 'Podcast Name', 'Season', 'Episode Number', 'Duration', 'Short Summary', 'Episode Summary', 'Excerpt',
            'Episode Notes / Content', 'Questions', 'Submission Questions', 'Topics to avoid', 'Episode Categories', 'Episode Tags',
            'Guest Bio', 'Additional Information',
            'Live Link/s', 'Website Episode URL', 'Website Episode Slug', 'YouTube URL', 'Spotify Episode URL', 'Apple Podcast Episode URL',
            'RSS URL', 'Press Release Links', 'Additional Resources', 'Featured Image URL', 'Thumbnail', 'Photos',
            'Additional Image URLs', 'Podcast Assets'
        ]);

        if (!empty($liveLinks)) {
            $parts[] = "=== Episode Links ===
" . implode("
", array_map(fn (string $url) => '- ' . $url, $liveLinks));
        }

        if ($youtubeUrl) {
            $parts[] = "=== YouTube Episode URL ===
" . $youtubeUrl;
        }

        if (($transcriptIntro['text'] ?? '') !== '') {
            $label = '=== Transcript Intro (First 5 Minutes';
            if (($transcriptIntro['language'] ?? '') !== '') {
                $label .= ' · ' . $transcriptIntro['language'];
            }
            $label .= ') ===';
            $parts[] = $label . "
" . $transcriptIntro['text'];
        }

        if (!empty($podcastAssets)) {
            $parts[] = "=== Podcast Assets ===
" . implode("
", array_map(fn (string $url) => '- ' . $url, $podcastAssets));
        }

        $parts[] = "=== Canonical Link Targets ===
"
            . 'Person Name: ' . ($linkTargets['person_name'] ?? '') . "
"
            . 'Person URL: ' . ($linkTargets['person_url'] ?? '') . "
"
            . 'Company Name: ' . ($linkTargets['company_name'] ?? '') . "
"
            . 'Company URL: ' . ($linkTargets['company_url'] ?? '') . "
"
            . 'Episode URL: ' . ($linkTargets['episode_url'] ?? '') . "
"
            . 'YouTube URL: ' . ($linkTargets['youtube_url'] ?? '') . "
"
            . 'YouTube Embed URL: ' . ($linkTargets['youtube_embed_url'] ?? '') . "
"
            . 'Featured Image URL: ' . ($linkTargets['featured_image_url'] ?? '') . "
"
            . 'Preferred Inline Guest Image URL: ' . ($linkTargets['inline_guest_image_url'] ?? '') . "
"
            . 'Contact URL: ' . ($linkTargets['contact_url'] ?? '');

        $parts[] = "=== Required Structural Cues ===
"
            . "- Publication: Hexa PR Wire
"
            . "- Podcast: The Michael Peres Podcast
"
            . "- Use this exact dateline verbatim at the start of the first paragraph: {$details['location']} (Hexa PR Wire - {$details['date']}) -
"
            . "- Never substitute another city, state, or date. Ignore any other dateline, city, state, or publication date found in transcripts, notes, source material, or prior examples.
"
            . "- Structure the opening so the guest's background, credentials, company, and relevance come before the episode recap.
"
            . "- Sections after the intro body should include: About the guest, About Michael Peres, About The Michael Peres Podcast, Contact Information.
"
            . "- If a YouTube URL or embed URL is provided, include one responsive YouTube embed iframe in the body.
"
            . "- The first mention of the guest/person must be linked to the Person URL when provided.
"
            . "- The first mention of the guest's company must be linked to the Company URL when provided.
"
            . "- Use the Episode Featured Image URL as the featured image. Do not substitute a generic stock or search image when a real episode thumbnail is provided.
"
            . "- Include exactly one inline guest/client image in the body when a preferred inline guest image URL is provided.
"
            . "- Use the guest's actual organization, title, and biography from the related Notion Person record. Do not invent missing credentials.
"
            . "- Use the transcript intro, episode notes, questions, and summary only as grounded support. If they do not explicitly show that a topic was discussed, do not claim it was discussed.
"
            . "- Keep the summary factual and restrained. Avoid sensational or inflated language.
"
            . "- If supporting external URLs are clearly present above, use them. Otherwise do not invent or guess links.";

        if (!empty($missing)) {
            $parts[] = "=== Known Data Gaps ===
" . implode("
", array_map(fn (string $line) => '- ' . $line, $missing));
        }

        return trim(implode("

", array_filter($parts)));
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
        array $guestDrivePhotos,
        array $drivePhotos,
        string $episodeFeaturedImageUrl,
        string $inlineGuestImageUrl
    ): array {
        $candidates = [];
        $guestTitle = $this->guestNames($guestPages) ?: 'Guest';

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

        foreach ($guestDrivePhotos as $photo) {
            $url = trim((string) ($photo['url'] ?? ''));
            if ($url === '') {
                continue;
            }

            $candidates[] = [
                'url' => $url,
                'thumbnail_url' => (string) ($photo['thumbnail_url'] ?? $url),
                'alt_text' => (string) ($photo['alt_text'] ?? $this->simpleGuestPhotoAlt($guestTitle)),
                'caption' => (string) ($photo['caption'] ?? $this->simpleGuestPhotoCaption($guestTitle)),
                'source' => (string) ($photo['source'] ?? 'notion-guest-drive'),
                'source_label' => (string) ($photo['source_label'] ?? 'Linked Person Drive Photo'),
                'role' => 'inline',
                'download_url' => (string) ($photo['download_url'] ?? $url),
                'view_url' => (string) ($photo['view_url'] ?? $url),
            ];
        }

        foreach ($drivePhotos as $photo) {
            $url = trim((string) ($photo['url'] ?? ''));
            if ($url === '') {
                continue;
            }

            $role = $url === $episodeFeaturedImageUrl ? 'featured' : 'inline';
            $candidates[] = [
                'url' => $url,
                'thumbnail_url' => (string) ($photo['thumbnail_url'] ?? $url),
                'alt_text' => (string) ($photo['alt_text'] ?? ($role === 'featured' ? $episodeTitle : $this->simpleGuestPhotoAlt($guestTitle))),
                'caption' => (string) ($photo['caption'] ?? ($role === 'featured' ? $episodeTitle : $this->simpleGuestPhotoCaption($guestTitle))),
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
            $role = ((string) $url === $episodeFeaturedImageUrl) ? 'featured' : 'inline';
            $candidates[] = [
                'url' => (string) $url,
                'thumbnail_url' => (string) $url,
                'alt_text' => $role === 'featured' ? $episodeTitle : $this->simpleGuestPhotoAlt($guestTitle),
                'caption' => $role === 'featured' ? $episodeTitle : $this->simpleGuestPhotoCaption($guestTitle),
                'source' => 'notion-podcast-assets',
                'source_label' => 'Notion Podcast Asset',
                'role' => $role,
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
                $role = ($url === $episodeFeaturedImageUrl) ? 'featured' : 'inline';
                $candidates[] = [
                    'url' => $url,
                    'thumbnail_url' => $url,
                    'alt_text' => $role === 'featured' ? $episodeTitle : $this->simpleGuestPhotoAlt($guestTitle),
                    'caption' => $role === 'featured' ? $episodeTitle : $this->simpleGuestPhotoCaption($guestTitle),
                    'source' => 'notion-episode-media',
                    'source_label' => 'Notion Episode Media',
                    'role' => $role,
                    'download_url' => $url,
                    'view_url' => $url,
                ];
            }
        }

        if (empty($guestDrivePhotos)) {
            foreach ($guestPages as $guestPage) {
                $guestTitle = $this->displayTitle($guestPage) ?: $guestTitle;
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
                            'alt_text' => $this->simpleGuestPhotoAlt($guestTitle),
                            'caption' => $this->simpleGuestPhotoCaption($guestTitle),
                            'source' => 'notion-guest-media',
                            'source_label' => 'Notion Guest Media',
                            'role' => 'inline',
                            'download_url' => $url,
                            'view_url' => $url,
                        ];
                    }
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

    private function simpleGuestPhotoCaption(string $guestName, string $fallback = ''): string
    {
        $guestName = trim($guestName);
        if ($guestName !== '') {
            return $guestName;
        }

        $fallback = trim(pathinfo($fallback, PATHINFO_FILENAME));
        if ($fallback !== '') {
            return trim(preg_replace('/[_-]+/', ' ', $fallback));
        }

        return 'Guest photo';
    }

    private function simpleGuestPhotoAlt(string $guestName, string $fallback = ''): string
    {
        return $this->simpleGuestPhotoCaption($guestName, $fallback);
    }

    private function youtubeTranscriptIntro(?string $url, int $maxSeconds = 300): array
    {
        $url = trim((string) $url);
        if ($url === '') {
            return ['text' => '', 'language' => '', 'source_url' => ''];
        }

        try {
            $response = Http::timeout(20)
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0 Safari/537.36',
                    'Accept-Language' => 'en-US,en;q=0.9',
                ])
                ->get($url);
            if (!$response->successful()) {
                return ['text' => '', 'language' => '', 'source_url' => ''];
            }

            $html = $response->body();
            if (!preg_match('/"captionTracks":(\[[^\]]+\])/s', $html, $matches)) {
                return ['text' => '', 'language' => '', 'source_url' => ''];
            }

            $tracks = json_decode($matches[1], true);
            if (!is_array($tracks) || empty($tracks)) {
                return ['text' => '', 'language' => '', 'source_url' => ''];
            }

            usort($tracks, function (array $a, array $b) {
                $rank = function (array $track): int {
                    $lang = strtolower((string) ($track['languageCode'] ?? ''));
                    if (in_array($lang, ['en', 'en-us', 'en-gb'], true)) {
                        return 0;
                    }
                    if (str_contains($lang, 'en')) {
                        return 1;
                    }
                    return 2;
                };
                return $rank($a) <=> $rank($b);
            });

            $track = $tracks[0] ?? [];
            $baseUrl = html_entity_decode((string) ($track['baseUrl'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if ($baseUrl === '') {
                return ['text' => '', 'language' => '', 'source_url' => ''];
            }

            $captionUrl = str_contains($baseUrl, 'fmt=') ? $baseUrl : $baseUrl . '&fmt=json3';
            $captionResponse = Http::timeout(20)
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0 Safari/537.36',
                    'Accept-Language' => 'en-US,en;q=0.9',
                ])
                ->get($captionUrl);
            if (!$captionResponse->successful()) {
                return ['text' => '', 'language' => '', 'source_url' => $captionUrl];
            }

            $payload = $captionResponse->json();
            if (!is_array($payload)) {
                return ['text' => '', 'language' => '', 'source_url' => $captionUrl];
            }

            $parts = [];
            $maxMs = max(1, $maxSeconds) * 1000;
            foreach (($payload['events'] ?? []) as $event) {
                if (!is_array($event)) {
                    continue;
                }
                $startMs = (int) ($event['tStartMs'] ?? 0);
                if ($startMs >= $maxMs) {
                    break;
                }
                $segmentText = '';
                foreach (($event['segs'] ?? []) as $segment) {
                    if (!is_array($segment)) {
                        continue;
                    }
                    $segmentText .= (string) ($segment['utf8'] ?? '');
                }
                $segmentText = html_entity_decode($segmentText, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $segmentText = trim(preg_replace('/\s+/u', ' ', $segmentText));
                if ($segmentText !== '') {
                    $parts[] = $segmentText;
                }
            }

            return [
                'text' => trim(preg_replace('/\s+/u', ' ', implode(' ', $parts))),
                'language' => (string) (($track['name']['simpleText'] ?? ($track['languageCode'] ?? '')) ?: ''),
                'source_url' => $captionUrl,
            ];
        } catch (\Throwable $e) {
            return ['text' => '', 'language' => '', 'source_url' => ''];
        }
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

        $videoId = $this->firstYoutubeVideoId($this->firstValue($episodeProperties, ['YouTube Video ID', 'urls_youtube']));
        if ($videoId !== null) {
            return 'https://www.youtube.com/watch?v=' . $videoId;
        }

        return $this->firstYoutubeUrl($liveLinks);
    }

    private function firstYoutubeVideoId(mixed $value): ?string
    {
        $items = is_array($value) ? $value : preg_split('/[\s,]+/', trim((string) $value), -1, PREG_SPLIT_NO_EMPTY);
        foreach ($items ?: [] as $item) {
            $candidate = preg_replace('/[^A-Za-z0-9_-]/', '', (string) $item);
            if ($candidate !== '' && strlen($candidate) >= 6) {
                return $candidate;
            }
        }

        return null;
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

    private function firstGuestDriveFolderUrl(array $guestPages): string
    {
        foreach ($guestPages as $guestPage) {
            foreach (($guestPage['properties'] ?? []) as $key => $value) {
                if (!$this->looksLikeGuestDriveProperty((string) $key)) {
                    continue;
                }

                foreach ($this->normalizeUrlList($value) as $url) {
                    if ($this->looksLikeGoogleDriveFolderUrl($url)) {
                        return $url;
                    }
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

    private function fetchDrivePhotos(string $driveFolderUrl, string $guestName, string $episodeTitle, string $sourceLabel = 'Google Drive Podcast Asset', string $source = 'google-drive'): array
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

                $name = trim((string) ($photo['name'] ?? 'Guest photo'));
                $photos[] = [
                    'url' => $url,
                    'thumbnail_url' => (string) ($photo['thumbnail_link'] ?? $url),
                    'alt_text' => $this->simpleGuestPhotoAlt($guestName, $name),
                    'caption' => $this->simpleGuestPhotoCaption($guestName, $name),
                    'source' => $source,
                    'source_label' => $sourceLabel,
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

    private function preferredEpisodeFeaturedMedia(array $episodeProperties, array $podcastAssets, array $drivePhotos, ?string $youtubeUrl): array
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

        foreach ($drivePhotos as $photo) {
            $url = trim((string) ($photo['url'] ?? ''));
            if ($url !== '') {
                return [
                    'url' => $url,
                    'field' => 'Podcast Assets',
                    'source_label' => 'Google Drive episode asset',
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

    private function preferredInlineGuestImage(array $guestPages, array $guestDrivePhotos, array $drivePhotos, string $featuredImageUrl = ''): array
    {
        foreach ($guestDrivePhotos as $photo) {
            $url = trim((string) ($photo['url'] ?? ''));
            if ($url !== '' && $url !== $featuredImageUrl) {
                return [
                    'url' => $url,
                    'field' => 'Personal Photos',
                    'source_label' => 'Linked Person Drive Photo',
                ];
            }
        }

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
        array $inlineGuestImage,
        array $transcriptIntro = []
    ): array {
        $guestProperties = $guestPages[0]['properties'] ?? [];
        $episodeRows = $this->extractSourceFieldEntries($episodeProperties, [
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
        ], 'Podcast Episode Database');

        if (($transcriptIntro['text'] ?? '') !== '') {
            $episodeRows[] = $this->sourceFieldEntry(
                'Transcript intro (first 5 minutes)',
                (string) ($transcriptIntro['text'] ?? ''),
                'YouTube captions',
                'YouTube Transcript'
            );
        }
        if (($transcriptIntro['language'] ?? '') !== '') {
            $episodeRows[] = $this->sourceFieldEntry(
                'Transcript language',
                (string) ($transcriptIntro['language'] ?? ''),
                'Caption track language',
                'YouTube Transcript'
            );
        }

        return [
            'episode' => array_values(array_filter($episodeRows)),
            'guest' => $this->extractSourceFieldEntries($guestProperties, [
                'Full Name',
                'Title / Job Title',
                'Business/Company Name',
                'Biography (Short)',
                'Biography (Full)',
                'Official Website',
                'Personal LinkedIn URL',
                'Company Website URL',
            ], 'Person Database'),
            'enforcement' => array_values(array_filter([
                $this->sourceFieldEntry('Guest link target', $linkTargets['person_url'] ?? '', 'Person URL', 'Canonical Targets'),
                $this->sourceFieldEntry('Company link target', $linkTargets['company_url'] ?? '', 'Company URL', 'Canonical Targets'),
                $this->sourceFieldEntry('YouTube URL', $linkTargets['youtube_url'] ?? '', 'YouTube URL', 'Canonical Targets'),
                $this->sourceFieldEntry(
                    'Episode featured image URL',
                    $linkTargets['featured_image_url'] ?? '',
                    (string) ($episodeFeaturedImage['field'] ?? ''),
                    'Podcast Episode Database'
                ),
                $this->sourceFieldEntry(
                    'Inline guest image URL',
                    $linkTargets['inline_guest_image_url'] ?? '',
                    (string) ($inlineGuestImage['field'] ?? ''),
                    str_contains((string) ($inlineGuestImage['source_label'] ?? ''), 'Person') ? 'Person Database' : 'Podcast Episode Database'
                ),
            ])),
        ];
    }

    private function extractSourceFieldEntries(array $properties, array $fields, string $sourceTable = ''): array
    {
        $entries = [];

        foreach ($fields as $field) {
            $value = $this->stringifyPropertyValue($properties[$field] ?? null);
            if ($value === '') {
                continue;
            }

            $entries[] = $this->sourceFieldEntry($field, $value, $field, $sourceTable);
        }

        return $entries;
    }

    private function sourceFieldEntry(string $field, string $value, string $sourceField = '', string $sourceTable = ''): ?array
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        return [
            'field' => $field,
            'value' => $value,
            'source_field' => trim($sourceField),
            'source_table' => trim($sourceTable),
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
        if ($this->looksLikeGoogleDriveFolderUrl($url)) {
            return false;
        }
        if (preg_match('/\.(jpg|jpeg|png|webp|gif)(\?.*)?$/i', $url)) {
            return true;
        }

        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        if ($host === 'media.licdn.com' || str_ends_with($host, '.licdn.com')) {
            return false;
        }

        return str_contains($url, 'googleusercontent.com')
            || str_contains($url, 'ytimg.com')
            || str_contains($url, '/wp-content/uploads/')
            || str_contains($url, 'cloudfront.net')
            || str_contains($url, 'cdn');
    }

    private function looksLikeGoogleDriveFolderUrl(string $url): bool
    {
        $url = trim($url);
        if ($url === '') {
            return false;
        }

        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $path = strtolower((string) parse_url($url, PHP_URL_PATH));

        if (!in_array($host, ['drive.google.com', 'docs.google.com'], true)) {
            return false;
        }

        return str_contains($path, '/folders/') || str_contains($path, '/drive/folders/');
    }

    private function looksLikeGuestDriveProperty(string $key): bool
    {
        $normalized = strtolower($key);

        return str_contains($normalized, 'drive')
            || str_contains($normalized, 'folder')
            || str_contains($normalized, 'photo')
            || str_contains($normalized, 'image')
            || str_contains($normalized, 'gallery')
            || str_contains($normalized, 'headshot');
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
