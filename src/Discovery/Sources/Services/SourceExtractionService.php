<?php

namespace hexa_app_publish\Discovery\Sources\Services;

use hexa_app_publish\Discovery\Sources\Models\ScrapeLog;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

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
     *     @type string $method     Extraction method: auto|readability|structured|heuristic|css|regex|jina (default: auto)
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
        $requestedUrl = $url;
        $method = $options['method'] ?? 'auto';
        $userAgent = $options['user_agent'] ?? 'chrome';
        $retries = $options['retries'] ?? 1;
        $timeout = $options['timeout'] ?? 20;
        $minWords = $options['min_words'] ?? 50;
        $autoFallback = $options['auto_fallback'] ?? true;
        $source = (string) ($options['source'] ?? 'pipeline');
        $draftId = isset($options['draft_id']) ? (int) $options['draft_id'] : null;
        $normalization = $this->normalizeSourceUrl($requestedUrl, $timeout);
        $url = $normalization['url'];
        $normalizationMeta = $normalization['meta'];

        // AI extraction methods — delegate to Claude, GPT, Grok, or Gemini
        if (in_array($method, ['claude', 'gpt', 'grok', 'gemini'], true)) {
            $result = $this->extractWithAi($url, $method, $minWords);
            $result = $this->attachNormalizationMeta($result, $requestedUrl, $url, $normalizationMeta);
            $this->logScrape($url, $method, $userAgent, $timeout, $retries, $result, $source, $draftId);
            return $result;
        }

        $extractor = $this->resolveExtractor();
        if (!$extractor) {
            return $this->fail($url, 'ArticleExtractorService not available.');
        }

        try {
            $primaryExtraction = $extractor->extract($url, $method, null, [
                'user_agent' => $userAgent,
                'retries'    => $retries,
                'timeout'    => $timeout,
                'min_words'  => $minWords,
            ]);
            $primaryResult = $this->normalizeResult($url, $method, $primaryExtraction);
            $primaryResult = $this->attachNormalizationMeta($primaryResult, $requestedUrl, $url, $normalizationMeta);
            $this->logScrape($url, $method, $userAgent, $timeout, $retries, $primaryResult, $source, $draftId);

            // Auto-fallback: if failed, retry with googlebot UA and log that request separately.
            if (!$primaryResult['success'] && $autoFallback && $userAgent !== 'googlebot') {
                $fallbackExtraction = $extractor->extract($url, $method, null, [
                    'user_agent' => 'googlebot',
                    'retries'    => $retries,
                    'timeout'    => $timeout,
                    'min_words'  => $minWords,
                ]);

                $fallbackResult = $this->normalizeResult($url, $method, $fallbackExtraction, 'googlebot');
                if ($fallbackResult['success']) {
                    $fallbackResult['message'] = 'Extracted via fallback (Googlebot). ' . ($fallbackResult['message'] ?? '');
                }

                $fallbackResult = $this->attachNormalizationMeta($fallbackResult, $requestedUrl, $url, $normalizationMeta);
                $this->logScrape($url, $method, 'googlebot', $timeout, $retries, $fallbackResult, $source, $draftId);
                return $fallbackResult;
            }

            return $primaryResult;
        } catch (\Exception $e) {
            Log::warning("[SourceExtraction] Failed for {$url}: " . $e->getMessage());
            $fail = $this->fail($url, $e->getMessage());
            $fail = $this->attachNormalizationMeta($fail, $requestedUrl, $url, $normalizationMeta);
            $this->logScrape($url, $method, $userAgent, $timeout, $retries, $fail, $source, $draftId);
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

    private function normalizeResult(string $url, string $requestedMethod, array $extraction, ?string $fallbackUsed = null): array
    {
        $fetchInfo = is_array($extraction['fetch_info'] ?? null) ? $extraction['fetch_info'] : [];

        return [
            'success'        => (bool) ($extraction['success'] ?? false),
            'message'        => (string) ($extraction['message'] ?? ''),
            'url'            => $url,
            'title'          => (string) ($extraction['data']['title'] ?? ''),
            'text'           => (string) ($extraction['data']['content_text'] ?? ''),
            'word_count'     => (int) ($extraction['data']['word_count'] ?? 0),
            'formatted_html' => (string) ($extraction['data']['content_formatted'] ?? ''),
            'fetch_info'     => $fetchInfo,
            'method_used'    => $fetchInfo['method_used'] ?? $fetchInfo['method'] ?? $requestedMethod,
            'response_time'  => $fetchInfo['response_time_ms'] ?? null,
            'http_status'    => $fetchInfo['http_status'] ?? $fetchInfo['status'] ?? null,
            'fallback_tried' => $fallbackUsed ?? ($fetchInfo['fallback_source'] ?? null),
        ];
    }

    /**
     * Log a scrape to the activity table.
     */
    private function logScrape(
        string $url,
        string $method,
        string $userAgent,
        int $timeout,
        int $retries,
        array $result,
        string $source = 'pipeline',
        ?int $draftId = null
    ): void
    {
        try {
            $domain = parse_url($url, PHP_URL_HOST) ?: 'unknown';
            $fetchInfo = is_array($result['fetch_info'] ?? null) ? $result['fetch_info'] : [];

            ScrapeLog::create([
                'user_id'          => auth()->id(),
                'url'              => Str::limit($url, 2048, ''),
                'effective_url'    => !empty($fetchInfo['effective_url']) ? Str::limit((string) $fetchInfo['effective_url'], 65535, '') : null,
                'domain'           => $domain,
                'method'           => $result['method_used'] ?? $method,
                'http_method'      => $fetchInfo['request_method'] ?? 'GET',
                'user_agent'       => $userAgent,
                'request_headers'  => $fetchInfo['request_headers'] ?? null,
                'request_meta'     => $this->buildRequestMeta($url, $method, $userAgent, $timeout, $retries, $fetchInfo),
                'timeout'          => $timeout,
                'retries'          => $retries,
                'http_status'      => $result['http_status'] ?? null,
                'response_reason'  => $fetchInfo['response_reason'] ?? null,
                'response_headers' => $fetchInfo['response_headers'] ?? null,
                'response_meta'    => $this->buildResponseMeta($fetchInfo, $result),
                'response_time_ms' => $result['response_time'] ?? null,
                'word_count'       => $result['word_count'] ?? null,
                'success'          => $result['success'],
                'error_message'    => $result['success'] ? null : Str::limit($result['message'] ?? '', 1000),
                'response_body_snippet' => Str::limit((string) ($fetchInfo['body_snippet'] ?? ''), 8000, ''),
                'fallback_used'    => $result['fallback_tried'] ?? null,
                'attempt_log'      => $fetchInfo['attempt_log'] ?? null,
                'fetch_info'       => $fetchInfo,
                'source'           => $source,
                'draft_id'         => $draftId,
            ]);
        } catch (\Throwable $e) {
            Log::warning('[ScrapeLog] Failed to log: ' . $e->getMessage());
        }
    }

    private function buildRequestMeta(string $url, string $method, string $userAgent, int $timeout, int $retries, array $fetchInfo): array
    {
        return [
            'requested_url' => $fetchInfo['requested_url'] ?? $url,
            'resolved_source_url' => $fetchInfo['resolved_source_url'] ?? $url,
            'effective_url' => $fetchInfo['effective_url'] ?? null,
            'requested_method' => $method,
            'method_used' => $fetchInfo['method'] ?? $method,
            'http_method' => $fetchInfo['request_method'] ?? 'GET',
            'user_agent_key' => $userAgent,
            'resolved_user_agent' => $fetchInfo['resolved_user_agent'] ?? null,
            'timeout' => $timeout,
            'retries' => $retries,
            'attempts' => $fetchInfo['attempts'] ?? null,
            'follow_redirects' => $fetchInfo['follow_redirects'] ?? null,
            'reader_url' => $fetchInfo['reader_url'] ?? null,
            'source_url_normalization' => $fetchInfo['source_url_normalization'] ?? null,
        ];
    }

    private function buildResponseMeta(array $fetchInfo, array $result): array
    {
        return [
            'http_status' => $result['http_status'] ?? null,
            'reason' => $fetchInfo['response_reason'] ?? null,
            'content_type' => $fetchInfo['content_type'] ?? null,
            'content_length' => $fetchInfo['content_length'] ?? null,
            'response_time_ms' => $result['response_time'] ?? null,
            'word_count' => $result['word_count'] ?? null,
            'handler_stats' => $fetchInfo['handler_stats'] ?? null,
            'failure_reason' => $fetchInfo['reason'] ?? null,
            'suggestion' => $fetchInfo['suggestion'] ?? null,
            'fallback_source' => $fetchInfo['fallback_source'] ?? null,
            'origin_status' => $fetchInfo['origin_status'] ?? null,
            'origin_error' => $fetchInfo['origin_error'] ?? null,
            'jina_error' => $fetchInfo['jina_error'] ?? null,
        ];
    }

    /**
     * Extract article content using an AI provider.
     *
     * @param string $url
     * @param string $provider 'claude', 'gpt', 'grok', or 'gemini'
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
            } elseif ($provider === 'gemini') {
                if (!class_exists(\hexa_package_gemini\Services\GeminiService::class)) {
                    return $this->fail($url, 'Gemini package not available.');
                }
                $ai = app(\hexa_package_gemini\Services\GeminiService::class);
                $result = $ai->extractArticle($url, 'gemini-2.5-flash');

                if (!$result['success']) {
                    return $this->fail($url, $result['message']);
                }

                $parsed = $result['data'] ?? [];
                $title = (string) ($parsed['title'] ?? '');
                $text = (string) ($parsed['text'] ?? '');
                $html = (string) ($parsed['html'] ?? ('<p>' . nl2br(e($text)) . '</p>'));
                $wordCount = str_word_count($text);

                if ($wordCount < $minWords) {
                    return [
                        'success' => false,
                        'message' => "AI extracted only {$wordCount} words (minimum: {$minWords}).",
                        'url' => $url,
                        'title' => $title,
                        'text' => $text,
                        'word_count' => $wordCount,
                        'formatted_html' => $html,
                        'fetch_info' => ['method' => $provider],
                    ];
                }

                return [
                    'success' => true,
                    'message' => "Extracted {$wordCount} words via {$provider}.",
                    'url' => $url,
                    'title' => $title,
                    'text' => $text,
                    'word_count' => $wordCount,
                    'formatted_html' => $html,
                    'fetch_info' => ['method' => $provider],
                ];
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

    /**
     * @return array{url: string, meta: array<string, mixed>}
     */
    private function normalizeSourceUrl(string $url, int $timeout): array
    {
        $host = strtolower((string) (parse_url($url, PHP_URL_HOST) ?: ''));
        if ($host !== 'news.google.com') {
            return ['url' => $url, 'meta' => []];
        }

        try {
            $articleId = $this->extractGoogleNewsArticleId($url);
            if (!$articleId) {
                return [
                    'url' => $url,
                    'meta' => [
                        'provider' => 'google-news',
                        'status' => 'missing_article_id',
                    ],
                ];
            }

            $decodedUrl = $this->decodeGoogleNewsArticleId($articleId, $timeout);
            if (!$decodedUrl) {
                return [
                    'url' => $url,
                    'meta' => [
                        'provider' => 'google-news',
                        'status' => 'decode_failed',
                        'article_id' => $articleId,
                    ],
                ];
            }

            return [
                'url' => $decodedUrl,
                'meta' => [
                    'provider' => 'google-news',
                    'status' => 'decoded',
                    'article_id' => $articleId,
                    'requested_url' => $url,
                    'resolved_url' => $decodedUrl,
                ],
            ];
        } catch (\Throwable $e) {
            Log::warning('[SourceExtraction] Google News URL normalization failed: ' . $e->getMessage());

            return [
                'url' => $url,
                'meta' => [
                    'provider' => 'google-news',
                    'status' => 'decode_error',
                    'error' => $e->getMessage(),
                ],
            ];
        }
    }

    private function extractGoogleNewsArticleId(string $url): ?string
    {
        $path = trim((string) parse_url($url, PHP_URL_PATH), '/');
        if ($path === '') {
            return null;
        }

        $segments = explode('/', $path);
        $index = array_search('articles', $segments, true);
        if ($index === false || empty($segments[$index + 1])) {
            return null;
        }

        return $segments[$index + 1];
    }

    private function decodeGoogleNewsArticleId(string $articleId, int $timeout): ?string
    {
        $decoded = $this->decodeGoogleNewsArticleIdOffline($articleId);
        if ($decoded === null || $decoded === '') {
            return null;
        }

        if (Str::startsWith($decoded, ['http://', 'https://'])) {
            return $decoded;
        }

        if (Str::startsWith($decoded, 'AU_yqL')) {
            return $this->decodeGoogleNewsArticleIdViaBatchExecute($articleId, $timeout);
        }

        return null;
    }

    private function decodeGoogleNewsArticleIdOffline(string $articleId): ?string
    {
        $normalized = strtr($articleId, '-_', '+/');
        $padding = strlen($normalized) % 4;
        if ($padding !== 0) {
            $normalized .= str_repeat('=', 4 - $padding);
        }

        $binary = base64_decode($normalized, true);
        if ($binary === false || $binary === '') {
            return null;
        }

        if (str_starts_with($binary, "\x08\x13\x22")) {
            $binary = substr($binary, 3);
        }

        if (substr($binary, -3) === "\xD2\x01\x00") {
            $binary = substr($binary, 0, -3);
        }

        if ($binary === '') {
            return null;
        }

        $firstByte = ord($binary[0]);
        $offset = 1;
        $length = $firstByte;

        if ($firstByte >= 0x80) {
            if (strlen($binary) < 2) {
                return null;
            }

            $offset = 2;
            $length = ($firstByte & 0x7F) | (ord($binary[1]) << 7);
        }

        $candidate = substr($binary, $offset, $length);
        if ($candidate === false || $candidate === '') {
            return null;
        }

        return trim($candidate);
    }

    private function decodeGoogleNewsArticleIdViaBatchExecute(string $articleId, int $timeout): ?string
    {
        $params = $this->fetchGoogleNewsDecodingParams($articleId, $timeout);
        if (!$params) {
            return $this->decodeGoogleNewsArticleIdViaLegacyBatchExecute($articleId, $timeout);
        }

        $payload = [
            'Fbv4je',
            sprintf(
                '["garturlreq",[["X","X",["X","X"],null,null,1,1,"US:en",null,1,null,null,null,null,null,0,1],"X","X",1,[1,1,1],1,1,null,0,0,null,0],"%s",%s,"%s"]',
                $articleId,
                $params['timestamp'],
                $params['signature']
            ),
        ];

        $body = 'f.req=' . rawurlencode(json_encode([[$payload]], JSON_UNESCAPED_SLASHES));
        $response = Http::withHeaders([
            'Content-Type' => 'application/x-www-form-urlencoded;charset=UTF-8',
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/129.0.0.0 Safari/537.36',
        ])->withBody($body, 'application/x-www-form-urlencoded;charset=UTF-8')
            ->timeout(max(5, min($timeout, 20)))
            ->post('https://news.google.com/_/DotsSplashUi/data/batchexecute');

        if (!$response->successful()) {
            return null;
        }

        $parts = preg_split("/\n\n/", (string) $response->body(), 2);
        if (count($parts) < 2) {
            return null;
        }

        $parsed = json_decode($parts[1], true);
        if (!is_array($parsed) || empty($parsed[0][2])) {
            return null;
        }

        $result = json_decode($parsed[0][2], true);
        $decodedUrl = is_array($result) ? ($result[1] ?? null) : null;

        return is_string($decodedUrl) && filter_var($decodedUrl, FILTER_VALIDATE_URL) ? $decodedUrl : null;
    }

    /**
     * @return array{signature: string, timestamp: string}|null
     */
    private function fetchGoogleNewsDecodingParams(string $articleId, int $timeout): ?array
    {
        foreach ([
            "https://news.google.com/articles/{$articleId}",
            "https://news.google.com/rss/articles/{$articleId}",
        ] as $url) {
            $response = Http::withHeaders([
                'User-Agent' => 'Mozilla/5.0',
            ])->timeout(max(5, min($timeout, 20)))->get($url);

            if (!$response->successful()) {
                continue;
            }

            $html = (string) $response->body();
            if (
                preg_match('/data-n-a-ts="([^"]+)"/', $html, $timestampMatch)
                && preg_match('/data-n-a-sg="([^"]+)"/', $html, $signatureMatch)
            ) {
                return [
                    'timestamp' => $timestampMatch[1],
                    'signature' => $signatureMatch[1],
                ];
            }
        }

        return null;
    }

    private function decodeGoogleNewsArticleIdViaLegacyBatchExecute(string $articleId, int $timeout): ?string
    {
        $payload = '[[["Fbv4je","[\\"garturlreq\\",[[\\"en-US\\",\\"US\\",[\\"FINANCE_TOP_INDICES\\",\\"WEB_TEST_1_0_0\\"],null,null,1,1,\\"US:en\\",null,180,null,null,null,null,null,0,null,null,[1608992183,723341000]],\\"en-US\\",\\"US\\",1,[2,3,4,8],1,0,\\"655000234\\",0,0,null,0],\\"'
            . $articleId
            . '\\"]",null,"generic"]]]';

        $response = Http::withHeaders([
            'Content-Type' => 'application/x-www-form-urlencoded;charset=utf-8',
            'Referrer' => 'https://news.google.com/',
        ])->asForm()->timeout(max(5, min($timeout, 20)))
            ->post('https://news.google.com/_/DotsSplashUi/data/batchexecute?rpcids=Fbv4je', [
                'f.req' => $payload,
            ]);

        if (!$response->successful()) {
            return null;
        }

        $body = (string) $response->body();
        $header = '["garturlres","';
        $footer = '",';
        $start = strpos($body, $header);
        if ($start === false) {
            return null;
        }

        $start += strlen($header);
        $end = strpos($body, $footer, $start);
        if ($end === false) {
            return null;
        }

        $decodedUrl = stripcslashes(substr($body, $start, $end - $start));

        return filter_var($decodedUrl, FILTER_VALIDATE_URL) ? $decodedUrl : null;
    }

    private function attachNormalizationMeta(array $result, string $requestedUrl, string $resolvedUrl, array $normalizationMeta): array
    {
        if ($requestedUrl === $resolvedUrl && empty($normalizationMeta)) {
            return $result;
        }

        $fetchInfo = is_array($result['fetch_info'] ?? null) ? $result['fetch_info'] : [];
        $fetchInfo['requested_url'] = $requestedUrl;
        $fetchInfo['resolved_source_url'] = $resolvedUrl;
        $fetchInfo['source_url_normalization'] = $normalizationMeta;
        $fetchInfo['effective_url'] = $fetchInfo['effective_url'] ?? $resolvedUrl;

        $result['fetch_info'] = $fetchInfo;
        $result['requested_url'] = $requestedUrl;
        $result['url'] = $resolvedUrl;

        return $result;
    }
}
