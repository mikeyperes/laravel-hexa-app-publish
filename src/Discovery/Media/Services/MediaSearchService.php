<?php

namespace hexa_app_publish\Discovery\Media\Services;

/**
 * MediaSearchService — unified photo search across Pexels, Unsplash, Pixabay.
 *
 * Replaces inline photo search logic from ArticleController::searchPhotos()
 * and PublishSearchController::searchImages().
 */
class MediaSearchService
{
    /**
     * Search for photos across configured providers.
     *
     * @param string $query
     * @param array $sources Provider names (pexels, unsplash, pixabay)
     * @param int $perPage Results per provider
     * @return array{success: bool, photos: array, totals: array, errors: array, message: string}
     */
    public function searchPhotos(string $query, array $sources = ['pexels', 'unsplash', 'pixabay'], int $perPage = 15): array
    {
        $allPhotos = [];
        $errors = [];
        $totals = [];

        if (in_array('pexels', $sources) && class_exists(\hexa_package_pexels\Services\PexelsService::class)) {
            try {
                $result = app(\hexa_package_pexels\Services\PexelsService::class)->searchPhotos($query, $perPage);
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

        if (in_array('unsplash', $sources) && class_exists(\hexa_package_unsplash\Services\UnsplashService::class)) {
            try {
                $result = app(\hexa_package_unsplash\Services\UnsplashService::class)->searchPhotos($query, $perPage);
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

        if (in_array('pixabay', $sources) && class_exists(\hexa_package_pixabay\Services\PixabayService::class)) {
            try {
                $result = app(\hexa_package_pixabay\Services\PixabayService::class)->searchPhotos($query, $perPage);
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

        return [
            'success' => count($allPhotos) > 0,
            'photos'  => $allPhotos,
            'totals'  => $totals,
            'errors'  => $errors,
            'message' => count($allPhotos) . ' photos found across ' . count($totals) . ' source(s).',
        ];
    }
}
