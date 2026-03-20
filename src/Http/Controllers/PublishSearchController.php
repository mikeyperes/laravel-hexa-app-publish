<?php

namespace hexa_app_publish\Http\Controllers;

use hexa_core\Http\Controllers\Controller;
use hexa_package_pexels\Services\PexelsService;
use hexa_package_unsplash\Services\UnsplashService;
use hexa_package_pixabay\Services\PixabayService;
use hexa_package_gnews\Services\GNewsService;
use hexa_package_newsdata\Services\NewsDataService;
use hexa_package_currents_news\Services\CurrentsNewsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * PublishSearchController — unified search across multiple APIs.
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
        $sources = [
            'gnews'         => !empty(\hexa_core\Models\Setting::getValue('gnews_api_key', '')),
            'newsdata'      => !empty(\hexa_core\Models\Setting::getValue('newsdata_api_key', '')),
            'currents_news' => !empty(\hexa_core\Models\Setting::getValue('currents_news_api_key', '')),
        ];

        return view('app-publish::search.articles', [
            'sources' => $sources,
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
            'query'    => 'required|string|max:255',
            'per_page' => 'integer|min:1|max:50',
            'sources'  => 'array',
        ]);

        $query   = $request->input('query');
        $perPage = (int) $request->input('per_page', 10);
        $selectedSources = $request->input('sources', ['gnews', 'newsdata', 'currents_news']);

        $allArticles = [];
        $errors = [];
        $totals = [];

        // GNews
        if (in_array('gnews', $selectedSources)) {
            try {
                $result = app(GNewsService::class)->searchArticles($query, min($perPage, 10));
                if ($result['success'] && !empty($result['data']['articles'])) {
                    $allArticles = array_merge($allArticles, $result['data']['articles']);
                    $totals['gnews'] = $result['data']['total'] ?? 0;
                } elseif (!$result['success']) {
                    $errors[] = 'GNews: ' . ($result['message'] ?? 'Failed');
                }
            } catch (\Exception $e) {
                $errors[] = 'GNews: ' . $e->getMessage();
            }
        }

        // NewsData
        if (in_array('newsdata', $selectedSources)) {
            try {
                $result = app(NewsDataService::class)->searchArticles($query, $perPage);
                if ($result['success'] && !empty($result['data']['articles'])) {
                    $allArticles = array_merge($allArticles, $result['data']['articles']);
                    $totals['newsdata'] = $result['data']['total'] ?? 0;
                } elseif (!$result['success']) {
                    $errors[] = 'NewsData: ' . ($result['message'] ?? 'Failed');
                }
            } catch (\Exception $e) {
                $errors[] = 'NewsData: ' . $e->getMessage();
            }
        }

        // Currents News
        if (in_array('currents_news', $selectedSources)) {
            try {
                $result = app(CurrentsNewsService::class)->searchArticles($query);
                if ($result['success'] && !empty($result['data']['articles'])) {
                    $allArticles = array_merge($allArticles, $result['data']['articles']);
                    $totals['currents_news'] = $result['data']['total'] ?? 0;
                } elseif (!$result['success']) {
                    $errors[] = 'Currents News: ' . ($result['message'] ?? 'Failed');
                }
            } catch (\Exception $e) {
                $errors[] = 'Currents News: ' . $e->getMessage();
            }
        }

        // Sort by published date descending
        usort($allArticles, function ($a, $b) {
            return strtotime($b['published_at'] ?? '0') - strtotime($a['published_at'] ?? '0');
        });

        return response()->json([
            'success' => count($allArticles) > 0,
            'message' => count($allArticles) . ' articles found across ' . count($totals) . ' source(s).',
            'data'    => [
                'articles' => $allArticles,
                'totals'   => $totals,
                'errors'   => $errors,
            ],
        ]);
    }
}
