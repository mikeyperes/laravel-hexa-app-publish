<?php

namespace hexa_app_publish\Discovery\Sources\Services;

use hexa_app_publish\Discovery\Sources\Models\ScrapeLog;
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

        // AI extraction methods — delegate to Claude, GPT, or Grok
        if ($method === 'claude' || $method === 'gpt' || $method === 'grok') {
            return $this->extractWithAi($url, $method, $minWords);
        }

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

            $fetchInfo = $extraction['fetch_info'] ?? [];
            $fallbackUsed = !$extraction['success'] ? null : (str_contains($extraction['message'] ?? '', 'fallback') ? 'googlebot' : null);

            $result = [
                'success'        => $extraction['success'],
                'message'        => $extraction['message'] ?? '',
                'url'            => $url,
                'title'          => $extraction['data']['title'] ?? '',
                'text'           => $extraction['data']['content_text'] ?? '',
                'word_count'     => $extraction['data']['word_count'] ?? 0,
                'formatted_html' => $extraction['data']['content_formatted'] ?? '',
                'fetch_info'     => $fetchInfo,
                'method_used'    => $fetchInfo['method'] ?? $method,
                'response_time'  => $fetchInfo['response_time_ms'] ?? null,
                'http_status'    => $fetchInfo['http_status'] ?? null,
                'fallback_tried' => $fallbackUsed,
            ];

            $this->logScrape($url, $method, $userAgent, $timeout, $retries, $result);
            return $result;
        } catch (\Exception $e) {
            Log::warning("[SourceExtraction] Failed for {$url}: " . $e->getMessage());
            $fail = $this->fail($url, $e->getMessage());
            $this->logScrape($url, $method, $userAgent, $timeout, $retries, $fail);
            return $fail;
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
     * Log a scrape to the activity table.
     */
    private function logScrape(string $url, string $method, string $userAgent, int $timeout, int $retries, array $result): void
    {
        try {
            $domain = parse_url($url, PHP_URL_HOST) ?: 'unknown';
            ScrapeLog::create([
                'user_id'          => auth()->id(),
                'url'              => Str::limit($url, 2048),
                'domain'           => $domain,
                'method'           => $result['method_used'] ?? $method,
                'user_agent'       => $userAgent,
                'timeout'          => $timeout,
                'retries'          => $retries,
                'http_status'      => $result['http_status'] ?? null,
                'response_time_ms' => $result['response_time'] ?? null,
                'word_count'       => $result['word_count'] ?? null,
                'success'          => $result['success'],
                'error_message'    => $result['success'] ? null : Str::limit($result['message'] ?? '', 1000),
                'fallback_used'    => $result['fallback_tried'] ?? null,
                'source'           => 'pipeline',
            ]);
        } catch (\Throwable $e) {
            Log::warning('[ScrapeLog] Failed to log: ' . $e->getMessage());
        }
    }

    /**
     * Extract article content using Claude AI or GPT with web search.
     *
     * @param string $url
     * @param string $provider 'claude' or 'gpt'
     * @param int $minWords
     * @return array
     */
    private function extractWithAi(string $url, string $provider, int $minWords = 50): array
    {
        $systemPrompt = "You are an article content extractor. Given a URL, use web search to fetch the full article content from that page. "
            . "Return a JSON object with: title (the article headline), text (the full article body as plain text), html (the article body as clean HTML with paragraphs). "
            . "Extract ONLY the article content — no navigation, ads, sidebars, or comments. "
            . "Output ONLY the JSON object, no other text.";

        $userMessage = "Extract the full article content from this URL: {$url}";

        try {
            if ($provider === 'claude') {
                if (!class_exists(\hexa_package_anthropic\Services\AnthropicService::class)) {
                    return $this->fail($url, 'Anthropic package not available.');
                }
                $ai = app(\hexa_package_anthropic\Services\AnthropicService::class);

                // Use web search tool for Claude
                $key = \hexa_core\Models\Setting::getValue('anthropic_api_key');
                if (!$key) {
                    return $this->fail($url, 'No Anthropic API key configured.');
                }

                $response = \Illuminate\Support\Facades\Http::withHeaders([
                    'x-api-key' => $key,
                    'anthropic-version' => '2023-06-01',
                    'Content-Type' => 'application/json',
                ])->timeout(120)->post('https://api.anthropic.com/v1/messages', [
                    'model' => 'claude-haiku-4-5-20251001',
                    'max_tokens' => 4096,
                    'system' => $systemPrompt,
                    'tools' => [['type' => 'web_search_20250305', 'name' => 'web_search', 'max_uses' => 5]],
                    'messages' => [['role' => 'user', 'content' => $userMessage]],
                ]);

                if (!$response->successful()) {
                    $error = $response->json();
                    return $this->fail($url, 'Claude error: ' . ($error['error']['message'] ?? "HTTP {$response->status()}"));
                }

                $data = $response->json();
                $textContent = '';
                foreach (($data['content'] ?? []) as $block) {
                    if (($block['type'] ?? '') === 'text') {
                        $textContent .= $block['text'];
                    }
                }
            } elseif ($provider === 'grok') {
                // Grok — use chat via xAI API
                if (!class_exists(\hexa_package_grok\Services\GrokService::class)) {
                    return $this->fail($url, 'Grok package not available.');
                }
                $ai = app(\hexa_package_grok\Services\GrokService::class);
                $result = $ai->chat($systemPrompt, $userMessage, 'grok-3-mini', 0.3, 4096);

                if (!$result['success']) {
                    return $this->fail($url, $result['message']);
                }
                $textContent = $result['data']['content'] ?? '';
            } else {
                // GPT — use chat (no native web search, but can process URL context)
                if (!class_exists(\hexa_package_chatgpt\Services\ChatGptService::class)) {
                    return $this->fail($url, 'ChatGPT package not available.');
                }
                $ai = app(\hexa_package_chatgpt\Services\ChatGptService::class);
                $result = $ai->chat($systemPrompt, $userMessage, 'gpt-4o', 0.3, 4096);

                if (!$result['success']) {
                    return $this->fail($url, $result['message']);
                }
                $textContent = $result['data']['content'] ?? '';
            }

            // Parse JSON from AI response
            $parsed = null;
            if (preg_match('/\{.*\}/s', $textContent, $matches)) {
                $parsed = json_decode($matches[0], true);
            }

            if (!$parsed || empty($parsed['text'])) {
                return $this->fail($url, 'AI could not extract article content.');
            }

            $title = $parsed['title'] ?? '';
            $text = $parsed['text'] ?? '';
            $html = $parsed['html'] ?? '<p>' . nl2br(e($text)) . '</p>';
            $wordCount = str_word_count($text);

            if ($wordCount < $minWords) {
                return [
                    'success'        => false,
                    'message'        => "AI extracted only {$wordCount} words (minimum: {$minWords}).",
                    'url'            => $url,
                    'title'          => $title,
                    'text'           => $text,
                    'word_count'     => $wordCount,
                    'formatted_html' => $html,
                    'fetch_info'     => ['method' => $provider],
                ];
            }

            return [
                'success'        => true,
                'message'        => "Extracted {$wordCount} words via {$provider}.",
                'url'            => $url,
                'title'          => $title,
                'text'           => $text,
                'word_count'     => $wordCount,
                'formatted_html' => $html,
                'fetch_info'     => ['method' => $provider],
            ];
        } catch (\Exception $e) {
            Log::warning("[SourceExtraction] AI extraction failed for {$url}: " . $e->getMessage());
            return $this->fail($url, 'AI extraction error: ' . $e->getMessage());
        }
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
