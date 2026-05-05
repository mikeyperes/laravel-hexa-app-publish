<?php

namespace hexa_app_publish\Publishing\Pipeline\Services;

use hexa_package_google_drive\Services\GoogleDriveService;
use hexa_package_notion\Services\NotionService;
use Illuminate\Support\Str;

class PressReleaseNotionBookImportService
{
    private const FALLBACK_PERSON_DATABASE_ID = 'f7b4b74d-a0b3-4670-8412-a1eb674277e3';

    public function __construct(
        private NotionService $notion
    ) {}

    public function searchPeople(string $query = '', int $limit = 10): array
    {
        $databaseId = $this->personDatabaseId();
        if ($databaseId === '') {
            return [
                'success' => false,
                'message' => 'People database is not configured in Notion.',
                'records' => [],
            ];
        }

        $query = trim($query);
        if ($query !== '') {
            $schemaResult = $this->notion->getDatabaseSchema($databaseId);
            if (!($schemaResult['success'] ?? false)) {
                return [
                    'success' => false,
                    'message' => $schemaResult['error'] ?? 'Failed to load People database schema.',
                    'records' => [],
                ];
            }

            $schema = $schemaResult['schema'] ?? [];
            $titleField = $this->notion->resolveTitlePropertyName($schema);
            $result = $this->searchPeopleInDatabase($databaseId, $query, $limit, $schema, $titleField);

            if (!($result['success'] ?? false)) {
                $fallback = $this->searchPeopleViaWorkspace($databaseId, $query, $limit);
                if (!($fallback['success'] ?? false)) {
                    return [
                        'success' => false,
                        'message' => $result['error'] ?? $fallback['error'] ?? 'Failed to search Notion people.',
                        'records' => [],
                    ];
                }

                return [
                    'success' => true,
                    'message' => 'People loaded.',
                    'records' => $fallback['records'] ?? [],
                ];
            }

            $records = array_map(
                fn (array $page) => $this->normalizePersonRecord($page, $titleField),
                array_values($result['records'] ?? [])
            );

            if (empty($records)) {
                $fallback = $this->searchPeopleViaWorkspace($databaseId, $query, $limit);
                if (($fallback['success'] ?? false) && !empty($fallback['records'] ?? [])) {
                    return [
                        'success' => true,
                        'message' => 'People loaded.',
                        'records' => $fallback['records'] ?? [],
                    ];
                }
            }

            return [
                'success' => true,
                'message' => 'People loaded.',
                'records' => $records,
            ];
        }

        $schemaResult = $this->notion->getDatabaseSchema($databaseId);
        if (!($schemaResult['success'] ?? false)) {
            return [
                'success' => false,
                'message' => $schemaResult['error'] ?? 'Failed to load People database schema.',
                'records' => [],
            ];
        }

        $result = $this->notion->queryDatabase(
            $databaseId,
            [],
            [['timestamp' => 'last_edited_time', 'direction' => 'descending']],
            max(1, min($limit, 15))
        );

        if (!($result['success'] ?? false)) {
            return [
                'success' => false,
                'message' => $result['error'] ?? 'Failed to load recent Notion people.',
                'records' => [],
            ];
        }

        $titleField = $this->notion->resolveTitlePropertyName($schemaResult['schema'] ?? []);
        $records = [];
        foreach (($result['results'] ?? []) as $page) {
            $records[] = $this->normalizePersonRecord($page, $titleField);
        }

        return [
            'success' => true,
            'message' => 'Recent people loaded.',
            'records' => $records,
        ];
    }

    private function searchPeopleInDatabase(string $databaseId, string $query, int $limit, array $schema, ?string $titleField = null): array
    {
        $filter = $this->buildPersonSearchFilter($databaseId, $query, $schema, $titleField);

        if (empty($filter)) {
            return [
                'success' => false,
                'error' => 'No searchable person fields are configured for this Notion database.',
                'records' => [],
            ];
        }

        return $this->notion->queryDatabase(
            $databaseId,
            $filter,
            [['timestamp' => 'last_edited_time', 'direction' => 'descending']],
            max(1, min($limit, 15))
        );
    }

    private function buildPersonSearchFilter(string $databaseId, string $query, array $schema, ?string $titleField = null): array
    {
        $filters = [];
        $candidateFields = array_values(array_unique(array_filter([
            $titleField,
            'Full Name',
            'Name',
            'Business/Company Name',
            'Company Name',
            'Company',
            'Current Company',
            'Official Website',
            'Company Website URL',
            'Personal LinkedIn URL',
            'Primary Email',
            'Public Email',
        ])));

        foreach ($candidateFields as $field) {
            if (!isset($schema[$field])) {
                continue;
            }

            $filters[] = $this->notion->buildContainsFilter($databaseId, $field, $query);
        }

        if (count($filters) === 1) {
            return $filters[0];
        }

        if (count($filters) > 1) {
            return ['or' => $filters];
        }

        return [];
    }

    private function searchPeopleViaWorkspace(string $databaseId, string $query, int $limit): array
    {
        $response = $this->notion->search($query, 'page', max(25, min(max($limit * 5, 25), 100)));
        if (!($response['success'] ?? false)) {
            return [
                'success' => false,
                'error' => $response['error'] ?? 'Failed to search the Notion workspace.',
                'records' => [],
            ];
        }

        $records = [];
        $seen = [];

        foreach (($response['data']['results'] ?? []) as $page) {
            $parentDatabaseId = (string) (($page['parent']['database_id'] ?? '') ?: ($page['parent']['data_source_id'] ?? ''));
            if ($parentDatabaseId !== $databaseId) {
                continue;
            }

            $pageId = trim((string) ($page['id'] ?? ''));
            if ($pageId === '' || isset($seen[$pageId])) {
                continue;
            }

            $seen[$pageId] = true;
            $parsed = $this->notion->getPage($pageId);
            if (!($parsed['success'] ?? false)) {
                continue;
            }

            $records[] = $this->normalizePersonRecord($parsed['page'] ?? []);
            if (count($records) >= $limit) {
                break;
            }
        }

        if (empty($records)) {
            return $this->searchPeopleViaLocalScan($databaseId, $query, $limit);
        }

        return [
            'success' => true,
            'records' => $records,
        ];
    }

    private function searchPeopleViaLocalScan(string $databaseId, string $query, int $limit): array
    {
        $query = Str::lower(trim($query));
        if ($query === '') {
            return [
                'success' => true,
                'records' => [],
            ];
        }

        $records = [];
        $cursor = null;
        $scanned = 0;
        $maxScanned = 250;

        do {
            $response = $this->notion->queryDatabase(
                $databaseId,
                [],
                [['timestamp' => 'last_edited_time', 'direction' => 'descending']],
                50,
                $cursor
            );

            if (!($response['success'] ?? false)) {
                return [
                    'success' => false,
                    'error' => $response['error'] ?? 'Failed to scan the Notion people database.',
                    'records' => [],
                ];
            }

            foreach (($response['results'] ?? []) as $page) {
                $scanned++;

                if ($this->personRecordMatchesQuery($page, $query)) {
                    $records[] = $this->normalizePersonRecord($page);
                    if (count($records) >= $limit) {
                        break 2;
                    }
                }

                if ($scanned >= $maxScanned) {
                    break 2;
                }
            }

            $cursor = $response['next_cursor'] ?? null;
        } while (!empty($response['has_more']) && $cursor !== null);

        return [
            'success' => true,
            'records' => $records,
        ];
    }

    private function personRecordMatchesQuery(array $page, string $query): bool
    {
        $properties = $page['properties'] ?? [];
        $values = [];

        $values[] = $this->displayTitle($page);

        foreach ([
            'Business/Company Name',
            'Company Name',
            'Company',
            'Current Company',
            'Official Website',
            'Company Website URL',
            'Personal LinkedIn URL',
            'Personal Twitter URL',
            'Personal Instagram URL',
            'Personal Facebook URL',
            'YouTube URL',
            'Wikipedia URL',
            'Primary Email',
            'Public Email',
        ] as $field) {
            $values[] = (string) ($this->firstValue($properties, [$field]) ?? '');
        }

        foreach ($values as $value) {
            if ($value !== '' && str_contains(Str::lower($value), $query)) {
                return true;
            }
        }

        return false;
    }

    public function listRelatedBooks(string $personPageId): array
    {
        $personPageId = trim($personPageId);
        if ($personPageId === '') {
            return [
                'success' => false,
                'message' => 'No person was selected.',
                'selected_person' => [],
                'records' => [],
            ];
        }

        $personParsed = $this->notion->getPage($personPageId);
        $personRaw = $this->notion->getPageRaw($personPageId);

        if (!($personParsed['success'] ?? false) || !($personRaw['success'] ?? false)) {
            return [
                'success' => false,
                'message' => $personParsed['error'] ?? $personRaw['error'] ?? 'Failed to load the selected Notion person.',
                'selected_person' => [],
                'records' => [],
            ];
        }

        $personPage = $personParsed['page'] ?? [];
        $personProperties = $personPage['properties'] ?? [];
        $relationProperty = $this->bookRelationProperty();
        $bookIds = array_values(array_filter(array_map(
            fn ($relation) => is_array($relation) ? (string) ($relation['id'] ?? '') : '',
            (array) (($personRaw['page']['properties'][$relationProperty]['relation'] ?? []) ?: [])
        )));

        $records = [];
        foreach ($bookIds as $bookId) {
            $bookParsed = $this->notion->getPage($bookId);
            if (!($bookParsed['success'] ?? false)) {
                continue;
            }

            $records[] = $this->normalizeBookRecord($bookParsed['page'] ?? []);
        }

        usort($records, fn (array $a, array $b) => strcasecmp((string) ($a['title'] ?? ''), (string) ($b['title'] ?? '')));

        return [
            'success' => true,
            'message' => empty($records) ? 'No related books were found for this person.' : 'Related books loaded.',
            'selected_person' => [
                'id' => $personPageId,
                'name' => $this->displayTitle($personPage),
                'record_url' => $personPage['url'] ?? null,
                'person_url' => $this->preferredPersonUrl([$personPage]),
                'company_name' => $this->personCompanyName([$personPage]),
                'company_url' => $this->preferredCompanyUrl([$personPage]),
                'social_urls' => $this->personSocialLinks($personProperties),
                'book_count' => count($records),
            ],
            'records' => $records,
        ];
    }

    public function importBook(string $personPageId, string $bookPageId): array
    {
        $personPageId = trim($personPageId);
        $bookPageId = trim($bookPageId);

        if ($personPageId === '' || $bookPageId === '') {
            return [
                'success' => false,
                'message' => 'Select a Notion person and one related book before continuing.',
            ];
        }

        $personParsed = $this->notion->getPage($personPageId);
        $personRaw = $this->notion->getPageRaw($personPageId);
        $bookParsed = $this->notion->getPage($bookPageId);
        $bookRaw = $this->notion->getPageRaw($bookPageId);

        if (!($personParsed['success'] ?? false) || !($personRaw['success'] ?? false) || !($bookParsed['success'] ?? false) || !($bookRaw['success'] ?? false)) {
            return [
                'success' => false,
                'message' => $personParsed['error']
                    ?? $personRaw['error']
                    ?? $bookParsed['error']
                    ?? $bookRaw['error']
                    ?? 'Failed to load the selected Notion person and book.',
            ];
        }

        $personPage = $personParsed['page'] ?? [];
        $personProperties = $personPage['properties'] ?? [];
        $bookPage = $bookParsed['page'] ?? [];
        $bookProperties = $bookPage['properties'] ?? [];

        $relatedBookIds = array_values(array_filter(array_map(
            fn ($relation) => is_array($relation) ? (string) ($relation['id'] ?? '') : '',
            (array) (($personRaw['page']['properties'][$this->bookRelationProperty()]['relation'] ?? []) ?: [])
        )));

        if (!empty($relatedBookIds) && !in_array($bookPageId, $relatedBookIds, true)) {
            return [
                'success' => false,
                'message' => 'That book is not related to the selected Notion person.',
            ];
        }

        $personName = $this->displayTitle($personPage) ?: 'Selected author';
        $bookTitle = $this->preferredBookTitle($bookProperties, $bookPage);
        $personUrl = $this->preferredPersonUrl([$personPage]);
        $companyName = $this->personCompanyName([$personPage]);
        $companyUrl = $this->preferredCompanyUrl([$personPage]);
        $bookLinks = $this->bookLinks($bookProperties);
        $primaryBookUrl = $bookLinks['primary'] ?? '';
        $bookAssetUrls = $this->bookAssetUrls($bookProperties);
        $bookDriveFolderUrl = $this->firstDriveFolderUrl($bookAssetUrls);
        $bookDrivePhotos = $this->fetchDrivePhotos($bookDriveFolderUrl, $personName, $bookTitle, 'Google Drive Book Asset', 'notion-book-drive');
        $personPhotoUrls = $this->personPhotoUrls($personProperties);
        $personDriveFolderUrl = $this->firstDriveFolderUrl($personPhotoUrls);
        $personDrivePhotos = $this->fetchDrivePhotos($personDriveFolderUrl, $personName, $bookTitle, 'Linked Person Drive Photo', 'notion-person-drive');
        $bookFeaturedImage = $this->preferredBookCoverImage($bookProperties, $bookDrivePhotos);
        $bookFeaturedImageUrl = (string) ($bookFeaturedImage['url'] ?? '');
        $inlinePersonImage = $this->preferredInlinePersonImage($personProperties, $personDrivePhotos, $bookDrivePhotos, $bookFeaturedImageUrl);
        $inlinePersonImageUrl = (string) ($inlinePersonImage['url'] ?? '');
        $details = [
            'date' => now()->format('F j, Y'),
            'location' => 'Miami, Florida',
            'contact' => $personName,
            'contact_url' => $personUrl !== '' ? $personUrl : $primaryBookUrl,
        ];

        $linkTargets = [
            'person_name' => $personName,
            'person_url' => $personUrl,
            'company_name' => $companyName,
            'company_url' => $companyUrl,
            'book_title' => $bookTitle,
            'book_url' => $primaryBookUrl,
            'google_books_url' => $bookLinks['google_books_url'] ?? '',
            'amazon_url' => $bookLinks['amazon_url'] ?? '',
            'goodreads_url' => $bookLinks['goodreads_url'] ?? '',
            'book_pdf_url' => $bookLinks['book_pdf_url'] ?? '',
            'featured_image_url' => $bookFeaturedImageUrl,
            'inline_person_image_url' => $inlinePersonImageUrl,
            'contact_url' => $details['contact_url'],
        ];

        $missing = [];
        if (empty($relatedBookIds)) {
            $missing[] = 'Book relation missing on the selected People record.';
        }
        if (($bookLinks['google_books_url'] ?? '') === '') {
            $missing[] = 'Google Book URL missing on the selected Book record. The canonical book link will fall back if another public URL exists.';
        }
        if (($bookLinks['amazon_url'] ?? '') === '') {
            $missing[] = 'Amazon URL missing on the selected Book record.';
        }
        if ($personUrl === '') {
            $missing[] = 'Public author URL missing. Add a website, LinkedIn URL, or another social URL on the linked People record.';
        }
        if ($bookFeaturedImageUrl === '') {
            $missing[] = 'Book cover image missing. Add a Book Cover URL or usable image asset on the selected Book record.';
        }
        if ($inlinePersonImageUrl === '') {
            $missing[] = 'No author photo could be resolved from the linked People record or book asset folders.';
        }
        if ($personDriveFolderUrl === '' && empty($personPhotoUrls)) {
            $missing[] = 'Personal Photos missing on the linked People record.';
        }
        if ($bookDriveFolderUrl === '' && empty($bookAssetUrls)) {
            $missing[] = 'Book asset URLs missing on the selected Book record.';
        }
        if ($this->personBio([$personPage]) === '') {
            $missing[] = 'Author biography is thin or missing in the linked People record.';
        }

        $sourceText = $this->buildBookSourceText(
            $personPage,
            $bookPage,
            $details,
            $linkTargets,
            $personPhotoUrls,
            $bookAssetUrls,
            $missing
        );

        $detectedPhotos = $this->buildBookDetectedPhotos(
            $personName,
            $bookTitle,
            $personProperties,
            $bookProperties,
            $personDrivePhotos,
            $bookDrivePhotos,
            $bookFeaturedImageUrl,
            $inlinePersonImageUrl
        );

        return [
            'success' => true,
            'message' => 'Book imported from Notion.',
            'source_text' => $sourceText,
            'preview' => Str::limit($sourceText, 1200, '...'),
            'label' => 'Notion Book · ' . $bookTitle,
            'details' => $details,
            'source_fields' => $this->buildSourceFields(
                $personProperties,
                $bookProperties,
                $linkTargets,
                $bookFeaturedImage,
                $inlinePersonImage
            ),
            'detected_photos' => $detectedPhotos,
            'selected_person' => [
                'id' => $personPageId,
                'name' => $personName,
                'record_url' => $personPage['url'] ?? null,
                'bio' => $this->personBio([$personPage]),
                'job_title' => $this->personJobTitle([$personPage]),
                'person_url' => $personUrl,
                'company_name' => $companyName,
                'company_url' => $companyUrl,
                'inline_photo_url' => $inlinePersonImageUrl,
                'inline_photo_source_field' => (string) ($inlinePersonImage['field'] ?? ''),
                'drive_folder_url' => $personDriveFolderUrl,
                'drive_photo_count' => count($personDrivePhotos),
                'social_urls' => $this->personSocialLinks($personProperties),
            ],
            'selected_book' => [
                'id' => $bookPageId,
                'title' => $bookTitle,
                'record_url' => $bookPage['url'] ?? null,
                'book_url' => $primaryBookUrl,
                'google_books_url' => $bookLinks['google_books_url'] ?? '',
                'amazon_url' => $bookLinks['amazon_url'] ?? '',
                'goodreads_url' => $bookLinks['goodreads_url'] ?? '',
                'book_pdf_url' => $bookLinks['book_pdf_url'] ?? '',
                'drive_folder_url' => $bookDriveFolderUrl,
                'featured_image_url' => $bookFeaturedImageUrl,
                'featured_image_source_field' => (string) ($bookFeaturedImage['field'] ?? ''),
            ],
            'missing_fields' => $missing,
        ];
    }

    private function personDatabaseId(): string
    {
        return trim((string) config('notion.profile_relations.person.database_id', self::FALLBACK_PERSON_DATABASE_ID));
    }

    private function bookDatabaseId(): string
    {
        return trim((string) config('notion.profile_relations.person.books.database_id', ''));
    }

    private function bookRelationProperty(): string
    {
        return trim((string) config('notion.profile_relations.person.books.source_relation_property', 'Related to Book Projects (Client)'))
            ?: 'Related to Book Projects (Client)';
    }

    private function normalizePersonRecord(array $page, ?string $titleField = null): array
    {
        $properties = $page['properties'] ?? [];
        if (empty($properties) && isset($page['title'])) {
            return [
                'id' => $page['id'] ?? null,
                'title' => (string) ($page['title'] ?? 'Untitled Person'),
                'subtitle' => (string) ($page['subtitle'] ?? ''),
                'url' => $page['url'] ?? null,
                'last_edited_time' => $page['last_edited_time'] ?? null,
            ];
        }

        return [
            'id' => $page['id'] ?? null,
            'title' => (string) ($this->notion->extractDisplayTitleFromParsedPage($page, $titleField ?: 'Full Name') ?: 'Untitled Person'),
            'subtitle' => $this->buildPersonSubtitle($properties),
            'url' => $page['url'] ?? null,
            'last_edited_time' => $page['last_edited_time'] ?? null,
            'person_url' => $this->preferredPersonUrl([$page]),
        ];
    }

    private function buildPersonSubtitle(array $properties): string
    {
        $parts = [];
        $company = trim((string) ($this->firstValue($properties, ['Business/Company Name', 'Company Name', 'Company', 'Current Company']) ?: ''));
        $website = $this->normalizeUrlList($this->firstValue($properties, ['Official Website']));
        $books = trim((string) ($this->firstValue($properties, [$this->bookRelationProperty()]) ?: ''));

        if ($company !== '') {
            $parts[] = $company;
        }
        if (!empty($website)) {
            $parts[] = 'Website available';
        }
        if ($books !== '') {
            $parts[] = $books;
        }

        return implode(' • ', $parts);
    }

    private function normalizeBookRecord(array $page, ?string $titleField = null): array
    {
        $properties = $page['properties'] ?? [];
        if (empty($properties) && isset($page['title'])) {
            return [
                'id' => $page['id'] ?? null,
                'title' => (string) ($page['title'] ?? 'Untitled Book'),
                'subtitle' => (string) ($page['subtitle'] ?? ''),
                'url' => $page['url'] ?? null,
                'last_edited_time' => $page['last_edited_time'] ?? null,
            ];
        }

        $bookLinks = $this->bookLinks($properties);
        $featured = $this->preferredBookCoverImage($properties, []);

        return [
            'id' => $page['id'] ?? null,
            'title' => $this->preferredBookTitle($properties, $page),
            'subtitle' => $this->buildBookSubtitle($properties),
            'url' => $page['url'] ?? null,
            'last_edited_time' => $page['last_edited_time'] ?? null,
            'book_url' => $bookLinks['primary'] ?? '',
            'google_books_url' => $bookLinks['google_books_url'] ?? '',
            'amazon_url' => $bookLinks['amazon_url'] ?? '',
            'featured_image_url' => (string) ($featured['url'] ?? ''),
        ];
    }

    private function buildBookSubtitle(array $properties): string
    {
        $parts = [];
        $status = trim((string) ($this->firstValue($properties, ['Status']) ?: ''));
        $googleBooks = trim((string) (($this->bookLinks($properties)['google_books_url'] ?? '') ?: ''));
        $amazon = trim((string) (($this->bookLinks($properties)['amazon_url'] ?? '') ?: ''));

        if ($status !== '') {
            $parts[] = 'Status: ' . $status;
        }
        if ($googleBooks !== '') {
            $parts[] = 'Google Books';
        } elseif ($amazon !== '') {
            $parts[] = 'Amazon';
        }

        return implode(' • ', $parts);
    }

    private function preferredBookTitle(array $properties, array $page = []): string
    {
        $candidate = trim((string) ($this->firstValue($properties, ['Proposed Titles', 'Book Title', 'Name', 'Title']) ?: ''));
        if ($candidate === '' && !empty($page)) {
            $candidate = trim((string) $this->notion->extractDisplayTitleFromParsedPage($page, 'Name'));
        }

        if ($candidate === '') {
            return 'Untitled Book';
        }

        return $this->sanitizeBookTitle($candidate);
    }

    private function sanitizeBookTitle(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        $cleaned = preg_replace('/^[^:]+:\s*Book\s*#?\d+\s*,?\s*\|?\s*/iu', '', $value);
        $cleaned = preg_replace('/^[^:]+:\s*/u', '', $cleaned ?? $value);
        $cleaned = trim((string) $cleaned);

        return $cleaned !== '' ? $cleaned : $value;
    }

    private function bookLinks(array $bookProperties): array
    {
        $googleBooks = $this->normalizeUrlList($this->firstValue($bookProperties, ['Google Book URL']));
        $amazon = $this->normalizeUrlList($this->firstValue($bookProperties, ['Amazon URL']));
        $goodreads = $this->normalizeUrlList($this->firstValue($bookProperties, ['Good Reads URL', 'Goodreads URL']));
        $pdf = $this->normalizeUrlList($this->firstValue($bookProperties, ['Book URL (pdf format)']));

        return [
            'google_books_url' => $googleBooks[0] ?? '',
            'amazon_url' => $amazon[0] ?? '',
            'goodreads_url' => $goodreads[0] ?? '',
            'book_pdf_url' => $pdf[0] ?? '',
            'primary' => $googleBooks[0] ?? $amazon[0] ?? $goodreads[0] ?? $pdf[0] ?? '',
        ];
    }

    private function bookAssetUrls(array $bookProperties): array
    {
        $urls = [];

        foreach ([
            'Google Drive Assets URL',
            'Book Assets (GD URL)',
            'Book Cover URL',
            'Book URL (pdf format)',
        ] as $field) {
            $urls = array_merge($urls, $this->normalizeUrlList($this->firstValue($bookProperties, [$field])));
        }

        return array_values(array_unique(array_filter($urls)));
    }

    private function personPhotoUrls(array $personProperties): array
    {
        $urls = [];
        foreach (['Personal Photos', 'Featured Image URL'] as $field) {
            $urls = array_merge($urls, $this->normalizeUrlList($this->firstValue($personProperties, [$field])));
        }

        return array_values(array_unique(array_filter($urls)));
    }

    private function preferredPersonUrl(array $personPages): string
    {
        $fields = [
            'Official Website',
            'Personal LinkedIn URL',
            'Personal Twitter URL',
            'Personal Instagram URL',
            'Personal Facebook URL',
            'YouTube URL',
            'Wikipedia URL',
        ];

        foreach ($personPages as $personPage) {
            $properties = $personPage['properties'] ?? [];
            foreach ($fields as $field) {
                $urls = $this->normalizeUrlList($this->firstValue($properties, [$field]));
                if (!empty($urls)) {
                    return $urls[0];
                }
            }
        }

        return '';
    }

    private function preferredCompanyUrl(array $personPages): string
    {
        $fields = ['Company Website URL', 'Official Website', 'Crunchbase URL', 'MuckRack URL', 'F6S URL'];
        foreach ($personPages as $personPage) {
            $properties = $personPage['properties'] ?? [];
            foreach ($fields as $field) {
                $urls = $this->normalizeUrlList($this->firstValue($properties, [$field]));
                if (!empty($urls)) {
                    return $urls[0];
                }
            }
        }

        return '';
    }

    private function personCompanyName(array $personPages): string
    {
        $fields = ['Business/Company Name', 'Company Name', 'Company', 'Current Company', 'Organization', 'Employer'];
        foreach ($personPages as $personPage) {
            $properties = $personPage['properties'] ?? [];
            $value = $this->firstValue($properties, $fields);
            if ($this->hasValue($value)) {
                return trim((string) $value);
            }
        }

        return '';
    }

    private function personBio(array $personPages): string
    {
        foreach ($personPages as $personPage) {
            $properties = $personPage['properties'] ?? [];
            $value = $this->firstValue($properties, ['Biography (Short)', 'Biography (Full)', 'Description', 'Mission Statement', 'Biography']);
            if ($this->hasValue($value)) {
                return trim((string) $value);
            }
        }

        return '';
    }

    private function personJobTitle(array $personPages): string
    {
        foreach ($personPages as $personPage) {
            $properties = $personPage['properties'] ?? [];
            $value = $this->firstValue($properties, ['Title / Job Title', 'Job Title', 'Position']);
            if ($this->hasValue($value)) {
                return trim((string) $value);
            }
        }

        return '';
    }

    private function personSocialLinks(array $personProperties): array
    {
        $map = [
            'website' => ['Official Website'],
            'linkedin' => ['Personal LinkedIn URL'],
            'twitter' => ['Personal Twitter URL'],
            'instagram' => ['Personal Instagram URL'],
            'facebook' => ['Personal Facebook URL'],
            'youtube' => ['YouTube URL'],
            'wikipedia' => ['Wikipedia URL'],
            'amazon_author' => ['Amazon Author URL'],
            'goodreads_author' => ['Goodreads Author URL'],
        ];

        $links = [];
        foreach ($map as $key => $fields) {
            $urls = $this->normalizeUrlList($this->firstValue($personProperties, $fields));
            if (!empty($urls)) {
                $links[$key] = $urls[0];
            }
        }

        return $links;
    }

    private function preferredBookCoverImage(array $bookProperties, array $bookDrivePhotos): array
    {
        foreach (['Book Cover URL', 'Cover Image URL', 'Featured Image URL'] as $field) {
            foreach ($this->normalizeUrlList($this->firstValue($bookProperties, [$field])) as $url) {
                if ($this->looksLikeRenderableImageUrl($url)) {
                    return [
                        'url' => $url,
                        'field' => $field,
                        'source_label' => 'Book Database',
                    ];
                }
            }
        }

        foreach ($bookDrivePhotos as $photo) {
            $url = trim((string) ($photo['url'] ?? ''));
            if ($url !== '') {
                return [
                    'url' => $url,
                    'field' => 'Google Drive Assets URL',
                    'source_label' => 'Google Drive book asset',
                ];
            }
        }

        foreach ($this->directBookImageUrls($bookProperties) as $url) {
            if ($this->looksLikeRenderableImageUrl($url)) {
                return [
                    'url' => $url,
                    'field' => 'Book Database media',
                    'source_label' => 'Book Database',
                ];
            }
        }

        return ['url' => '', 'field' => '', 'source_label' => ''];
    }

    private function preferredInlinePersonImage(array $personProperties, array $personDrivePhotos, array $bookDrivePhotos, string $featuredImageUrl = ''): array
    {
        foreach ($personDrivePhotos as $photo) {
            $url = trim((string) ($photo['url'] ?? ''));
            if ($url !== '' && $url !== $featuredImageUrl) {
                return [
                    'url' => $url,
                    'field' => 'Personal Photos',
                    'source_label' => 'Linked Person Drive Photo',
                ];
            }
        }

        foreach ($this->directPersonImageUrls($personProperties) as $url) {
            if ($url !== '' && $url !== $featuredImageUrl) {
                return [
                    'url' => $url,
                    'field' => 'Personal Photos',
                    'source_label' => 'Linked Person record',
                ];
            }
        }

        foreach ($bookDrivePhotos as $photo) {
            $url = trim((string) ($photo['url'] ?? ''));
            if ($url !== '' && $url !== $featuredImageUrl) {
                return [
                    'url' => $url,
                    'field' => 'Google Drive Assets URL',
                    'source_label' => 'Google Drive fallback',
                ];
            }
        }

        return ['url' => '', 'field' => '', 'source_label' => ''];
    }

    private function directPersonImageUrls(array $personProperties): array
    {
        return $this->directImageUrlsFromProperties($personProperties, '/photo|image|headshot|portrait|thumbnail/i');
    }

    private function directBookImageUrls(array $bookProperties): array
    {
        return $this->directImageUrlsFromProperties($bookProperties, '/cover|photo|image|thumbnail/i');
    }

    private function directImageUrlsFromProperties(array $properties, string $pattern): array
    {
        $urls = [];
        foreach ($properties as $key => $value) {
            if (!preg_match($pattern, (string) $key)) {
                continue;
            }

            foreach ($this->normalizeUrlList($value) as $url) {
                if ($this->looksLikeRenderableImageUrl($url)) {
                    $urls[] = $url;
                }
            }
        }

        return array_values(array_unique($urls));
    }

    private function buildBookSourceText(
        array $personPage,
        array $bookPage,
        array $details,
        array $linkTargets,
        array $personPhotoUrls,
        array $bookAssetUrls,
        array $missing
    ): string {
        $personName = $this->displayTitle($personPage) ?: ($linkTargets['person_name'] ?? 'Author');
        $bookTitle = $this->preferredBookTitle($bookPage['properties'] ?? [], $bookPage);

        $parts = [];
        $parts[] = "=== Book Press Release Mission ===
This is a Hexa PR Wire press release about a book and its author. Lead with the author, the book title, the core subject of the book, and why the release matters. Keep the copy factual, publication-ready, and anchored to the source material below. Do not invent endorsements, bestseller claims, rankings, awards, partnerships, quotes, launch metrics, or publication details that are not explicitly present in the source package.";

        $parts[] = "=== Author Profile ===
" . $this->renderPropertyBlock($personPage['properties'] ?? [], [
            'Full Name', 'Title / Job Title', 'Business/Company Name', 'Official Website',
            'Personal LinkedIn URL', 'Personal Twitter URL', 'Personal Instagram URL', 'Personal Facebook URL',
            'YouTube URL', 'Biography (Short)', 'Biography (Full)', 'Description', 'Personal Photos',
            'Featured Image URL', 'Amazon Author URL', 'Goodreads Author URL',
        ]);

        $parts[] = "=== Book Record ===
" . $this->renderPropertyBlock($bookPage['properties'] ?? [], [
            'Proposed Titles', 'Name', 'Google Book URL', 'Amazon URL', 'Good Reads URL',
            'Book Cover URL', 'Google Drive Assets URL', 'Book Assets (GD URL)', 'Book URL (pdf format)',
            'Description', 'Additional Information', 'ISBN', 'ASIN', 'Status',
        ]);

        if (!empty($personPhotoUrls)) {
            $parts[] = "=== Author Photo Sources ===
" . implode("
", array_map(fn (string $url) => '- ' . $url, $personPhotoUrls));
        }

        if (!empty($bookAssetUrls)) {
            $parts[] = "=== Book Asset Sources ===
" . implode("
", array_map(fn (string $url) => '- ' . $url, $bookAssetUrls));
        }

        $parts[] = "=== Canonical Link Targets ===
Person Name: " . ($linkTargets['person_name'] ?? '') . "
Person URL: " . ($linkTargets['person_url'] ?? '') . "
Company Name: " . ($linkTargets['company_name'] ?? '') . "
Company URL: " . ($linkTargets['company_url'] ?? '') . "
Book Title: " . ($linkTargets['book_title'] ?? '') . "
Book URL: " . ($linkTargets['book_url'] ?? '') . "
Google Books URL: " . ($linkTargets['google_books_url'] ?? '') . "
Amazon URL: " . ($linkTargets['amazon_url'] ?? '') . "
Goodreads URL: " . ($linkTargets['goodreads_url'] ?? '') . "
Book PDF URL: " . ($linkTargets['book_pdf_url'] ?? '') . "
Book Cover URL: " . ($linkTargets['featured_image_url'] ?? '') . "
Preferred Inline Author Image URL: " . ($linkTargets['inline_person_image_url'] ?? '') . "
Contact URL: " . ($linkTargets['contact_url'] ?? '');

        $parts[] = "=== Required Structural Cues ===
- Publication: Hexa PR Wire
- Use this exact dateline verbatim at the start of the first paragraph: {$details['location']} (Hexa PR Wire - {$details['date']}) -
- Keep the article in formal third-person press-release style.
- Never use em dashes or en dashes anywhere in the article, title options, SEO description, categories, or tags.
- The first mention of the author must link to the Person URL when provided.
- The first mention of the book title must link to the Book URL when provided.
- Use the Book Cover URL as the featured image. Do not substitute a generic stock image when a real cover is available.
- Include exactly one inline author image in the body when a preferred inline author image URL is provided.
- Build the opening around the author, the book, the core topic or promise of the book, and why the release matters.
- After the opening body, include these exact H2 sections in this order: About {$personName}, About the Book, Contact Information.
- If Google Books, Amazon, Goodreads, or PDF URLs are present above, use them naturally where relevant. Do not invent or guess links.
- Do not mention Notion, internal fields, raw property names, or source systems.";

        if (!empty($missing)) {
            $parts[] = "=== Known Data Gaps ===
" . implode("
", array_map(fn (string $line) => '- ' . $line, $missing));
        }

        return trim(implode("

", array_filter($parts)));
    }

    private function buildBookDetectedPhotos(
        string $personName,
        string $bookTitle,
        array $personProperties,
        array $bookProperties,
        array $personDrivePhotos,
        array $bookDrivePhotos,
        string $bookFeaturedImageUrl,
        string $inlinePersonImageUrl
    ): array {
        $candidates = [];

        if ($bookFeaturedImageUrl !== '') {
            $candidates[] = $this->imageCandidate(
                $bookFeaturedImageUrl,
                $bookTitle,
                $bookTitle,
                'notion-book-cover',
                'Notion Book Cover',
                'featured'
            );
        }

        foreach ($personDrivePhotos as $photo) {
            $url = trim((string) ($photo['url'] ?? ''));
            if ($url === '') {
                continue;
            }

            $candidates[] = $this->imageCandidate(
                $url,
                (string) ($photo['alt_text'] ?? $this->simplePersonPhotoAlt($personName)),
                (string) ($photo['caption'] ?? $this->simplePersonPhotoCaption($personName)),
                (string) ($photo['source'] ?? 'notion-person-drive'),
                (string) ($photo['source_label'] ?? 'Linked Person Drive Photo'),
                $url === $inlinePersonImageUrl ? 'inline' : 'inline',
                (string) ($photo['filename'] ?? '')
            );
        }

        foreach ($this->directPersonImageUrls($personProperties) as $url) {
            $candidates[] = $this->imageCandidate(
                $url,
                $this->simplePersonPhotoAlt($personName),
                $this->simplePersonPhotoCaption($personName),
                'notion-person-media',
                'Notion Person Media',
                $url === $inlinePersonImageUrl ? 'inline' : 'inline'
            );
        }

        foreach ($bookDrivePhotos as $photo) {
            $url = trim((string) ($photo['url'] ?? ''));
            if ($url === '') {
                continue;
            }

            $role = $url === $bookFeaturedImageUrl ? 'featured' : 'inline';
            $candidates[] = $this->imageCandidate(
                $url,
                (string) ($photo['alt_text'] ?? ($role === 'featured' ? $bookTitle : $this->simplePersonPhotoAlt($personName))),
                (string) ($photo['caption'] ?? ($role === 'featured' ? $bookTitle : $this->simplePersonPhotoCaption($personName))),
                (string) ($photo['source'] ?? 'notion-book-drive'),
                (string) ($photo['source_label'] ?? 'Google Drive Book Asset'),
                $role,
                (string) ($photo['filename'] ?? '')
            );
        }

        foreach ($this->directBookImageUrls($bookProperties) as $url) {
            $role = $url === $bookFeaturedImageUrl ? 'featured' : 'inline';
            $candidates[] = $this->imageCandidate(
                $url,
                $role === 'featured' ? $bookTitle : $this->simplePersonPhotoAlt($personName),
                $role === 'featured' ? $bookTitle : $this->simplePersonPhotoCaption($personName),
                'notion-book-media',
                'Notion Book Media',
                $role
            );
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

    private function buildSourceFields(
        array $personProperties,
        array $bookProperties,
        array $linkTargets,
        array $bookFeaturedImage,
        array $inlinePersonImage
    ): array {
        return [
            'person' => $this->extractSourceFieldEntries($personProperties, [
                'Full Name',
                'Title / Job Title',
                'Business/Company Name',
                'Official Website',
                'Personal LinkedIn URL',
                'Personal Twitter URL',
                'Personal Instagram URL',
                'Personal Facebook URL',
                'YouTube URL',
                'Biography (Short)',
                'Biography (Full)',
                'Description',
                'Personal Photos',
                'Featured Image URL',
            ], 'Person Database'),
            'book' => $this->extractSourceFieldEntries($bookProperties, [
                'Proposed Titles',
                'Name',
                'Google Book URL',
                'Amazon URL',
                'Good Reads URL',
                'Book Cover URL',
                'Google Drive Assets URL',
                'Book Assets (GD URL)',
                'Book URL (pdf format)',
                'Description',
                'Additional Information',
                'ISBN',
                'ASIN',
            ], 'Book Database'),
            'enforcement' => array_values(array_filter([
                $this->sourceFieldEntry('Author link target', $linkTargets['person_url'] ?? '', 'Person URL', 'Canonical Targets'),
                $this->sourceFieldEntry('Book link target', $linkTargets['book_url'] ?? '', 'Book URL', 'Canonical Targets'),
                $this->sourceFieldEntry('Google Books URL', $linkTargets['google_books_url'] ?? '', 'Google Book URL', 'Canonical Targets'),
                $this->sourceFieldEntry('Amazon URL', $linkTargets['amazon_url'] ?? '', 'Amazon URL', 'Canonical Targets'),
                $this->sourceFieldEntry(
                    'Book cover URL',
                    $linkTargets['featured_image_url'] ?? '',
                    (string) ($bookFeaturedImage['field'] ?? ''),
                    'Book Database'
                ),
                $this->sourceFieldEntry(
                    'Inline author image URL',
                    $linkTargets['inline_person_image_url'] ?? '',
                    (string) ($inlinePersonImage['field'] ?? ''),
                    str_contains((string) ($inlinePersonImage['source_label'] ?? ''), 'Person') ? 'Person Database' : 'Book Database'
                ),
                $this->sourceFieldEntry('Contact URL', $linkTargets['contact_url'] ?? '', 'Contact URL', 'Canonical Targets'),
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

        return array_values(array_filter($entries));
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

    private function imageCandidate(
        string $url,
        string $altText,
        string $caption,
        string $source,
        string $sourceLabel,
        string $role = 'inline',
        string $filename = ''
    ): array {
        $url = trim($url);
        $previewUrl = $url;
        $downloadUrl = $url;
        $viewUrl = $url;

        $googleDriveFileId = $this->googleDriveFileId($url);
        if ($googleDriveFileId !== '') {
            $previewUrl = 'https://drive.google.com/thumbnail?id=' . rawurlencode($googleDriveFileId) . '&sz=w1600';
            $downloadUrl = 'https://drive.google.com/uc?export=download&id=' . rawurlencode($googleDriveFileId);
            $viewUrl = $url;
        }

        return [
            'url' => $url,
            'thumbnail_url' => $previewUrl,
            'preview_url' => $previewUrl,
            'alt_text' => trim($altText),
            'caption' => trim($caption),
            'source' => $source,
            'source_label' => $sourceLabel,
            'role' => $role,
            'download_url' => $downloadUrl,
            'source_url' => $downloadUrl,
            'view_url' => $viewUrl,
            'filename' => $filename,
        ];
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

    private function fetchDrivePhotos(string $driveFolderUrl, string $personName, string $bookTitle, string $sourceLabel = 'Google Drive Book Asset', string $source = 'google-drive'): array
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

                $name = trim((string) ($photo['name'] ?? 'Book asset'));
                $photos[] = [
                    'url' => $url,
                    'thumbnail_url' => (string) ($photo['thumbnail_link'] ?? $url),
                    'alt_text' => $this->simplePersonPhotoAlt($personName, $name ?: $bookTitle),
                    'caption' => $this->simplePersonPhotoCaption($personName, $name ?: $bookTitle),
                    'source' => $source,
                    'source_label' => $sourceLabel,
                    'download_url' => (string) ($photo['web_content_link'] ?? $url),
                    'view_url' => (string) ($photo['web_view_link'] ?? $url),
                    'filename' => $name,
                ];
            }

            return array_values($photos);
        } catch (\Throwable $e) {
            return [];
        }
    }

    private function simplePersonPhotoCaption(string $personName, string $fallback = ''): string
    {
        $personName = trim($personName);
        if ($personName !== '') {
            return $personName;
        }

        $fallback = trim(pathinfo($fallback, PATHINFO_FILENAME));
        if ($fallback !== '') {
            return trim((string) preg_replace('/[_-]+/', ' ', $fallback));
        }

        return 'Author photo';
    }

    private function simplePersonPhotoAlt(string $personName, string $fallback = ''): string
    {
        return $this->simplePersonPhotoCaption($personName, $fallback);
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

    private function looksLikeRenderableImageUrl(string $url): bool
    {
        $url = strtolower(trim($url));
        if ($url === '') {
            return false;
        }

        if ($this->looksLikeGoogleDriveFolderUrl($url)) {
            return false;
        }

        if ($this->looksLikeGoogleDriveFileUrl($url)) {
            return true;
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

    private function looksLikeGoogleDriveFileUrl(string $url): bool
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

        if (str_contains($path, '/file/d/')) {
            return true;
        }

        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        return !empty($query['id']);
    }

    private function googleDriveFileId(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }

        if (preg_match('~/d/([A-Za-z0-9_-]+)~', $url, $matches)) {
            return (string) ($matches[1] ?? '');
        }

        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        return trim((string) ($query['id'] ?? ''));
    }

    private function firstDriveFolderUrl(array $urls): string
    {
        foreach ($urls as $url) {
            $url = trim((string) $url);
            if ($url !== '' && $this->looksLikeGoogleDriveFolderUrl($url)) {
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
}
