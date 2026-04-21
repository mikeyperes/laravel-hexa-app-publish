<?php

namespace hexa_app_publish\Discovery\Media\Services;

use hexa_core\Models\Setting;
use hexa_core\Services\ImageCopyrightBlacklistService;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * MediaSearchService — unified photo search across Pexels, Unsplash, Pixabay.
 *
 * Replaces inline photo search logic from ArticleController::searchPhotos()
 * and PublishSearchController::searchImages().
 */
class MediaSearchService
{
    private const CONNECT_TIMEOUT_SECONDS = 4;
    private const REQUEST_TIMEOUT_SECONDS = 8;

    /**
     * Search for photos across configured providers.
     *
     * @param string $query
     * @param array $sources Provider names (pexels, unsplash, pixabay)
     * @param int $perPage Results per provider
     * @param int $page Page number
     * @param string $qualityContext featured|inline|general
     * @param bool $probeQuality Whether to probe top candidates for file type/size
     * @return array{success: bool, photos: array, totals: array, errors: array, timings: array, total_ms: int, message: string}
     */
    public function searchPhotos(
        string $query,
        array $sources = ['pexels', 'unsplash', 'pixabay'],
        int $perPage = 15,
        int $page = 1,
        string $qualityContext = 'inline',
        bool $probeQuality = false
    ): array
    {
        $result = $this->searchPhotosBatch([
            [
                'key' => 'single',
                'query' => $query,
                'per_page' => $perPage,
                'page' => $page,
            ],
        ], $sources, $qualityContext, $probeQuality);

        $single = $result['results']['single'] ?? [
            'success' => false,
            'photos' => [],
            'totals' => [],
            'errors' => [],
            'timings' => [],
        ];

        return [
            'success' => $single['success'],
            'photos' => $single['photos'],
            'totals' => $single['totals'],
            'errors' => $single['errors'],
            'timings' => $single['timings'],
            'total_ms' => $result['total_ms'],
            'message' => $single['message'] ?? (count($single['photos']) . ' photos found.'),
        ];
    }

    /**
     * Search multiple terms in a single backend request so the browser does not queue one
     * /search/images request per placeholder on single-threaded local servers.
     *
     * @param array<int, array{key?: string, query?: string, per_page?: int, page?: int}> $queries
     * @param array<int, string> $sources
     * @param string $qualityContext featured|inline|general
     * @param bool $probeQuality Whether to probe top candidates for file type/size
     * @return array{success: bool, results: array<string, array>, total_ms: int}
     */
    public function searchPhotosBatch(
        array $queries,
        array $sources = ['pexels', 'unsplash', 'pixabay'],
        string $qualityContext = 'inline',
        bool $probeQuality = false
    ): array
    {
        $normalizedSources = collect($sources)
            ->map(fn ($source) => strtolower((string) $source))
            ->filter(fn ($source) => in_array($source, ['pexels', 'unsplash', 'pixabay', 'google', 'serpapi', 'google-cse'], true))
            ->values()
            ->all();

        $normalizedQueries = collect($queries)
            ->map(function ($item, $index) {
                return [
                    'key' => (string) ($item['key'] ?? $index),
                    'query' => trim((string) ($item['query'] ?? '')),
                    'per_page' => max(1, min((int) ($item['per_page'] ?? 15), 30)),
                    'page' => max(1, min((int) ($item['page'] ?? 1), 50)),
                ];
            })
            ->filter(fn ($item) => $item['query'] !== '')
            ->values()
            ->all();

        $results = [];
        foreach ($normalizedQueries as $item) {
            $results[$item['key']] = [
                'success' => false,
                'query' => $item['query'],
                'photos' => [],
                'totals' => [],
                'errors' => [],
                'timings' => [],
                'message' => 'No photos found.',
            ];
        }

        if (empty($normalizedQueries) || empty($normalizedSources)) {
            return [
                'success' => false,
                'results' => $results,
                'total_ms' => 0,
            ];
        }

        $requestDefinitions = [];
        $serviceDefinitions = [];
        foreach ($normalizedQueries as $item) {
            foreach ($normalizedSources as $source) {
                if ($this->usesHttpPool($source)) {
                    $definition = $this->requestDefinition($source, $item['query'], $item['per_page'], $item['page']);
                    if ($definition === null) {
                        $results[$item['key']]['errors'][] = ucfirst($source) . ': Not configured.';
                        continue;
                    }

                    $alias = $item['key'] . ':' . $source;
                    $requestDefinitions[$alias] = $definition + [
                        'key' => $item['key'],
                        'source' => $source,
                    ];
                    continue;
                }

                if (!$this->providerConfigured($source)) {
                    $results[$item['key']]['errors'][] = ucfirst($source) . ': Not configured.';
                    continue;
                }

                $serviceDefinitions[] = [
                    'key' => $item['key'],
                    'source' => $source,
                    'query' => $item['query'],
                    'per_page' => $item['per_page'],
                    'page' => $item['page'],
                ];
            }
        }

        $startedAt = microtime(true);

        $responses = [];
        if (!empty($requestDefinitions)) {
            $responses = Http::pool(function (Pool $pool) use ($requestDefinitions) {
                $requests = [];
                foreach ($requestDefinitions as $alias => $definition) {
                    $request = $pool
                        ->as($alias)
                        ->connectTimeout(self::CONNECT_TIMEOUT_SECONDS)
                        ->timeout(self::REQUEST_TIMEOUT_SECONDS)
                        ->acceptJson();

                    if (!empty($definition['headers'])) {
                        $request = $request->withHeaders($definition['headers']);
                    }

                    $requests[$alias] = $request->get($definition['url'], $definition['query']);
                }

                return $requests;
            });
        }

        $totalMs = (int) round((microtime(true) - $startedAt) * 1000);

        foreach ($requestDefinitions as $alias => $definition) {
            $response = $responses[$alias] ?? null;
            $parsed = $this->parseSourceResponse($definition['source'], $response);
            $bucket = &$results[$definition['key']];

            if (!empty($parsed['photos'])) {
                $bucket['photos'] = array_merge($bucket['photos'], $parsed['photos']);
            }

            if (isset($parsed['total'])) {
                $bucket['totals'][$definition['source']] = $parsed['total'];
            }

            if (isset($parsed['timing_ms'])) {
                $bucket['timings'][$definition['source']] = $parsed['timing_ms'];
            }

            if (!empty($parsed['error'])) {
                $bucket['errors'][] = ucfirst($definition['source']) . ': ' . $parsed['error'];
            }

            unset($bucket);
        }

        foreach ($serviceDefinitions as $definition) {
            $parsed = $this->searchServiceProvider(
                $definition['source'],
                $definition['query'],
                $definition['per_page'],
                $definition['page']
            );
            $bucket = &$results[$definition['key']];

            if (!empty($parsed['photos'])) {
                $bucket['photos'] = array_merge($bucket['photos'], $parsed['photos']);
            }

            if (isset($parsed['total'])) {
                $bucket['totals'][$definition['source']] = $parsed['total'];
            }

            if (isset($parsed['timing_ms'])) {
                $bucket['timings'][$definition['source']] = $parsed['timing_ms'];
            }

            if (!empty($parsed['error'])) {
                $bucket['errors'][] = ucfirst($definition['source']) . ': ' . $parsed['error'];
            }

            unset($bucket);
        }

        foreach ($results as &$bucket) {
            $bucket['photos'] = $this->rankPhotos($bucket['photos'], $qualityContext, $probeQuality);
            $bucket['success'] = count($bucket['photos']) > 0;
            $bucket['message'] = count($bucket['photos']) . ' photos found across ' . count($bucket['totals']) . ' source(s).';
        }
        unset($bucket);

        return [
            'success' => collect($results)->contains(fn ($bucket) => $bucket['success']),
            'results' => $results,
            'total_ms' => $totalMs,
        ];
    }

    /**
     * Rank and enrich photo candidates for auto-selection.
     *
     * @param array<int, array<string, mixed>> $photos
     * @return array<int, array<string, mixed>>
     */
    public function rankPhotos(array $photos, string $qualityContext = 'inline', bool $probeQuality = false): array
    {
        $context = $this->normalizeQualityContext($qualityContext);
        $enriched = array_map(fn (array $photo) => $this->enrichPhoto($photo, $context), $photos);

        usort($enriched, fn (array $a, array $b) => ($b['quality_score'] ?? 0) <=> ($a['quality_score'] ?? 0));

        if ($probeQuality) {
            $probeLimit = max(1, (int) config('hws-publish.photo_quality.probe_top_candidates', 4));
            $topCount = min(count($enriched), $probeLimit);

            for ($i = 0; $i < $topCount; $i++) {
                $probe = $this->probePhotoAsset((string) ($enriched[$i]['url_large'] ?? $enriched[$i]['url_full'] ?? $enriched[$i]['url'] ?? ''));
                if (!empty($probe)) {
                    $enriched[$i]['file_size_bytes'] = $probe['file_size_bytes'] ?? ($enriched[$i]['file_size_bytes'] ?? null);
                    $enriched[$i]['mime_type'] = $probe['mime_type'] ?? ($enriched[$i]['mime_type'] ?? null);
                    $enriched[$i] = $this->enrichPhoto($enriched[$i], $context);
                }
            }
        }

        usort($enriched, function (array $a, array $b) {
            $passCmp = (($b['quality_pass'] ?? false) <=> ($a['quality_pass'] ?? false));
            if ($passCmp !== 0) {
                return $passCmp;
            }

            $scoreCmp = (($b['quality_score'] ?? 0) <=> ($a['quality_score'] ?? 0));
            if ($scoreCmp !== 0) {
                return $scoreCmp;
            }

            return (($b['width'] ?? 0) * ($b['height'] ?? 0)) <=> (($a['width'] ?? 0) * ($a['height'] ?? 0));
        });

        return array_values($enriched);
    }

    private function requestDefinition(string $source, string $query, int $perPage, int $page): ?array
    {
        return match ($source) {
            'pexels' => $this->pexelsRequestDefinition($query, $perPage, $page),
            'unsplash' => $this->unsplashRequestDefinition($query, $perPage, $page),
            'pixabay' => $this->pixabayRequestDefinition($query, $perPage, $page),
            default => null,
        };
    }

    private function usesHttpPool(string $source): bool
    {
        return in_array($source, ['pexels', 'unsplash', 'pixabay'], true);
    }

    private function providerConfigured(string $source): bool
    {
        return match ($source) {
            'google-cse' => Setting::getValue('use_google_image_search', '0') === '1'
                && class_exists(\hexa_package_google_cse\Services\GoogleCseService::class),
            'google', 'serpapi' => Setting::getValue('use_serpapi_search', '0') === '1'
                && class_exists(\hexa_package_serpapi\Services\SerpApiService::class),
            default => false,
        };
    }

    private function pexelsRequestDefinition(string $query, int $perPage, int $page): ?array
    {
        $key = Setting::getValue('pexels_api_key');
        if (!$key) {
            return null;
        }

        return [
            'url' => 'https://api.pexels.com/v1/search',
            'query' => [
                'query' => $query,
                'per_page' => min($perPage, 80),
                'page' => $page,
            ],
            'headers' => [
                'Authorization' => $key,
            ],
        ];
    }

    private function unsplashRequestDefinition(string $query, int $perPage, int $page): ?array
    {
        $key = Setting::getValue('unsplash_api_key');
        if (!$key) {
            return null;
        }

        return [
            'url' => 'https://api.unsplash.com/search/photos',
            'query' => [
                'query' => $query,
                'per_page' => min($perPage, 30),
                'page' => $page,
            ],
            'headers' => [
                'Authorization' => 'Client-ID ' . $key,
            ],
        ];
    }

    private function pixabayRequestDefinition(string $query, int $perPage, int $page): ?array
    {
        $key = Setting::getValue('pixabay_api_key');
        if (!$key) {
            return null;
        }

        return [
            'url' => 'https://pixabay.com/api/',
            'query' => [
                'key' => $key,
                'q' => $query,
                'per_page' => min($perPage, 200),
                'page' => $page,
                'image_type' => 'photo',
                'safesearch' => 'true',
            ],
            'headers' => [],
        ];
    }

    private function parseSourceResponse(string $source, mixed $response): array
    {
        if (!$response instanceof Response) {
            return [
                'photos' => [],
                'error' => 'Request failed.',
            ];
        }

        if (!$response->successful()) {
            return [
                'photos' => [],
                'error' => 'HTTP ' . $response->status(),
                'timing_ms' => $this->responseTimingMs($response),
            ];
        }

        $payload = $response->json();

        return match ($source) {
            'pexels' => [
                'photos' => collect($payload['photos'] ?? [])->map(fn ($p) => [
                    'source' => 'pexels',
                    'id' => $p['id'] ?? null,
                    'url_thumb' => $p['src']['medium'] ?? $p['src']['small'] ?? $p['src']['large'] ?? null,
                    'url_full' => $p['src']['original'] ?? null,
                    'url_large' => $p['src']['large2x'] ?? $p['src']['large'] ?? $p['src']['medium'] ?? null,
                    'source_url' => $p['url'] ?? null,
                    'alt' => $p['alt'] ?? '',
                    'photographer' => $p['photographer'] ?? '',
                    'photographer_url' => $p['photographer_url'] ?? '',
                    'width' => $p['width'] ?? 0,
                    'height' => $p['height'] ?? 0,
                ])->values()->all(),
                'total' => $payload['total_results'] ?? 0,
                'timing_ms' => $this->responseTimingMs($response),
            ],
            'unsplash' => [
                'photos' => collect($payload['results'] ?? [])->map(fn ($p) => [
                    'source' => 'unsplash',
                    'id' => $p['id'] ?? null,
                    'url_thumb' => $p['urls']['small'] ?? $p['urls']['thumb'] ?? $p['urls']['regular'] ?? null,
                    'url_full' => $p['urls']['full'] ?? null,
                    'url_large' => $p['urls']['regular'] ?? $p['urls']['full'] ?? null,
                    'source_url' => $p['links']['html'] ?? null,
                    'alt' => $p['alt_description'] ?? $p['description'] ?? '',
                    'photographer' => $p['user']['name'] ?? '',
                    'photographer_url' => $p['user']['links']['html'] ?? '',
                    'width' => $p['width'] ?? 0,
                    'height' => $p['height'] ?? 0,
                    'download_url' => $p['links']['download'] ?? null,
                    'attribution_required' => true,
                ])->values()->all(),
                'total' => $payload['total'] ?? 0,
                'timing_ms' => $this->responseTimingMs($response),
            ],
            'pixabay' => [
                'photos' => collect($payload['hits'] ?? [])->map(fn ($p) => [
                    'source' => 'pixabay',
                    'id' => $p['id'] ?? null,
                    'url_thumb' => $p['webformatURL'] ?? null,
                    'url_full' => $p['largeImageURL'] ?? null,
                    'url_large' => $p['largeImageURL'] ?? $p['webformatURL'] ?? null,
                    'source_url' => $p['pageURL'] ?? null,
                    'alt' => $p['tags'] ?? '',
                    'photographer' => $p['user'] ?? '',
                    'photographer_url' => isset($p['user'], $p['user_id']) ? "https://pixabay.com/users/{$p['user']}-{$p['user_id']}/" : '',
                    'width' => $p['imageWidth'] ?? 0,
                    'height' => $p['imageHeight'] ?? 0,
                ])->values()->all(),
                'total' => $payload['totalHits'] ?? 0,
                'timing_ms' => $this->responseTimingMs($response),
            ],
            default => [
                'photos' => [],
                'error' => 'Unsupported source.',
            ],
        };
    }

    private function responseTimingMs(Response $response): ?int
    {
        $stats = $response->transferStats;
        if (!$stats) {
            return null;
        }

        return (int) round($stats->getTransferTime() * 1000);
    }

    private function normalizeQualityContext(string $context): string
    {
        return in_array($context, ['featured', 'inline'], true) ? $context : 'inline';
    }

    /**
     * @return array<string, mixed>
     */
    private function qualityProfile(string $context): array
    {
        return (array) config('hws-publish.photo_quality.' . $this->normalizeQualityContext($context), []);
    }

    /**
     * @param array<string, mixed> $photo
     * @return array<string, mixed>
     */
    private function enrichPhoto(array $photo, string $qualityContext): array
    {
        $profile = $this->qualityProfile($qualityContext);
        $url = (string) ($photo['url_large'] ?? $photo['url_full'] ?? $photo['url'] ?? $photo['url_thumb'] ?? '');
        $sourceUrl = (string) ($photo['source_url'] ?? '');
        $sourceDomain = strtolower((string) ($photo['domain'] ?? parse_url($sourceUrl, PHP_URL_HOST) ?: parse_url($url, PHP_URL_HOST) ?: ''));
        $extension = strtolower(pathinfo(parse_url($url, PHP_URL_PATH) ?: '', PATHINFO_EXTENSION));
        $mimeType = strtolower((string) ($photo['mime_type'] ?? ''));
        $width = (int) ($photo['width'] ?? 0);
        $height = (int) ($photo['height'] ?? 0);
        $aspectRatio = ($width > 0 && $height > 0) ? round($width / max($height, 1), 3) : null;
        $landscape = $width > 0 && $height > 0 && $width >= $height;
        $fileSizeBytes = isset($photo['file_size_bytes']) && is_numeric($photo['file_size_bytes'])
            ? (int) $photo['file_size_bytes']
            : null;

        $blacklist = app(ImageCopyrightBlacklistService::class)->match($url, $sourceDomain);
        $copyrightFlag = (bool) ($photo['copyright_flag'] ?? false) || (bool) ($blacklist['flagged'] ?? false);
        $allowedExtensions = array_map('strtolower', (array) ($profile['allowed_extensions'] ?? []));
        $allowedMimeTypes = array_map('strtolower', (array) ($profile['allowed_mime_types'] ?? []));
        $preferredSources = array_map('strtolower', (array) ($profile['preferred_sources'] ?? []));
        $source = strtolower((string) ($photo['source'] ?? ''));

        $fileTypeOk = null;
        if ($extension !== '' || $mimeType !== '') {
            $fileTypeOk = ($extension !== '' && in_array($extension, $allowedExtensions, true))
                || ($mimeType !== '' && in_array($mimeType, $allowedMimeTypes, true));
        }

        $fileSizeOk = $fileSizeBytes !== null
            ? $fileSizeBytes >= (int) ($profile['min_bytes'] ?? 0)
            : null;

        $dimensionsOk = $width >= (int) ($profile['min_width'] ?? 0)
            && $height >= (int) ($profile['min_height'] ?? 0);
        $aspectOk = $landscape
            && $aspectRatio !== null
            && $aspectRatio >= (float) ($profile['min_aspect_ratio'] ?? 1.0)
            && $aspectRatio <= (float) ($profile['max_aspect_ratio'] ?? 3.0);
        $sourceOk = !$copyrightFlag;
        $preferredSource = in_array($source, $preferredSources, true);

        $qualityPass = $dimensionsOk
            && $aspectOk
            && $sourceOk
            && $fileTypeOk !== false
            && $fileSizeOk !== false;

        $score = 0;
        $score += $sourceOk ? 220 : -1200;
        $score += $preferredSource ? 70 : 0;
        $score += $dimensionsOk ? 140 : -120;
        $score += $aspectOk ? 120 : -90;
        $score += $fileTypeOk === true ? 80 : ($fileTypeOk === false ? -120 : 0);
        $score += $fileSizeOk === true ? 60 : ($fileSizeOk === false ? -80 : 0);
        $score += min(140, (int) floor(($width * $height) / 200000));

        $photo['domain'] = $sourceDomain;
        $photo['file_extension'] = $extension;
        $photo['mime_type'] = $mimeType !== '' ? $mimeType : null;
        $photo['file_size_bytes'] = $fileSizeBytes;
        $photo['aspect_ratio'] = $aspectRatio;
        $photo['copyright_flag'] = $copyrightFlag;
        $photo['copyright_reason'] = $photo['copyright_reason'] ?? ($blacklist['reason'] ?? null);
        $photo['quality_context'] = $qualityContext;
        $photo['quality_pass'] = $qualityPass;
        $photo['quality_score'] = $score;
        $photo['quality_checklist'] = [
            'dimensions' => [
                'ok' => $dimensionsOk,
                'width' => $width,
                'height' => $height,
                'min_width' => (int) ($profile['min_width'] ?? 0),
                'min_height' => (int) ($profile['min_height'] ?? 0),
            ],
            'file_size' => [
                'ok' => $fileSizeOk,
                'bytes' => $fileSizeBytes,
                'min_bytes' => (int) ($profile['min_bytes'] ?? 0),
            ],
            'file_type' => [
                'ok' => $fileTypeOk,
                'extension' => $extension !== '' ? $extension : null,
                'mime_type' => $mimeType !== '' ? $mimeType : null,
                'allowed_extensions' => $allowedExtensions,
                'allowed_mime_types' => $allowedMimeTypes,
            ],
            'aspect_ratio' => [
                'ok' => $aspectOk,
                'ratio' => $aspectRatio,
                'landscape' => $landscape,
                'min' => (float) ($profile['min_aspect_ratio'] ?? 1.0),
                'max' => (float) ($profile['max_aspect_ratio'] ?? 3.0),
            ],
            'source' => [
                'ok' => $sourceOk,
                'provider' => $source,
                'domain' => $sourceDomain,
                'preferred' => $preferredSource,
                'blacklisted' => $copyrightFlag,
            ],
        ];

        return $photo;
    }

    /**
     * @return array{photos: array<int, array<string, mixed>>, total?: int, timing_ms?: int, error?: string}
     */
    private function searchServiceProvider(string $source, string $query, int $perPage, int $page): array
    {
        $startedAt = microtime(true);

        try {
            $result = match ($source) {
                'google-cse' => $this->searchGoogleCse($query, $perPage, $page),
                'google', 'serpapi' => $this->searchSerp($query, $perPage, $page),
                default => ['success' => false, 'message' => 'Unsupported source.', 'data' => null],
            };
        } catch (\Throwable $e) {
            return [
                'photos' => [],
                'error' => $e->getMessage(),
                'timing_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            ];
        }

        $timingMs = (int) round((microtime(true) - $startedAt) * 1000);

        if (!($result['success'] ?? false)) {
            return [
                'photos' => [],
                'error' => (string) ($result['message'] ?? 'Request failed.'),
                'timing_ms' => $timingMs,
            ];
        }

        return [
            'photos' => array_values((array) ($result['data']['photos'] ?? [])),
            'total' => $result['data']['total'] ?? count((array) ($result['data']['photos'] ?? [])),
            'timing_ms' => $timingMs,
        ];
    }

    private function searchGoogleCse(string $query, int $perPage, int $page): array
    {
        if (!class_exists(\hexa_package_google_cse\Services\GoogleCseService::class)) {
            return ['success' => false, 'message' => 'Google CSE package not available.', 'data' => null];
        }

        $service = app(\hexa_package_google_cse\Services\GoogleCseService::class);
        if ($service->isQuotaExhausted()) {
            return ['success' => false, 'message' => 'Google CSE quota exhausted.', 'data' => null];
        }

        $start = (($page - 1) * max($perPage, 1)) + 1;
        return $service->searchImages($query, $perPage, $start);
    }

    private function searchSerp(string $query, int $perPage, int $page): array
    {
        if (!class_exists(\hexa_package_serpapi\Services\SerpApiService::class)) {
            return ['success' => false, 'message' => 'SerpAPI package not available.', 'data' => null];
        }

        $start = ($page - 1) * max($perPage, 1);
        return app(\hexa_package_serpapi\Services\SerpApiService::class)->searchImages($query, $perPage, $start, 'photo');
    }

    /**
     * @return array<string, mixed>
     */
    private function probePhotoAsset(string $url): array
    {
        if ($url === '') {
            return [];
        }

        try {
            $response = Http::connectTimeout(3)
                ->timeout(5)
                ->withHeaders(['Accept' => 'image/*'])
                ->head($url);

            if (!$response->successful()) {
                $response = Http::connectTimeout(3)
                    ->timeout(5)
                    ->withHeaders([
                        'Accept' => 'image/*',
                        'Range' => 'bytes=0-0',
                    ])
                    ->get($url);
            }

            if (!$response->successful()) {
                return [];
            }

            $contentType = $response->header('Content-Type');
            if (is_array($contentType)) {
                $contentType = $contentType[0] ?? null;
            }

            $contentLength = $response->header('Content-Length');
            if (is_array($contentLength)) {
                $contentLength = $contentLength[0] ?? null;
            }

            return [
                'mime_type' => $contentType ? strtolower(trim(explode(';', $contentType)[0])) : null,
                'file_size_bytes' => is_numeric($contentLength) ? (int) $contentLength : null,
            ];
        } catch (\Throwable $e) {
            return [];
        }
    }
}
