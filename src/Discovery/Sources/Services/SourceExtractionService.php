<?php

namespace hexa_app_publish\Discovery\Sources\Services;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

/**
 * SourceExtractionService — single source of truth for URL content extraction.
 *
 * Wraps ArticleExtractorService with normalized output and auto-fallback.
 * Replaces duplicated extraction logic from PipelineController::checkSources(),
 * CampaignRunService extract loop, and PublishArticleController::scrapeUrl().
 */
class SourceExtractionService
{
    /**
     * Extract content from a single URL.
     *
     * @param string $url
     * @param array $options {
     *     @type string $method     Extraction method: auto|readability|css|regex (default: auto)
     *     @type string $user_agent UA string: chrome|googlebot (default: chrome)
     *     @type int    $retries    Retry count (default: 1)
     *     @type int    $timeout    Request timeout in seconds (default: 20)
     *     @type int    $min_words  Minimum word count for pass (default: 50)
     *     @type bool   $auto_fallback Retry with googlebot UA on failure (default: true)
     * }
     * @return array{success: bool, message: string, url: string, title: string, text: string, word_count: int, formatted_html: string, fetch_info: array|null}
     */
    public function extract(string $url, array $options = []): array
    {
        $method = $options['method'] ?? 'auto';
        $userAgent = $options['user_agent'] ?? 'chrome';
        $retries = $options['retries'] ?? 1;
        $timeout = $options['timeout'] ?? 20;
        $minWords = $options['min_words'] ?? 50;
        $autoFallback = $options['auto_fallback'] ?? true;

        $extractor = $this->resolveExtractor();
        if (!$extractor) {
            return $this->fail($url, 'ArticleExtractorService not available.');
        }

        try {
            $extraction = $extractor->extract($url, $method, null, [
                'user_agent' => $userAgent,
                'retries'    => $retries,
                'timeout'    => $timeout,
                'min_words'  => $minWords,
            ]);

            // Auto-fallback: if failed, retry with googlebot UA
            if (!$extraction['success'] && $autoFallback && $userAgent !== 'googlebot') {
                $extraction = $extractor->extract($url, $method, null, [
                    'user_agent' => 'googlebot',
                    'retries'    => $retries,
                    'timeout'    => $timeout,
                    'min_words'  => $minWords,
                ]);
                if ($extraction['success']) {
                    $extraction['message'] = 'Extracted via fallback (Googlebot). ' . $extraction['message'];
                }
            }

            return [
                'success'        => $extraction['success'],
                'message'        => $extraction['message'] ?? '',
                'url'            => $url,
                'title'          => $extraction['data']['title'] ?? '',
                'text'           => $extraction['data']['content_text'] ?? '',
                'word_count'     => $extraction['data']['word_count'] ?? 0,
                'formatted_html' => $extraction['data']['content_formatted'] ?? '',
                'fetch_info'     => $extraction['fetch_info'] ?? null,
            ];
        } catch (\Exception $e) {
            Log::warning("[SourceExtraction] Failed for {$url}: " . $e->getMessage());
            return $this->fail($url, $e->getMessage());
        }
    }

    /**
     * Extract content from multiple URLs. Returns per-URL results.
     *
     * @param array $urls
     * @param array $options Same as extract()
     * @return array{results: array, pass_count: int, total: int}
     */
    public function extractMultiple(array $urls, array $options = []): array
    {
        $results = [];
        $passCount = 0;

        foreach ($urls as $url) {
            $result = $this->extract($url, $options);
            $results[] = $result;
            if ($result['success']) {
                $passCount++;
            }
        }

        return [
            'results'    => $results,
            'pass_count' => $passCount,
            'total'      => count($urls),
        ];
    }

    /**
     * Extract and return only the source text data (for campaign/spin use).
     *
     * @param array $urls
     * @param array $options
     * @return array Array of ['url' => ..., 'title' => ..., 'text' => ...]
     */
    public function extractTexts(array $urls, array $options = []): array
    {
        $texts = [];
        foreach ($urls as $url) {
            $result = $this->extract($url, $options);
            if ($result['success'] && !empty($result['text'])) {
                $texts[] = [
                    'url'   => $result['url'],
                    'title' => $result['title'],
                    'text'  => $result['text'],
                ];
            }
        }
        return $texts;
    }

    /**
     * @param string $url
     * @param string $message
     * @return array
     */
    private function fail(string $url, string $message): array
    {
        return [
            'success'        => false,
            'message'        => $message,
            'url'            => $url,
            'title'          => '',
            'text'           => '',
            'word_count'     => 0,
            'formatted_html' => '',
            'fetch_info'     => null,
        ];
    }

    /**
     * @return \hexa_package_article_extractor\Services\ArticleExtractorService|null
     */
    private function resolveExtractor()
    {
        if (!class_exists(\hexa_package_article_extractor\Services\ArticleExtractorService::class)) {
            return null;
        }
        return app(\hexa_package_article_extractor\Services\ArticleExtractorService::class);
    }
}
