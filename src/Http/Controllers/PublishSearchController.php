<?php

namespace hexa_app_publish\Http\Controllers;

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
class PublishSearchController extends Controller
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

        return view('app-publish::search.images', [
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

        // Pexels
        if (in_array('pexels', $selectedSources)) {
            try {
                $result = app(PexelsService::class)->searchPhotos($query, $perPage);
                if ($result['success'] && !empty($result['data']['photos'])) {
                    $allPhotos = array_merge($allPhotos, $result['data']['photos']);
                    $totals['pexels'] = $result['data']['total'] ?? 0;
                } elseif (!$result['success']) {
                    $errors[] = 'Pexels: ' . ($result['message'] ?? 'Failed');
                }
            } catch (\Exception $e) {
                $errors[] = 'Pexels: ' . $e->getMessage();
            }
        }

        // Unsplash
        if (in_array('unsplash', $selectedSources)) {
            try {
                $result = app(UnsplashService::class)->searchPhotos($query, $perPage);
                if ($result['success'] && !empty($result['data']['photos'])) {
                    $allPhotos = array_merge($allPhotos, $result['data']['photos']);
                    $totals['unsplash'] = $result['data']['total'] ?? 0;
                } elseif (!$result['success']) {
                    $errors[] = 'Unsplash: ' . ($result['message'] ?? 'Failed');
                }
            } catch (\Exception $e) {
                $errors[] = 'Unsplash: ' . $e->getMessage();
            }
        }

        // Pixabay
        if (in_array('pixabay', $selectedSources)) {
            try {
                $result = app(PixabayService::class)->searchPhotos($query, $perPage);
                if ($result['success'] && !empty($result['data']['photos'])) {
                    $allPhotos = array_merge($allPhotos, $result['data']['photos']);
                    $totals['pixabay'] = $result['data']['total'] ?? 0;
                } elseif (!$result['success']) {
                    $errors[] = 'Pixabay: ' . ($result['message'] ?? 'Failed');
                }
            } catch (\Exception $e) {
                $errors[] = 'Pixabay: ' . $e->getMessage();
            }
        }

        return response()->json([
            'success' => count($allPhotos) > 0,
            'message' => count($allPhotos) . ' photos found across ' . count($totals) . ' source(s).',
            'data'    => [
                'photos' => $allPhotos,
                'totals' => $totals,
                'errors' => $errors,
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
        return view('app-publish::search.articles', [
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
            $request->input('query', ''),
            [
                'sources'  => $request->input('sources', ['gnews', 'newsdata', 'currents_news']),
                'per_page' => (int) $request->input('per_page', 10),
                'mode'     => $request->input('mode', 'keyword'),
                'category' => $request->input('category'),
                'country'  => $request->input('country', 'us'),
            ]
        );

        return response()->json($result);
    }
}
