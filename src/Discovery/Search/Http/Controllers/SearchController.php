<?php

namespace hexa_app_publish\Discovery\Search\Http\Controllers;

use hexa_core\Http\Controllers\Controller;
use hexa_app_publish\Discovery\Media\Services\MediaSearchService;
use hexa_app_publish\Discovery\Sources\Services\SourceDiscoveryService;
use hexa_app_publish\Publishing\Articles\Models\PublishArticle;
use hexa_app_publish\Publishing\Articles\Services\ArticleActivityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * PublishSearchController — unified search across multiple APIs.
 *
 * Image search remains here (Pexels/Unsplash/Pixabay).
 * Article search delegates to SourceDiscoveryService.
 */
class SearchController extends Controller
{
    /**
     * Show the image search page.
     *
     * @return \Illuminate\View\View
     */
    public function images()
    {
        $sources = [
            'pexels'   => !empty(\hexa_core\Models\Setting::getValue('pexels_api_key', '')),
            'unsplash' => !empty(\hexa_core\Models\Setting::getValue('unsplash_api_key', '')),
            'pixabay'  => !empty(\hexa_core\Models\Setting::getValue('pixabay_api_key', '')),
        ];

        return view('app-publish::discovery.search.images', [
            'sources' => $sources,
        ]);
    }

    /**
     * AJAX: Search all configured image APIs in parallel and return merged results.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function searchImages(Request $request): JsonResponse
    {
        $request->validate([
            'query'    => 'required|string|max:255',
            'draft_id' => 'nullable|integer|exists:publish_articles,id',
            'per_page' => 'integer|min:1|max:30',
            'page'     => 'integer|min:1|max:50',
            'sources'  => 'array',
            'quality_context' => 'nullable|in:inline,featured,general',
            'probe_quality' => 'nullable|boolean',
        ]);

        $query   = $request->input('query');
        $perPage = (int) $request->input('per_page', 10);
        $page    = (int) $request->input('page', 1);
        $selectedSources = $request->input('sources', ['pexels', 'unsplash', 'pixabay']);
        $qualityContext = (string) $request->input('quality_context', 'inline');
        $probeQuality = $request->boolean('probe_quality', false);
        $draft = $this->resolveDraft($request->input('draft_id'));
        $result = app(MediaSearchService::class)->searchPhotos(
            $query,
            $selectedSources,
            $perPage,
            $page,
            $qualityContext,
            $probeQuality
        );

        app(ArticleActivityService::class)->record($draft, [
            'activity_group' => 'image-search:' . md5($query . '|' . $qualityContext),
            'activity_type' => 'image_search',
            'stage' => 'images',
            'substage' => 'search',
            'status' => $result['success'] ? 'success' : 'failed',
            'provider' => implode(',', $selectedSources),
            'method' => 'searchImages',
            'success' => (bool) $result['success'],
            'message' => count($result['photos']) . ' image candidate(s) returned.',
            'request_payload' => [
                'query' => $query,
                'sources' => array_values($selectedSources),
                'quality_context' => $qualityContext,
                'probe_quality' => $probeQuality,
            ],
            'response_payload' => [
                'totals' => $result['totals'],
                'errors' => $result['errors'],
                'timings' => $result['timings'] ?? [],
                'photos' => array_slice((array) ($result['photos'] ?? []), 0, 12),
            ],
        ]);

        return response()->json([
            'success' => $result['success'],
            'message' => count($result['photos']) . ' photos in ' . ($result['total_ms'] ?? 0) . 'ms',
            'data'    => [
                'photos'  => $result['photos'],
                'totals'  => $result['totals'],
                'errors'  => $result['errors'],
                'timings' => $result['timings'] ?? [],
            ],
        ]);
    }

    public function searchImagesBatch(Request $request): JsonResponse
    {
        $request->validate([
            'queries' => 'required|array|min:1|max:20',
            'draft_id' => 'nullable|integer|exists:publish_articles,id',
            'queries.*.key' => 'nullable|string|max:100',
            'queries.*.query' => 'required|string|max:255',
            'queries.*.per_page' => 'nullable|integer|min:1|max:30',
            'queries.*.page' => 'nullable|integer|min:1|max:50',
            'sources' => 'nullable|array',
            'quality_context' => 'nullable|in:inline,featured,general',
            'probe_quality' => 'nullable|boolean',
        ]);

        $draft = $this->resolveDraft($request->input('draft_id'));
        $result = app(MediaSearchService::class)->searchPhotosBatch(
            $request->input('queries', []),
            $request->input('sources', ['pexels', 'unsplash', 'pixabay']),
            (string) $request->input('quality_context', 'inline'),
            $request->boolean('probe_quality', false)
        );

        app(ArticleActivityService::class)->record($draft, [
            'activity_group' => 'image-search-batch:' . md5(json_encode($request->input('queries', []))),
            'activity_type' => 'image_search',
            'stage' => 'images',
            'substage' => 'batch_search',
            'status' => $result['success'] ? 'success' : 'failed',
            'provider' => implode(',', (array) $request->input('sources', ['pexels', 'unsplash', 'pixabay'])),
            'method' => 'searchImagesBatch',
            'success' => (bool) $result['success'],
            'message' => 'Batch image search completed.',
            'request_payload' => [
                'queries' => $request->input('queries', []),
                'quality_context' => (string) $request->input('quality_context', 'inline'),
                'probe_quality' => $request->boolean('probe_quality', false),
            ],
            'response_payload' => [
                'results' => $result['results'],
                'total_ms' => $result['total_ms'] ?? 0,
            ],
        ]);

        return response()->json([
            'success' => $result['success'],
            'message' => 'Batch image search completed in ' . ($result['total_ms'] ?? 0) . 'ms',
            'data' => [
                'results' => $result['results'],
                'total_ms' => $result['total_ms'] ?? 0,
            ],
        ]);
    }

    /**
     * Search Google Images (CSE first, SerpAPI fallback).
     */
    public function searchGoogleImages(Request $request): JsonResponse
    {
        $request->validate([
            'query'    => 'required|string|max:255',
            'draft_id' => 'nullable|integer|exists:publish_articles,id',
            'per_page' => 'integer|min:1|max:20',
            'start'    => 'integer|min:0|max:100',
            'quality_context' => 'nullable|in:inline,featured,general',
            'probe_quality' => 'nullable|boolean',
        ]);

        $query = $request->input('query');
        $perPage = (int) $request->input('per_page', 10);
        $start = (int) $request->input('start', 0);
        $qualityContext = (string) $request->input('quality_context', 'featured');
        $probeQuality = $request->boolean('probe_quality', true);
        $draft = $this->resolveDraft($request->input('draft_id'));
        $result = null;
        $provider = null;
        $timing = 0;
        $mediaSearch = app(MediaSearchService::class);

        $useGoogle = \hexa_core\Models\Setting::getValue('use_google_image_search', '0') === '1';
        $useSerp = \hexa_core\Models\Setting::getValue('use_serpapi_search', '0') === '1';
        $fallback = \hexa_core\Models\Setting::getValue('google_fallback_serpapi', '1') === '1';

        // Try Google CSE first
        if ($useGoogle && class_exists(\hexa_package_google_cse\Services\GoogleCseService::class)) {
            $cse = app(\hexa_package_google_cse\Services\GoogleCseService::class);
            if (!$cse->isQuotaExhausted()) {
                $t0 = microtime(true);
                $result = $cse->searchImages($query, $perPage, $start + 1);
                $timing = round((microtime(true) - $t0) * 1000);
                $provider = 'google-cse';

                if ($result['success']) {
                    $this->recordGoogleImageAudit($draft, $query, $qualityContext, $provider, $result);
                    $result['data']['photos'] = $mediaSearch->rankPhotos(
                        (array) ($result['data']['photos'] ?? []),
                        $qualityContext,
                        $probeQuality
                    );
                    $result['data']['provider'] = 'google-cse';
                    $result['data']['timing_ms'] = $timing;
                    $result['message'] .= " ({$timing}ms via Google CSE)";
                    return response()->json($result);
                }

                // CSE failed — fall through to SerpAPI if enabled
                if ($fallback && $useSerp) {
                    $result = null;
                }
            } elseif ($fallback && $useSerp) {
                // Already exhausted, fall through
            } else {
                return response()->json(['success' => false, 'message' => 'Google CSE daily quota exhausted. Enable SerpAPI fallback in settings.', 'data' => null]);
            }
        }

        // SerpAPI (primary or fallback)
        if (!$result && $useSerp && class_exists(\hexa_package_serpapi\Services\SerpApiService::class)) {
            $serp = app(\hexa_package_serpapi\Services\SerpApiService::class);
            $t0 = microtime(true);
            $result = $serp->searchImages($query, $perPage, $start, 'photo');
            $timing = round((microtime(true) - $t0) * 1000);
            $provider = 'serpapi';

            if ($result['success']) {
                $this->recordGoogleImageAudit($draft, $query, $qualityContext, $provider, $result);
                $result['data']['photos'] = $mediaSearch->rankPhotos(
                    (array) ($result['data']['photos'] ?? []),
                    $qualityContext,
                    $probeQuality
                );
                $result['data']['provider'] = 'serpapi';
                $result['data']['timing_ms'] = $timing;
                $result['message'] .= " ({$timing}ms via SerpAPI)";
                return response()->json($result);
            }
        }

        if (!$result) {
            $msg = 'No Google image search provider configured.';
            if (!$useGoogle && !$useSerp) $msg .= ' Enable Google CSE or SerpAPI in Publishing Settings.';
            return response()->json(['success' => false, 'message' => $msg, 'data' => null]);
        }

        return response()->json($result);
    }

    private function resolveDraft(mixed $draftId): ?PublishArticle
    {
        if (!is_numeric($draftId) || (int) $draftId <= 0) {
            return null;
        }

        return PublishArticle::find((int) $draftId);
    }

    /**
     * @param array<string, mixed> $result
     */
    private function recordGoogleImageAudit(?PublishArticle $draft, string $query, string $qualityContext, ?string $provider, array $result): void
    {
        app(ArticleActivityService::class)->record($draft, [
            'activity_group' => 'image-search-google:' . md5($query . '|' . $qualityContext),
            'activity_type' => 'image_search',
            'stage' => 'images',
            'substage' => 'google_search',
            'status' => ($result['success'] ?? false) ? 'success' : 'failed',
            'provider' => $provider,
            'method' => 'searchGoogleImages',
            'success' => (bool) ($result['success'] ?? false),
            'message' => $result['message'] ?? null,
            'request_payload' => [
                'query' => $query,
                'quality_context' => $qualityContext,
            ],
            'response_payload' => [
                'provider' => $provider,
                'timing_ms' => $result['data']['timing_ms'] ?? null,
                'photos' => array_slice((array) ($result['data']['photos'] ?? []), 0, 12),
            ],
        ]);
    }

    /**
     * Show the article search page.
     *
     * @return \Illuminate\View\View
     */
    public function articles()
    {
        $sourceDiscovery = app(SourceDiscoveryService::class);
        return view('app-publish::discovery.search.articles', [
            'sources' => $sourceDiscovery->availableProviders(),
        ]);
    }

    /**
     * AJAX: Search all configured news APIs and return merged article results.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function searchArticles(Request $request): JsonResponse
    {
        $request->validate([
            'query'    => 'nullable|string|max:255',
            'category' => 'nullable|string|max:50',
            'country'  => 'nullable|string|max:5',
            'mode'     => 'nullable|in:keyword,local,trending,genre',
            'per_page' => 'integer|min:1|max:50',
            'sources'  => 'array',
        ]);

        $result = app(SourceDiscoveryService::class)->searchArticles(
            (string) $request->input('query', ''),
            [
                'sources'  => $request->input('sources', ['google-news-rss', 'gnews', 'newsdata', 'currents_news']),
                'per_page' => (int) $request->input('per_page', 10),
                'mode'     => $request->input('mode', 'keyword'),
                'category' => $request->input('category'),
                'country'  => $request->input('country', 'us'),
            ]
        );

        return response()->json($result);
    }
}
