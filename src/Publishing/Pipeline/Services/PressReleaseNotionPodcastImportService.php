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
        $episodeRawProperties = (array) ($episodeRaw['page']['properties'] ?? []);
        $guestIds = $this->relatedPageIds($episodeRawProperties, $this->guestRelationProperty());
        $hostIds = $this->relatedPageIds($episodeRawProperties, $this->hostRelationProperty());
        $podcastIds = $this->relatedPageIds($episodeRawProperties, $this->podcastRelationProperty());

        $guestPages = $this->loadParsedPages($guestIds);
        $hostPages = $this->loadParsedPages($hostIds);
        $podcastPages = $this->loadParsedPages($podcastIds);

        $liveLinks = $this->normalizeUrlList($this->firstValue($episodeProperties, ['Live Link/s', 'Live Links', 'Live Link', 'Episode URL', 'Website Episode URL']));
        $youtubeUrl = $this->resolveYoutubeUrl($episodeProperties, $liveLinks);
        $youtubeEmbedUrl = $this->youtubeEmbedUrl($youtubeUrl);
        $transcriptIntro = $this->youtubeTranscriptIntro($youtubeUrl, 300);
        $podcastAssets = $this->normalizeUrlList($episodeProperties['Podcast Assets'] ?? []);
        $guestName = $this->guestNames($guestPages);
        $hostName = $this->guestNames($hostPages);
        $podcastName = $this->podcastName($podcastPages, $episodeProperties);
        $personUrl = $this->preferredPersonUrl($guestPages);
        $hostUrl = $this->preferredPersonUrl($hostPages);
        $companyName = $this->guestCompanyName($guestPages);
        $companyUrl = $this->preferredCompanyUrl($guestPages);
        $guestEmail = $this->preferredEmail($guestPages);
        $hostEmail = $this->preferredEmail($hostPages);
        $podcastUrl = $this->preferredPodcastUrl($podcastPages, $episodeProperties, $liveLinks);
        $episodeDriveMeta = [
            'url' => $this->firstDriveFolderUrl($podcastAssets),
            'field' => 'Podcast Assets',
        ];
        $drivePhotos = $this->fetchDrivePhotos($episodeDriveMeta, $guestName, $episodeTitle, 'Google Drive folder media', 'notion-episode-drive');
        $guestDriveMeta = $this->firstGuestDriveFolderMeta($guestPages);
        $guestDrivePhotos = $this->fetchDrivePhotos($guestDriveMeta, $guestName, $episodeTitle, 'Google Drive folder media', 'notion-guest-drive');
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
            'contact_email' => $guestEmail,
        ];
        $linkTargets = [
            'person_name' => $guestName,
            'person_url' => $personUrl,
            'host_name' => $hostName,
            'host_url' => $hostUrl,
            'company_name' => $companyName,
            'company_url' => $companyUrl,
            'podcast_name' => $podcastName,
            'podcast_url' => $podcastUrl,
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
        if (empty($hostIds)) {
            $missing[] = 'Host relation missing on the selected episode.';
        }
        if (empty($podcastIds)) {
            $missing[] = 'Podcast relation missing on the selected episode.';
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
        if (($episodeDriveMeta['url'] ?? '') === '') {
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
        if ($hostName === '' || $hostUrl === '') {
            $missing[] = 'Host person name or public URL missing in the linked Host record.';
        }
        if ($podcastName === '' || $podcastUrl === '') {
            $missing[] = 'Podcast name or canonical public URL missing in the linked Podcast record.';
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
            $hostPages,
            $podcastPages,
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
                $hostPages,
                $podcastPages,
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
                'host' => $hostName,
                'podcast' => $podcastName,
                'guest_record_ids' => $guestIds,
                'host_record_ids' => $hostIds,
                'podcast_record_ids' => $podcastIds,
                'live_links' => $liveLinks,
                'youtube_url' => $youtubeUrl,
                'youtube_embed_url' => $youtubeEmbedUrl,
                'podcast_assets' => $podcastAssets,
                'drive_folder_url' => $episodeDriveMeta['url'] ?? '',
                'drive_folder_field' => $episodeDriveMeta['field'] ?? '',
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
                'email' => $guestEmail,
                'inline_photo_url' => $inlineGuestImageUrl,
                'inline_photo_source_field' => (string) ($inlineGuestImage['field'] ?? ''),
                'drive_folder_url' => $guestDriveMeta['url'] ?? '',
                'drive_folder_field' => $guestDriveMeta['field'] ?? '',
                'drive_photo_count' => count($guestDrivePhotos),
                'job_title' => $this->guestJobTitle($guestPages),
            ],
            'selected_host' => [
                'name' => $hostName,
                'record_ids' => $hostIds,
                'record_url' => $hostPages[0]['url'] ?? null,
                'bio' => $this->guestBio($hostPages),
                'person_url' => $hostUrl,
                'email' => $hostEmail,
                'job_title' => $this->guestJobTitle($hostPages),
                'featured_image_url' => $this->preferredProfileImageUrl($hostPages),
            ],
            'selected_podcast' => [
                'name' => $podcastName,
                'record_ids' => $podcastIds,
                'record_url' => $podcastPages[0]['url'] ?? null,
                'podcast_url' => $podcastUrl,
                'description' => $this->podcastDescription($podcastPages),
                'website_content_url' => $this->preferredUrlFromPages($podcastPages, ['Website Content']),
                'logo_url' => $this->preferredUrlFromPages($podcastPages, ['Logo URL']),
                'youtube_channel_url' => $this->preferredUrlFromPages($podcastPages, ['YouTube Channel URL']),
                'apple_podcast_url' => $this->preferredUrlFromPages($podcastPages, ['Apple Podcast URL']),
                'spotify_podcast_url' => $this->preferredUrlFromPages($podcastPages, ['Spotify Podcast URL', 'Spotify URL']),
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

    private function hostRelationProperty(): string
    {
        return 'Host';
    }

    private function podcastRelationProperty(): string
    {
        return 'Podcast';
    }

    private function relatedPageIds(array $rawProperties, string $propertyName): array
    {
        return array_values(array_filter(array_map(
            fn ($relation) => is_array($relation) ? (string) ($relation['id'] ?? '') : '',
            (array) (($rawProperties[$propertyName]['relation'] ?? []) ?: [])
        )));
    }

    private function loadParsedPages(array $pageIds): array
    {
        $pages = [];

        foreach ($pageIds as $pageId) {
            $parsed = $this->notion->getPage((string) $pageId);
            if ($parsed['success'] ?? false) {
                $pages[] = $parsed['page'] ?? [];
            }
        }

        return $pages;
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
        array $hostPages,
        array $podcastPages,
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
Write a neutral third-person podcast press release for Hexa PR Wire about a Michael Peres Podcast episode. Lead with the guest's real background, role, company, and why the appearance is relevant. Use the guest biography as pre-episode context before the episode discussion. Keep the episode summary conservative, grounded in the source material below, and never imply that biography details were discussed on-air unless the notes or transcript explicitly support that.";

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

        if (!empty($hostPages)) {
            foreach ($hostPages as $index => $hostPage) {
                $hostTitle = $this->displayTitle($hostPage);
                $hostProperties = $hostPage['properties'] ?? [];
                $parts[] = "=== Host Profile " . ($index + 1) . ": {$hostTitle} ===
" . $this->renderPropertyBlock($hostProperties, [
                    'Full Name', 'Name', 'Title / Job Title', 'Biography (Short)', 'Biography (Full)', 'Description', 'Mission Statement',
                    'Official Website', 'Podcast Profile URL', 'Personal LinkedIn URL', 'Personal Twitter URL', 'Personal Instagram URL',
                    'Personal Facebook URL', 'Wikipedia URL', 'Featured Image URL', 'Personal Photos'
                ]);
            }
        }

        if (!empty($podcastPages)) {
            foreach ($podcastPages as $index => $podcastPage) {
                $podcastTitle = $this->podcastName([$podcastPage], $episodeProperties);
                $podcastProperties = $podcastPage['properties'] ?? [];
                $parts[] = "=== Podcast Record " . ($index + 1) . ": {$podcastTitle} ===
" . $this->renderPropertyBlock($podcastProperties, [
                    'Business/Company Name', 'Name', 'Description', 'Podcast URL', 'Website Content', 'YouTube Channel URL',
                    'Apple Podcast URL', 'Spotify Podcast URL', 'Spotify URL', 'Amazon Podcast URL', 'Google Podcast URL',
                    'LinkedIn URL', 'Facebook URL', 'Logo URL', 'Tunein Podcast URL', 'Listen Notes URL'
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
            . 'Host Name: ' . ($linkTargets['host_name'] ?? '') . "
"
            . 'Host URL: ' . ($linkTargets['host_url'] ?? '') . "
"
            . 'Company Name: ' . ($linkTargets['company_name'] ?? '') . "
"
            . 'Company URL: ' . ($linkTargets['company_url'] ?? '') . "
"
            . 'Podcast Name: ' . ($linkTargets['podcast_name'] ?? '') . "
"
            . 'Podcast URL: ' . ($linkTargets['podcast_url'] ?? '') . "
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
            . "- Podcast: " . (($linkTargets['podcast_name'] ?? '') !== '' ? $linkTargets['podcast_name'] : 'The Michael Peres Podcast') . "
"
            . "- Host: " . (($linkTargets['host_name'] ?? '') !== '' ? $linkTargets['host_name'] : 'Michael Peres') . "
"
            . "- Use this exact dateline verbatim at the start of the first paragraph: {$details['location']} (Hexa PR Wire - {$details['date']}) -
"
            . "- Never substitute another city, state, or date. Ignore any other dateline, city, state, or publication date found in transcripts, notes, source material, or prior examples.
"
            . "- After the main dateline, do not add a second location/date pair from the episode page, transcript, or old examples.
"
            . "- The first sentence after the dateline must read like a neutral wire item: [Podcast Name] features [Guest Name], [role/company context], in an episode about verified themes.
"
            . "- Do not make Hexa PR Wire the acting subject of the sentence. Never write that Hexa PR Wire announces, presents, or is pleased to announce anything in the lead.
"
            . "- Structure the opening so the guest's background, credentials, company, and relevance come before the episode recap.
"
            . "- Sections after the intro body should include: About the guest, About Michael Peres, About The Michael Peres Podcast, Contact Information.
"
            . "- Use the linked Host person record to ground the About Michael Peres section.
"
            . "- Use the linked Podcast record to ground the About The Michael Peres Podcast section.
"
            . "- If a YouTube URL or embed URL is provided, include one responsive YouTube embed iframe in the body.
"
            . "- Link the first top-level mention of the guest/person, company, podcast, and host when canonical URLs are provided.
"
            . "- In About sections, link the first mention inside that subsection to the same canonical target.
"
            . "- Use the Episode Featured Image URL as the featured image. Do not substitute a generic stock or search image when a real episode thumbnail is provided.
"
            . "- Include exactly one inline guest/client image in the body when a preferred inline guest image URL is provided.
"
            . "- Use the guest's actual organization, title, and biography from the related Notion Person record. Do not invent missing credentials.
"
            . "- Use the transcript intro, episode notes, questions, and summary only as grounded support. If they do not explicitly show that a topic was discussed, do not claim it was discussed.
"
            . "- Keep the tone factual, restrained, and publication-neutral. Do not write 'Hexa PR Wire is pleased to announce' or similar self-promotional phrasing.
"
            . "- Avoid inflated language such as remarkable, dynamic, groundbreaking, inspirational journey, valuable insights, accomplished, celebrated, visionary, or other promotional adjectives unless the source explicitly justifies them.
"
            . "- Prefer plain verbs such as features, joins, discusses, shares, explains, and outlines.
"
            . "- Use biography details as background context, not as claims about what was discussed on-air unless the transcript, episode notes, or summary explicitly confirms it.
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
                'source_label' => (string) ($photo['source_label'] ?? 'Google Drive folder media'),
                'source_meta_html' => (string) ($photo['source_meta_html'] ?? ''),
                'role' => 'inline',
                'preview_url' => (string) ($photo['preview_url'] ?? $photo['thumbnail_url'] ?? $url),
                'source_url' => (string) ($photo['source_url'] ?? $photo['download_url'] ?? $photo['view_url'] ?? $url),
                'download_url' => (string) ($photo['download_url'] ?? $url),
                'view_url' => (string) ($photo['view_url'] ?? $url),
                'drive_field' => (string) ($photo['drive_field'] ?? ''),
                'drive_folder_url' => (string) ($photo['drive_folder_url'] ?? ''),
                'filename' => (string) ($photo['filename'] ?? ''),
                'mime_type' => (string) ($photo['mime_type'] ?? ''),
                'width' => isset($photo['width']) ? (int) $photo['width'] : null,
                'height' => isset($photo['height']) ? (int) $photo['height'] : null,
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
                'source_label' => (string) ($photo['source_label'] ?? 'Google Drive folder media'),
                'source_meta_html' => (string) ($photo['source_meta_html'] ?? ''),
                'role' => $role,
                'preview_url' => (string) ($photo['preview_url'] ?? $photo['thumbnail_url'] ?? $url),
                'source_url' => (string) ($photo['source_url'] ?? $photo['download_url'] ?? $photo['view_url'] ?? $url),
                'download_url' => (string) ($photo['download_url'] ?? $url),
                'view_url' => (string) ($photo['view_url'] ?? $url),
                'drive_field' => (string) ($photo['drive_field'] ?? ''),
                'drive_folder_url' => (string) ($photo['drive_folder_url'] ?? ''),
                'filename' => (string) ($photo['filename'] ?? ''),
                'mime_type' => (string) ($photo['mime_type'] ?? ''),
                'width' => isset($photo['width']) ? (int) $photo['width'] : null,
                'height' => isset($photo['height']) ? (int) $photo['height'] : null,
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

    private function preferredEmail(array $pages): string
    {
        foreach ($pages as $page) {
            $properties = $page['properties'] ?? [];
            foreach (['Primary Email', 'Public Email', 'Email'] as $field) {
                $value = trim((string) ($this->firstValue($properties, [$field]) ?? ''));
                if ($value !== '' && filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    return $value;
                }
            }
        }

        return '';
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

    private function podcastName(array $podcastPages, array $episodeProperties = []): string
    {
        foreach ($podcastPages as $podcastPage) {
            $title = $this->sanitizePodcastName($this->displayTitle($podcastPage));
            if ($title !== '') {
                return $title;
            }

            $properties = $podcastPage['properties'] ?? [];
            $value = $this->sanitizePodcastName((string) ($this->firstValue($properties, ['Business/Company Name', 'Name', 'Podcast Name']) ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        return $this->sanitizePodcastName((string) ($this->firstValue($episodeProperties, ['Podcast Name']) ?? ''));
    }

    private function podcastDescription(array $podcastPages): string
    {
        foreach ($podcastPages as $podcastPage) {
            $properties = $podcastPage['properties'] ?? [];
            $value = $this->firstValue($properties, ['Description', 'Short Summary', 'Episode Summary']);
            if ($this->hasValue($value)) {
                return trim((string) $value);
            }
        }

        return '';
    }

    private function preferredPodcastUrl(array $podcastPages, array $episodeProperties = [], array $liveLinks = []): string
    {
        $fields = ['Podcast URL', 'Website Content', 'Apple Podcast URL', 'Spotify Podcast URL', 'Spotify URL', 'YouTube Channel URL', 'LinkedIn URL', 'Facebook URL'];
        $url = $this->preferredUrlFromPages($podcastPages, $fields);
        if ($url !== '') {
            return $this->normalizeCanonicalPublicUrl($url);
        }

        $episodeUrl = $this->normalizeUrlList($this->firstValue($episodeProperties, ['Podcast URL', 'Website Episode URL']));
        if (!empty($episodeUrl)) {
            return $this->normalizeCanonicalPublicUrl($episodeUrl[0]);
        }

        return !empty($liveLinks) ? $this->normalizeCanonicalPublicUrl($liveLinks[0]) : '';
    }

    private function sanitizePodcastName(string $value): string
    {
        $value = trim(html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($value === '') {
            return '';
        }

        if (preg_match('/^https?:\/\//i', $value)) {
            $parts = parse_url($value);
            $path = trim((string) ($parts['path'] ?? ''), '/');
            $value = $path !== '' ? basename($path) : trim((string) ($parts['host'] ?? ''));
        }

        $value = preg_replace('/^profile\//i', '', $value) ?? $value;
        $value = preg_replace('/[-_]+/', ' ', $value) ?? $value;
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        return trim($value, " 	
 /.,");
    }

    private function normalizeCanonicalPublicUrl(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }

        $parts = parse_url($url);
        if (!is_array($parts)) {
            return $url;
        }

        $scheme = trim((string) ($parts['scheme'] ?? 'https')) ?: 'https';
        $host = trim((string) ($parts['host'] ?? ''));
        if ($host === '') {
            return $url;
        }

        $path = trim((string) ($parts['path'] ?? ''));
        if ($path === '' || $path === '/') {
            return $scheme . '://' . $host . '/';
        }

        if (preg_match('#/(watch|embed|shorts)/#i', $path) || preg_match('/(spotify|apple|podcasts?|linkedin|facebook|youtube)/i', $host)) {
            return $url;
        }

        if (substr_count(trim($path, '/'), '/') >= 1 || preg_match('/-[a-z0-9]+(?:-[a-z0-9]+){2,}/i', $path)) {
            return $scheme . '://' . $host . '/';
        }

        return $scheme . '://' . $host . rtrim($path, '/') . '/';
    }

    private function preferredUrlFromPages(array $pages, array $fields): string
    {
        foreach ($pages as $page) {
            $properties = $page['properties'] ?? [];
            foreach ($fields as $field) {
                $urls = $this->normalizeUrlList($this->firstValue($properties, [$field]));
                if (!empty($urls)) {
                    return $urls[0];
                }
            }
        }

        return '';
    }

    private function preferredProfileImageUrl(array $pages): string
    {
        foreach ($pages as $page) {
            foreach (($page['properties'] ?? []) as $key => $value) {
                if (!preg_match('/photo|image|headshot|portrait|thumbnail/i', (string) $key)) {
                    continue;
                }
                foreach ($this->normalizeUrlList($value) as $url) {
                    if ($this->looksLikeRenderableImageUrl($url)) {
                        return $url;
                    }
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

    private function firstGuestDriveFolderMeta(array $guestPages): array
    {
        foreach ($guestPages as $guestPage) {
            foreach (($guestPage['properties'] ?? []) as $key => $value) {
                if (!$this->looksLikeGuestDriveProperty((string) $key)) {
                    continue;
                }

                foreach ($this->normalizeUrlList($value) as $url) {
                    if ($this->looksLikeGoogleDriveFolderUrl($url)) {
                        return [
                            'url' => $url,
                            'field' => (string) $key,
                        ];
                    }
                }
            }
        }

        return ['url' => '', 'field' => ''];
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

    private function fetchDrivePhotos(array $driveMeta, string $guestName, string $episodeTitle, string $sourceLabel = 'Google Drive folder media', string $source = 'google-drive'): array
    {
        $driveFolderUrl = trim((string) ($driveMeta['url'] ?? ''));
        $driveField = trim((string) ($driveMeta['field'] ?? ''));
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

            $cacheDirectory = 'publish/podcast-drive/' . preg_replace('/[^A-Za-z0-9_-]+/', '-', (string) ($reference['id'] ?? 'shared'));
            $photos = [];
            foreach (($result['photos'] ?? []) as $photo) {
                if (!is_array($photo)) {
                    continue;
                }
                $sourceDownloadUrl = trim((string) ($photo['web_content_link'] ?? $photo['thumbnail_link'] ?? $photo['web_view_link'] ?? ''));
                if ($sourceDownloadUrl === '') {
                    continue;
                }

                $name = trim((string) ($photo['name'] ?? 'Guest photo'));
                $cached = $drive->cacheFileToPublicDisk($photo, $cacheDirectory);
                $publicUrl = trim((string) ($cached['url'] ?? ''));
                $displayUrl = $publicUrl !== '' ? $publicUrl : $sourceDownloadUrl;
                $thumbnailUrl = $publicUrl !== '' ? $publicUrl : $this->drivePhotoPreviewUrl($photo, $sourceDownloadUrl);
                $previewUrl = $publicUrl !== '' ? $publicUrl : trim((string) ($photo['preview_url'] ?? $thumbnailUrl ?: $sourceDownloadUrl));
                $sourceUrl = (string) ($photo['web_view_link'] ?? $photo['webViewLink'] ?? $driveFolderUrl ?: $sourceDownloadUrl);
                $photos[] = [
                    'url' => $displayUrl,
                    'thumbnail_url' => $thumbnailUrl,
                    'preview_url' => $previewUrl,
                    'source_url' => $sourceUrl,
                    'alt_text' => $this->simpleGuestPhotoAlt($guestName, $name),
                    'caption' => $this->simpleGuestPhotoCaption($guestName, $name),
                    'source' => $source,
                    'source_label' => $sourceLabel,
                    'download_url' => $displayUrl,
                    'view_url' => $displayUrl,
                    'drive_field' => $driveField,
                    'drive_folder_url' => $driveFolderUrl,
                    'filename' => $name,
                    'mime_type' => (string) ($photo['mime_type'] ?? ''),
                    'width' => $photo['width'] ?? null,
                    'height' => $photo['height'] ?? null,
                    'cached_url' => $publicUrl,
                ];
            }

            return array_values($photos);
        } catch (\Throwable $e) {
            return [];
        }
    }

    private function drivePhotoPreviewUrl(array $photo, string $fallbackUrl = ''): string
    {
        $candidates = [
            $photo['thumbnail_url'] ?? null,
            $photo['preview_url'] ?? null,
            $photo['thumbnails']['1280'] ?? null,
            $photo['thumbnails'][1280] ?? null,
            $photo['thumbnails']['640'] ?? null,
            $photo['thumbnails'][640] ?? null,
            $photo['quality_urls']['thumb_1280'] ?? null,
            $photo['quality_urls']['thumb_640'] ?? null,
            $photo['thumbnailLink'] ?? null,
            $photo['thumbnail_link'] ?? null,
            $fallbackUrl,
        ];

        foreach ($candidates as $candidate) {
            $candidate = trim((string) $candidate);
            if ($candidate !== '') {
                return $candidate;
            }
        }

        return '';
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
                    'field' => (string) ($photo['drive_field'] ?? 'Podcast Assets'),
                    'source_label' => 'Google Drive folder media',
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
                    'field' => (string) ($photo['drive_field'] ?? 'Personal Photos'),
                    'source_label' => 'Google Drive folder media',
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
        array $hostPages,
        array $podcastPages,
        array $linkTargets,
        array $episodeFeaturedImage,
        array $inlineGuestImage,
        array $transcriptIntro = []
    ): array {
        $guestProperties = $guestPages[0]['properties'] ?? [];
        $hostProperties = $hostPages[0]['properties'] ?? [];
        $podcastProperties = $podcastPages[0]['properties'] ?? [];
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
            'host' => $this->extractSourceFieldEntries($hostProperties, [
                'Full Name',
                'Title / Job Title',
                'Biography (Short)',
                'Biography (Full)',
                'Official Website',
                'Podcast Profile URL',
                'Personal LinkedIn URL',
                'Featured Image URL',
            ], 'Host Person Database'),
            'podcast' => $this->extractSourceFieldEntries($podcastProperties, [
                'Business/Company Name',
                'Description',
                'Podcast URL',
                'Website Content',
                'YouTube Channel URL',
                'Apple Podcast URL',
                'Spotify Podcast URL',
                'LinkedIn URL',
                'Facebook URL',
                'Logo URL',
            ], 'Podcast Database'),
            'enforcement' => array_values(array_filter([
                $this->sourceFieldEntry('Guest link target', $linkTargets['person_url'] ?? '', 'Person URL', 'Canonical Targets'),
                $this->sourceFieldEntry('Host link target', $linkTargets['host_url'] ?? '', 'Person URL', 'Canonical Targets'),
                $this->sourceFieldEntry('Company link target', $linkTargets['company_url'] ?? '', 'Company URL', 'Canonical Targets'),
                $this->sourceFieldEntry('Podcast link target', $linkTargets['podcast_url'] ?? '', 'Podcast URL', 'Canonical Targets'),
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
