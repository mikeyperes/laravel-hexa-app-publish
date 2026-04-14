<?php

namespace hexa_app_publish\Discovery\Search\Http\Controllers;

use hexa_core\Http\Controllers\Controller;
use hexa_app_publish\Discovery\Sources\Services\SourceDiscoveryService;
use hexa_package_pexels\Services\PexelsService;
use hexa_package_unsplash\Services\UnsplashService;
use hexa_package_pixabay\Services\PixabayService;
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
            'per_page' => 'integer|min:1|max:30',
            'sources'  => 'array',
        ]);

        $query   = $request->input('query');
        $perPage = (int) $request->input('per_page', 10);
        $selectedSources = $request->input('sources', ['pexels', 'unsplash', 'pixabay']);

        $allPhotos = [];
        $errors = [];
        $totals = [];
        $timings = [];

        $serviceMap = [
            'pexels'   => PexelsService::class,
            'unsplash' => UnsplashService::class,
            'pixabay'  => PixabayService::class,
        ];

        foreach ($selectedSources as $source) {
            if (!isset($serviceMap[$source])) continue;
            $start = microtime(true);
            try {
                $result = app($serviceMap[$source])->searchPhotos($query, $perPage);
                $elapsed = round((microtime(true) - $start) * 1000);
                $timings[$source] = $elapsed;
                if ($result['success'] && !empty($result['data']['photos'])) {
                    $allPhotos = array_merge($allPhotos, $result['data']['photos']);
                    $totals[$source] = $result['data']['total'] ?? 0;
                } elseif (!$result['success']) {
                    $errors[] = ucfirst($source) . ': ' . ($result['message'] ?? 'Failed') . " ({$elapsed}ms)";
                }
            } catch (\Exception $e) {
                $elapsed = round((microtime(true) - $start) * 1000);
                $timings[$source] = $elapsed;
                $errors[] = ucfirst($source) . ': ' . $e->getMessage() . " ({$elapsed}ms)";
            }
        }

        $totalTime = array_sum($timings);

        return response()->json([
            'success' => count($allPhotos) > 0,
            'message' => count($allPhotos) . ' photos in ' . $totalTime . 'ms (' . implode(', ', array_map(fn ($k, $v) => $k . ':' . $v . 'ms', array_keys($timings), $timings)) . ')',
            'data'    => [
                'photos'  => $allPhotos,
                'totals'  => $totals,
                'errors'  => $errors,
                'timings' => $timings,
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
            'per_page' => 'integer|min:1|max:20',
        ]);

        $query = $request->input('query');
        $perPage = (int) $request->input('per_page', 10);
        $result = null;
        $provider = null;
        $timing = 0;

        $useGoogle = \hexa_core\Models\Setting::getValue('use_google_image_search', '0') === '1';
        $useSerp = \hexa_core\Models\Setting::getValue('use_serpapi_search', '0') === '1';
        $fallback = \hexa_core\Models\Setting::getValue('google_fallback_serpapi', '1') === '1';

        // Try Google CSE first
        if ($useGoogle && class_exists(\hexa_package_google_cse\Services\GoogleCseService::class)) {
            $cse = app(\hexa_package_google_cse\Services\GoogleCseService::class);
            if (!$cse->isQuotaExhausted()) {
                $start = microtime(true);
                $result = $cse->searchImages($query, $perPage);
                $timing = round((microtime(true) - $start) * 1000);
                $provider = 'google-cse';

                if ($result['success']) {
                    $result['data']['provider'] = 'google-cse';
                    $result['data']['timing_ms'] = $timing;
                    $result['message'] .= " ({$timing}ms via Google CSE)";
                    return response()->json($result);
                }

                // Quota exhausted during this request
                if (!empty($result['quota_exhausted']) && $fallback && $useSerp) {
                    $result = null; // fall through to SerpAPI
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
            $start = microtime(true);
            $result = $serp->searchImages($query, $perPage);
            $timing = round((microtime(true) - $start) * 1000);
            $provider = 'serpapi';

            if ($result['success']) {
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
