<?php

namespace hexa_app_publish\Publishing\Pipeline\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class PressReleasePhotoDetectionService
{
    public function detectFromUrl(string $url): array
    {
        $log = [];
        $log[] = $this->entry('info', 'Starting public photo detection.', ['url' => $url]);

        try {
            $response = Http::timeout(30)
                ->withHeaders(['User-Agent' => 'Mozilla/5.0 Hexa Press Release Photo Detector'])
                ->get($url);

            if (!$response->successful()) {
                $log[] = $this->entry('error', 'Failed to fetch public URL.', ['status' => $response->status()]);

                return [
                    'success' => false,
                    'photos' => [],
                    'log' => $log,
                    'message' => 'Failed to fetch public URL for photo detection.',
                ];
            }

            $html = (string) $response->body();
            $photos = $this->extractImageCandidates($html, $url);

            $log[] = $this->entry('success', 'Public photo detection complete.', [
                'count' => count($photos),
            ]);

            return [
                'success' => true,
                'photos' => $photos,
                'log' => $log,
                'message' => count($photos) . ' photo(s) detected from public URL.',
            ];
        } catch (\Throwable $e) {
            $log[] = $this->entry('error', 'Photo detection failed: ' . $e->getMessage());

            return [
                'success' => false,
                'photos' => [],
                'log' => $log,
                'message' => 'Photo detection failed: ' . $e->getMessage(),
            ];
        }
    }

    private function extractImageCandidates(string $html, string $baseUrl): array
    {
        $seen = [];
        $photos = [];

        $capture = function (string $url, string $source, string $alt = '', string $caption = '') use (&$seen, &$photos) {
            if ($url === '' || isset($seen[$url])) {
                return;
            }

            $lower = strtolower(parse_url($url, PHP_URL_PATH) ?: '');
            if ($lower === '' || !preg_match('/\.(jpg|jpeg|png|webp|gif)(\?.*)?$/', $lower)) {
                return;
            }

            $seen[$url] = true;
            $photos[] = [
                'url' => $url,
                'thumbnail_url' => $url,
                'alt_text' => trim($alt),
                'caption' => trim($caption),
                'source' => $source,
            ];
        };

        preg_match_all('/<meta[^>]+property=["\']og:image["\'][^>]+content=["\']([^"\']+)["\']/i', $html, $ogMatches);
        foreach ($ogMatches[1] ?? [] as $url) {
            $capture($this->absoluteUrl($url, $baseUrl), 'og:image');
        }

        preg_match_all('/<meta[^>]+name=["\']twitter:image["\'][^>]+content=["\']([^"\']+)["\']/i', $html, $twitterMatches);
        foreach ($twitterMatches[1] ?? [] as $url) {
            $capture($this->absoluteUrl($url, $baseUrl), 'twitter:image');
        }

        preg_match_all('/<img[^>]+src=["\']([^"\']+)["\'][^>]*>/i', $html, $imgMatches, PREG_SET_ORDER);
        foreach ($imgMatches as $match) {
            $src = $this->absoluteUrl($match[1] ?? '', $baseUrl);
            $alt = '';
            if (preg_match('/alt=["\']([^"\']*)["\']/i', $match[0], $altMatch)) {
                $alt = $altMatch[1];
            }
            $capture($src, 'img', $alt);
        }

        return array_slice($photos, 0, 12);
    }

    private function absoluteUrl(string $url, string $baseUrl): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }

        if (Str::startsWith($url, ['http://', 'https://'])) {
            return $url;
        }

        if (Str::startsWith($url, '//')) {
            $scheme = parse_url($baseUrl, PHP_URL_SCHEME) ?: 'https';

            return $scheme . ':' . $url;
        }

        $base = rtrim($baseUrl, '/');
        if (Str::startsWith($url, '/')) {
            $parts = parse_url($baseUrl);
            $host = ($parts['scheme'] ?? 'https') . '://' . ($parts['host'] ?? '');
            if (!empty($parts['port'])) {
                $host .= ':' . $parts['port'];
            }

            return $host . $url;
        }

        return $base . '/' . ltrim($url, '/');
    }

    private function entry(string $type, string $message, array $context = []): array
    {
        return [
            'type' => $type,
            'message' => $message,
            'timestamp' => now()->toIso8601String(),
            'context' => $context,
        ];
    }
}
