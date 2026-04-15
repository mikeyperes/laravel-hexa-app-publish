<?php

namespace hexa_app_publish\Discovery\Media\Services;

use hexa_core\Models\Setting;
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
     * @return array{success: bool, photos: array, totals: array, errors: array, timings: array, total_ms: int, message: string}
     */
    public function searchPhotos(string $query, array $sources = ['pexels', 'unsplash', 'pixabay'], int $perPage = 15, int $page = 1): array
    {
        $result = $this->searchPhotosBatch([
            [
                'key' => 'single',
                'query' => $query,
                'per_page' => $perPage,
                'page' => $page,
            ],
        ], $sources);

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
     * @return array{success: bool, results: array<string, array>, total_ms: int}
     */
    public function searchPhotosBatch(array $queries, array $sources = ['pexels', 'unsplash', 'pixabay']): array
    {
        $normalizedSources = collect($sources)
            ->map(fn ($source) => strtolower((string) $source))
            ->filter(fn ($source) => in_array($source, ['pexels', 'unsplash', 'pixabay'], true))
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
        foreach ($normalizedQueries as $item) {
            foreach ($normalizedSources as $source) {
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
            }
        }

        $startedAt = microtime(true);

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

        foreach ($results as &$bucket) {
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

    private function requestDefinition(string $source, string $query, int $perPage, int $page): ?array
    {
        return match ($source) {
            'pexels' => $this->pexelsRequestDefinition($query, $perPage, $page),
            'unsplash' => $this->unsplashRequestDefinition($query, $perPage, $page),
            'pixabay' => $this->pixabayRequestDefinition($query, $perPage, $page),
            default => null,
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
}
