<?php

namespace hexa_app_publish\Publishing\Pipeline\Services;

use hexa_app_publish\Publishing\Articles\Models\PublishArticle;
use hexa_app_publish\Support\AiModelCatalog;
use Illuminate\Http\Client\Events\ConnectionFailed;
use Illuminate\Http\Client\Events\RequestSending;
use Illuminate\Http\Client\Events\ResponseReceived;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Throwable;

class PublishPipelineHttpActivityTracker
{
    private const BODY_PREVIEW_LIMIT = 1800;
    private const BODY_DETAIL_LIMIT = 40000;

    /**
     * @var array<string, array<int, array<string, mixed>>>
     */
    private array $pending = [];

    /**
     * @var array<int, PublishArticle|null>
     */
    private array $articleCache = [];

    public function __construct(
        private PublishPipelineApiContext $context,
        private PipelineActivityService $activityService,
        private AiModelCatalog $modelCatalog
    ) {}

    public function onRequestSending(RequestSending $event): void
    {
        $context = $this->context->current();
        if (!$this->shouldTrack($context, $event->request)) {
            return;
        }

        $key = $this->requestKey($event->request);
        $this->pending[$key][] = [
            'started_at' => microtime(true),
            'captured_at' => now()->toIso8601String(),
            'context' => $context,
            'request' => $this->extractRequestDetails($event->request),
        ];
    }

    public function onResponseReceived(ResponseReceived $event): void
    {
        $pending = $this->pullPending($event->request);
        $context = $pending['context'] ?? $this->context->current();
        if (!$this->shouldTrack($context, $event->request)) {
            return;
        }

        $requestDetails = $pending['request'] ?? $this->extractRequestDetails($event->request);
        $durationMs = $this->resolveDurationMs($pending['started_at'] ?? null, $event->response);
        $responseDetails = $this->extractResponseDetails($event->response);
        $usage = $this->extractUsage($requestDetails['service_key'], $requestDetails['request_payload'], $responseDetails['response_payload'], $requestDetails['url']);
        $this->recordEvent($context, $requestDetails, [
            ...$responseDetails,
            'request_sent_at' => $pending['captured_at'] ?? null,
            'duration_ms' => $durationMs,
            'usage' => $usage,
            'error_message' => null,
        ]);
    }

    public function onConnectionFailed(ConnectionFailed $event): void
    {
        $pending = $this->pullPending($event->request);
        $context = $pending['context'] ?? $this->context->current();
        if (!$this->shouldTrack($context, $event->request)) {
            return;
        }

        $requestDetails = $pending['request'] ?? $this->extractRequestDetails($event->request);
        $pendingStartedAt = $pending['started_at'] ?? null;
        $durationMs = $pendingStartedAt ? (int) round((microtime(true) - (float) $pendingStartedAt) * 1000) : null;

        $this->recordEvent($context, $requestDetails, [
            'status_code' => null,
            'request_sent_at' => $pending['captured_at'] ?? null,
            'duration_ms' => $durationMs,
            'response_headers' => [],
            'response_payload' => null,
            'response_preview' => '',
            'usage' => [
                'model' => $this->detectModel($requestDetails['service_key'], $requestDetails['request_payload'], $requestDetails['url']),
                'input_tokens' => null,
                'output_tokens' => null,
                'total_tokens' => null,
                'cost_usd' => null,
            ],
            'error_message' => $event->exception->getMessage(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function shouldTrack(array $context, Request $request): bool
    {
        $draftId = (int) ($context['draft_id'] ?? 0);
        if ($draftId <= 0) {
            return false;
        }

        $url = trim((string) $request->url());
        if ($url === '') {
            return false;
        }

        $service = $this->classifyService($url);
        if ($service['key'] === 'wordpress') {
            return true;
        }

        return !str_contains($url, url('/'));
    }

    private function requestKey(Request $request): string
    {
        return (string) spl_object_id($request);
    }

    /**
     * @return array<string, mixed>
     */
    private function pullPending(Request $request): array
    {
        $key = $this->requestKey($request);
        $queue = $this->pending[$key] ?? [];
        $pending = array_shift($queue);
        $this->pending[$key] = $queue;

        if (($this->pending[$key] ?? []) === []) {
            unset($this->pending[$key]);
        }

        return is_array($pending) ? $pending : [];
    }

    /**
     * @return array<string, mixed>
     */
    private function extractRequestDetails(Request $request): array
    {
        $rawUrl = trim((string) $request->url());
        $service = $this->classifyService($rawUrl);
        $headers = $this->sanitizeHeaders($request->headers());
        $payload = $this->normalizePayload($request->data(), (string) $request->body());

        return [
            'method' => strtoupper((string) $request->method()),
            'url' => $this->redactUrl($rawUrl),
            'service_key' => $service['key'],
            'service_label' => $service['label'],
            'service_host' => $service['host'],
            'request_headers' => $headers,
            'request_payload' => $payload,
            'payload_preview' => $this->stringifyPreview($payload),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function extractResponseDetails(Response $response): array
    {
        $headers = $this->sanitizeHeaders($response->headers());
        $contentTypeHeader = $response->header('Content-Type');
        $contentType = strtolower(is_array($contentTypeHeader) ? (string) Arr::first($contentTypeHeader) : (string) $contentTypeHeader);
        $payload = $this->normalizeResponsePayload($response, $contentType);

        return [
            'status_code' => $response->status(),
            'response_headers' => $headers,
            'response_payload' => $payload,
            'response_preview' => $this->stringifyPreview($payload),
        ];
    }

    private function resolveDurationMs(mixed $startedAt, Response $response): ?int
    {
        if (is_numeric($startedAt)) {
            return (int) round((microtime(true) - (float) $startedAt) * 1000);
        }

        $stats = $response->handlerStats();
        $seconds = $stats['total_time'] ?? null;

        return is_numeric($seconds) ? (int) round(((float) $seconds) * 1000) : null;
    }

    /**
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>  $requestDetails
     * @param  array<string, mixed>  $result
     */
    private function recordEvent(array $context, array $requestDetails, array $result): void
    {
        $article = $this->resolveArticle((int) ($context['draft_id'] ?? 0));
        if (!$article) {
            return;
        }

        $statusCode = $result['status_code'] ?? null;
        $durationMs = $result['duration_ms'] ?? null;
        $usage = is_array($result['usage'] ?? null) ? $result['usage'] : [];
        $serviceLabel = (string) ($requestDetails['service_label'] ?? 'External API');
        $url = (string) ($requestDetails['url'] ?? '');
        $method = (string) ($requestDetails['method'] ?? 'GET');
        $pathLabel = $this->pathLabel($url);
        $statusLabel = $statusCode ? 'HTTP ' . $statusCode : 'failed';
        $message = $serviceLabel . ' ' . $method . ' ' . $pathLabel . ' -> ' . $statusLabel;
        $errorMessage = trim((string) ($result['error_message'] ?? ''));

        $detailsParts = [];
        if (!empty($context['user_name'])) {
            $detailsParts[] = 'User: ' . $context['user_name'];
        }
        if ($durationMs !== null) {
            $detailsParts[] = 'Duration: ' . $durationMs . 'ms';
        }
        if ($errorMessage !== '') {
            $detailsParts[] = 'Error: ' . $errorMessage;
        }

        $meta = [
            'service_key' => $requestDetails['service_key'],
            'service_label' => $serviceLabel,
            'service_host' => $requestDetails['service_host'],
            'actor_user_id' => $context['user_id'] ?? null,
            'actor_name' => $context['user_name'] ?? null,
            'request_payload_full' => $requestDetails['request_payload'],
            'request_headers' => $requestDetails['request_headers'],
            'response_payload_full' => $result['response_payload'] ?? null,
            'response_headers' => $result['response_headers'] ?? [],
            'response_error' => $errorMessage !== '' ? $errorMessage : null,
            'request_sent_at' => $result['request_sent_at'] ?? null,
            'model' => $usage['model'] ?? null,
            'input_tokens' => $usage['input_tokens'] ?? null,
            'output_tokens' => $usage['output_tokens'] ?? null,
            'total_tokens' => $usage['total_tokens'] ?? null,
            'cost_usd' => $usage['cost_usd'] ?? null,
            'operation_type' => $context['operation_type'] ?? null,
            'operation_id' => $context['operation_id'] ?? null,
        ];

        $this->activityService->appendServerEvent(
            $article,
            (string) ($context['client_trace'] ?: ('draft-' . $article->id)),
            [
                'client_event_id' => 'api:' . Str::uuid(),
                'run_trace' => (string) ($context['client_trace'] ?: ('draft-' . $article->id)),
                'captured_at' => now()->toIso8601String(),
                'scope' => 'api',
                'type' => $statusCode && $statusCode < 400 ? 'success' : 'error',
                'message' => $message,
                'stage' => 'api',
                'substage' => (string) ($requestDetails['service_key'] ?? 'external'),
                'trace_id' => (string) ($context['trace_id'] ?? ''),
                'method' => $method,
                'status' => $statusCode,
                'duration_ms' => $durationMs,
                'url' => $url,
                'details' => implode(' | ', array_filter($detailsParts)),
                'payload_preview' => (string) ($requestDetails['payload_preview'] ?? ''),
                'response_preview' => (string) ($result['response_preview'] ?? ''),
                'debug_only' => false,
                'step' => 7,
                'meta' => array_filter($meta, static fn ($value) => $value !== null && $value !== '' && $value !== []),
            ],
            $context['workflow_type'] ?? null,
            (bool) ($context['debug_enabled'] ?? false),
            isset($context['user_id']) ? (int) $context['user_id'] : null
        );
    }

    private function resolveArticle(int $draftId): ?PublishArticle
    {
        if ($draftId <= 0) {
            return null;
        }

        if (!array_key_exists($draftId, $this->articleCache)) {
            $this->articleCache[$draftId] = PublishArticle::find($draftId);
        }

        return $this->articleCache[$draftId];
    }

    /**
     * @param  array<string, mixed>|array<int, mixed>|null  $structured
     */
    private function normalizePayload(mixed $structured, string $rawBody): mixed
    {
        if (is_array($structured) && $structured !== []) {
            return $this->sanitizeValue($structured, self::BODY_DETAIL_LIMIT);
        }

        $decoded = $this->tryDecodeJson($rawBody);
        if ($decoded !== null) {
            return $this->sanitizeValue($decoded, self::BODY_DETAIL_LIMIT);
        }

        if ($rawBody === '') {
            return null;
        }

        return $this->truncateString($rawBody, self::BODY_DETAIL_LIMIT);
    }

    private function normalizeResponsePayload(Response $response, string $contentType): mixed
    {
        if (str_contains($contentType, 'application/json') || str_contains($contentType, '+json')) {
            $json = $response->json();
            if (is_array($json)) {
                return $this->sanitizeValue($json, self::BODY_DETAIL_LIMIT);
            }
        }

        $body = (string) $response->body();
        if ($body === '') {
            return null;
        }

        if (str_starts_with($contentType, 'image/')) {
            return '[binary ' . $contentType . ' ' . strlen($body) . ' bytes]';
        }

        $decoded = $this->tryDecodeJson($body);
        if ($decoded !== null) {
            return $this->sanitizeValue($decoded, self::BODY_DETAIL_LIMIT);
        }

        return $this->truncateString($body, self::BODY_DETAIL_LIMIT);
    }

    /**
     * @return array{key: string, label: string, host: string}
     */
    private function classifyService(string $url): array
    {
        $parts = parse_url($url);
        $host = strtolower((string) ($parts['host'] ?? ''));
        $path = strtolower((string) ($parts['path'] ?? ''));

        return match (true) {
            str_contains($host, 'anthropic.com') => ['key' => 'anthropic', 'label' => 'Anthropic', 'host' => $host],
            str_contains($host, 'openai.com') => ['key' => 'openai', 'label' => 'OpenAI', 'host' => $host],
            str_contains($host, 'x.ai') => ['key' => 'grok', 'label' => 'Grok', 'host' => $host],
            str_contains($host, 'generativelanguage.googleapis.com') => ['key' => 'gemini', 'label' => 'Gemini', 'host' => $host],
            str_contains($host, 'serper.dev') => ['key' => 'serper', 'label' => 'Serper', 'host' => $host],
            str_contains($host, 'serpapi.com') => ['key' => 'serpapi', 'label' => 'SerpAPI', 'host' => $host],
            str_contains($host, 'news.google.com') => ['key' => 'google-news', 'label' => 'Google News', 'host' => $host],
            str_contains($path, '/wp-json/') => ['key' => 'wordpress', 'label' => 'WordPress', 'host' => $host],
            default => ['key' => 'web-fetch', 'label' => 'Web Fetch', 'host' => $host],
        };
    }

    private function redactUrl(string $url): string
    {
        if ($url === '') {
            return '';
        }

        $parts = parse_url($url);
        if ($parts === false) {
            return $url;
        }

        $queryParams = [];
        parse_str((string) ($parts['query'] ?? ''), $queryParams);
        foreach (['key', 'api_key', 'apikey', 'token', 'access_token'] as $secretKey) {
            if (array_key_exists($secretKey, $queryParams)) {
                $queryParams[$secretKey] = '[redacted]';
            }
        }

        $query = $queryParams !== [] ? http_build_query($queryParams) : null;

        return ($parts['scheme'] ?? 'https') . '://'
            . ($parts['host'] ?? '')
            . (isset($parts['port']) ? ':' . $parts['port'] : '')
            . ($parts['path'] ?? '')
            . ($query ? '?' . $query : '');
    }

    /**
     * @param  array<string, mixed>|array<int, mixed>  $headers
     * @return array<string, mixed>
     */
    private function sanitizeHeaders(array $headers): array
    {
        $sanitized = [];
        foreach ($headers as $key => $value) {
            $normalizedKey = strtolower((string) $key);
            if (in_array($normalizedKey, ['authorization', 'x-api-key', 'api-key', 'x-goog-api-key', 'cookie'], true)) {
                $sanitized[(string) $key] = '[redacted]';
                continue;
            }

            $sanitized[(string) $key] = $this->sanitizeValue($value, 600);
        }

        ksort($sanitized);

        return $sanitized;
    }

    private function stringifyPreview(mixed $payload): string
    {
        if ($payload === null || $payload === '') {
            return '';
        }

        $string = $this->stringifyValue($payload);

        return $this->truncateString($string, self::BODY_PREVIEW_LIMIT);
    }

    private function stringifyValue(mixed $value): string
    {
        if (is_string($value)) {
            return $value;
        }

        try {
            return json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return (string) json_encode($value);
        }
    }

    private function tryDecodeJson(string $value): mixed
    {
        $trimmed = trim($value);
        if ($trimmed === '' || (!str_starts_with($trimmed, '{') && !str_starts_with($trimmed, '['))) {
            return null;
        }

        try {
            return json_decode($trimmed, true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return null;
        }
    }

    private function sanitizeValue(mixed $value, int $stringLimit): mixed
    {
        if (is_string($value)) {
            return $this->truncateString($value, $stringLimit);
        }

        if (is_array($value)) {
            $sanitized = [];
            foreach ($value as $key => $item) {
                $sanitized[$key] = $this->sanitizeValue($item, $stringLimit);
            }

            return $sanitized;
        }

        return $value;
    }

    private function truncateString(string $value, int $limit): string
    {
        if (mb_strlen($value) <= $limit) {
            return $value;
        }

        return mb_substr($value, 0, $limit) . ' … [truncated ' . mb_strlen($value) . ' chars]';
    }

    /**
     * @param  mixed  $requestPayload
     * @param  mixed  $responsePayload
     * @return array{model: ?string, input_tokens: ?int, output_tokens: ?int, total_tokens: ?int, cost_usd: ?float}
     */
    private function extractUsage(string $serviceKey, mixed $requestPayload, mixed $responsePayload, string $url): array
    {
        $model = $this->detectModel($serviceKey, $requestPayload, $url);
        $inputTokens = null;
        $outputTokens = null;
        $totalTokens = null;

        if (is_array($responsePayload)) {
            switch ($serviceKey) {
                case 'anthropic':
                    $inputTokens = (int) data_get($responsePayload, 'usage.input_tokens', 0);
                    $outputTokens = (int) data_get($responsePayload, 'usage.output_tokens', 0);
                    $totalTokens = $inputTokens + $outputTokens;
                    break;
                case 'openai':
                case 'grok':
                    $inputTokens = (int) data_get($responsePayload, 'usage.prompt_tokens', 0);
                    $outputTokens = (int) data_get($responsePayload, 'usage.completion_tokens', 0);
                    $totalTokens = (int) data_get($responsePayload, 'usage.total_tokens', $inputTokens + $outputTokens);
                    break;
                case 'gemini':
                    $inputTokens = (int) (data_get($responsePayload, 'usageMetadata.promptTokenCount') ?? data_get($responsePayload, 'usage_metadata.prompt_token_count') ?? 0);
                    $outputTokens = (int) (data_get($responsePayload, 'usageMetadata.candidatesTokenCount') ?? data_get($responsePayload, 'usage_metadata.candidates_token_count') ?? 0);
                    $totalTokens = (int) (data_get($responsePayload, 'usageMetadata.totalTokenCount') ?? data_get($responsePayload, 'usage_metadata.total_token_count') ?? ($inputTokens + $outputTokens));
                    break;
            }
        }

        if ($inputTokens === 0 && $outputTokens === 0 && $totalTokens === 0) {
            $inputTokens = $outputTokens = $totalTokens = null;
        }

        $cost = null;
        if ($model && ($inputTokens !== null || $outputTokens !== null)) {
            $cost = round((float) $this->modelCatalog->calculateCost($model, [
                'input_tokens' => $inputTokens ?? 0,
                'output_tokens' => $outputTokens ?? 0,
                'total_tokens' => $totalTokens ?? (($inputTokens ?? 0) + ($outputTokens ?? 0)),
            ]), 6);

            if ($cost <= 0) {
                $cost = null;
            }
        }

        return [
            'model' => $model,
            'input_tokens' => $inputTokens,
            'output_tokens' => $outputTokens,
            'total_tokens' => $totalTokens,
            'cost_usd' => $cost,
        ];
    }

    private function detectModel(string $serviceKey, mixed $requestPayload, string $url): ?string
    {
        if (is_array($requestPayload)) {
            $candidate = trim((string) (data_get($requestPayload, 'model') ?? data_get($requestPayload, 'generationConfig.model') ?? ''));
            if ($candidate !== '') {
                return $candidate;
            }
        }

        if ($serviceKey === 'gemini' && preg_match('~/models/([^:/?]+)~i', $url, $matches)) {
            return trim((string) ($matches[1] ?? '')) ?: null;
        }

        return null;
    }

    private function pathLabel(string $url): string
    {
        $parts = parse_url($url);
        $host = (string) ($parts['host'] ?? '');
        $path = (string) ($parts['path'] ?? '/');

        return $host !== '' ? $host . $path : $path;
    }
}
