<?php

namespace hexa_app_publish\Discovery\Links\Health\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class LinkHealthService
{
    /**
     * @return array{url: string, status_code: int|null, status_text: string, status_tone: string, checked_via: string, final_url: string, is_broken: bool, probe_failed: bool}
     */
    public function probe(string $url, string $cacheNamespace = 'default'): array
    {
        $normalized = $this->normalizeUrl($url);
        if (!$normalized) {
            return [
                'url' => $url,
                'status_code' => null,
                'status_text' => 'Invalid URL',
                'status_tone' => 'red',
                'checked_via' => 'validation',
                'final_url' => '',
                'is_broken' => true,
                'probe_failed' => true,
            ];
        }

        if (!class_exists(\hexa_package_content_extractor\Probe\Services\UrlProbeService::class)) {
            return $this->probeUncached($normalized);
        }

        $result = app(\hexa_package_content_extractor\Probe\Services\UrlProbeService::class)->probe($normalized, [
            'cache_namespace' => 'publish:' . trim($cacheNamespace),
        ]);

        return [
            'url' => $normalized,
            'status_code' => $result['status_code'] ?? null,
            'status_text' => $result['status_text'] ?? 'Unknown',
            'status_tone' => $result['status_tone'] ?? 'amber',
            'checked_via' => $result['checked_via'] ?? 'GET',
            'final_url' => (string) ($result['final_url'] ?? $normalized),
            'is_broken' => (bool) ($result['is_broken'] ?? false),
            'probe_failed' => (bool) ($result['probe_failed'] ?? false),
            'wayback_url' => $result['wayback_url'] ?? null,
            'wayback_verified' => (bool) ($result['wayback_verified'] ?? false),
        ];
    }

    /**
     * @param array<int, mixed> $articles
     * @param array<int, string> $excludeUrls
     * @return array{articles: array<int, array<string, mixed>>, stats: array{checked: int, kept: int, discarded: int}}
     */
    public function verifyArticleCandidates(
        array $articles,
        int $limit,
        array $excludeUrls = [],
        ?callable $acceptCandidate = null,
        string $cacheNamespace = 'default'
    ): array {
        $verified = [];
        $seen = [];

        foreach ($excludeUrls as $excludeUrl) {
            $normalized = $this->normalizeUrl((string) $excludeUrl);
            if ($normalized) {
                $seen[$normalized] = true;
            }
        }

        $checked = 0;
        $discarded = 0;

        foreach ($articles as $article) {
            if (count($verified) >= $limit) {
                break;
            }

            $candidate = $this->normalizeArticleCandidate($article);
            if ($candidate === null) {
                $discarded++;
                continue;
            }

            if ($acceptCandidate && !$acceptCandidate($candidate)) {
                $discarded++;
                continue;
            }

            if (isset($seen[$candidate['url']])) {
                continue;
            }

            $checked++;
            $probe = $this->probe($candidate['url'], $cacheNamespace);
            if ($probe['probe_failed'] || $probe['is_broken']) {
                $discarded++;
                continue;
            }

            $resolvedUrl = $this->normalizeUrl((string) ($probe['final_url'] ?? '')) ?: $candidate['url'];
            if (!$this->looksLikeCanonicalArticleUrl($resolvedUrl) || isset($seen[$resolvedUrl])) {
                $discarded++;
                continue;
            }

            $seen[$resolvedUrl] = true;
            $verified[] = array_merge($candidate, [
                'url' => $resolvedUrl,
                'status_code' => $probe['status_code'],
                'status_text' => $probe['status_text'],
                'status_tone' => $probe['status_tone'],
                'checked_via' => $probe['checked_via'],
                'final_url' => $resolvedUrl,
                'is_broken' => false,
            ]);
        }

        return [
            'articles' => $verified,
            'stats' => [
                'checked' => $checked,
                'kept' => count($verified),
                'discarded' => $discarded,
            ],
        ];
    }

    /**
     * @param mixed $article
     * @return array<string, mixed>|null
     */
    public function normalizeArticleCandidate(mixed $article): ?array
    {
        if (!is_array($article)) {
            return null;
        }

        $url = $this->normalizeUrl((string) ($article['url'] ?? ''));
        if (!$url || !$this->looksLikeCanonicalArticleUrl($url)) {
            return null;
        }

        return [
            'url' => $url,
            'title' => trim((string) ($article['title'] ?? $url)),
            'description' => trim((string) ($article['description'] ?? '')),
        ];
    }

    public function normalizeUrl(string $url): ?string
    {
        $url = html_entity_decode(trim($url), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $url = trim($url, " \t\n\r\0\x0B<>\"'");
        $url = rtrim($url, '.,;)]}');

        if (preg_match('/^www\./i', $url)) {
            $url = 'https://' . $url;
        }

        if ($url === '' || !filter_var($url, FILTER_VALIDATE_URL)) {
            return null;
        }

        $parts = parse_url($url);
        if (!is_array($parts)) {
            return null;
        }

        $scheme = Str::lower((string) ($parts['scheme'] ?? ''));
        if (!in_array($scheme, ['http', 'https'], true)) {
            return null;
        }

        return $url;
    }

    public function looksLikeCanonicalArticleUrl(string $url): bool
    {
        $parts = parse_url($url);
        if (!is_array($parts)) {
            return false;
        }

        $host = Str::lower((string) ($parts['host'] ?? ''));
        $path = Str::lower(trim((string) ($parts['path'] ?? '/')));
        $query = Str::lower((string) ($parts['query'] ?? ''));

        if ($host === '' || $path === '' || $path === '/') {
            return false;
        }

        foreach ($this->blockedHosts() as $blockedHost) {
            if ($host === $blockedHost || Str::endsWith($host, '.' . $blockedHost)) {
                return false;
            }
        }

        $blockedFragments = [
            '/search',
            '/tag/',
            '/tags/',
            '/category/',
            '/categories/',
            '/topic/',
            '/topics/',
            '/author/',
            '/authors/',
            '/archive',
            '/archives/',
            '/newsletter',
            '/video/',
            '/videos/',
        ];

        foreach ($blockedFragments as $fragment) {
            if (str_contains($path, $fragment)) {
                return false;
            }
        }

        $segments = array_values(array_filter(explode('/', trim($path, '/'))));
        $first = $segments[0] ?? '';
        if (count($segments) === 1 && in_array($first, ['news', 'latest', 'live', 'video', 'videos', 'photos'], true)) {
            return false;
        }

        if ($query !== '' && preg_match('/(^|&)(q|query|search|s|output)=/', $query)) {
            return false;
        }

        return true;
    }

    /**
     * @return array{html: string, checked: int, removed: int, updated: int, failed: int, links: array<int, array<string, mixed>>}
     */
    public function sanitizeHtmlAnchors(string $html, string $cacheNamespace = 'prepare', ?callable $shouldContinue = null): array
    {
        if (trim($html) === '' || stripos($html, '<a ') === false) {
            return [
                'html' => $html,
                'checked' => 0,
                'removed' => 0,
                'updated' => 0,
                'failed' => 0,
                'links' => [],
            ];
        }

        $previous = libxml_use_internal_errors(true);
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $options = defined('LIBXML_HTML_NOIMPLIED') && defined('LIBXML_HTML_NODEFDTD')
            ? LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
            : 0;
        $loaded = $dom->loadHTML('<?xml encoding="utf-8" ?><div id="__hexa-link-health-root">' . $html . '</div>', $options);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (!$loaded) {
            return [
                'html' => $html,
                'checked' => 0,
                'removed' => 0,
                'updated' => 0,
                'failed' => 1,
                'links' => [],
            ];
        }

        $root = null;
        foreach ($dom->getElementsByTagName('div') as $div) {
            if ($div->getAttribute('id') === '__hexa-link-health-root') {
                $root = $div;
                break;
            }
        }

        if (!$root) {
            return [
                'html' => $html,
                'checked' => 0,
                'removed' => 0,
                'updated' => 0,
                'failed' => 1,
                'links' => [],
            ];
        }

        $anchors = [];
        foreach ($root->getElementsByTagName('a') as $anchor) {
            if ($anchor->hasAttribute('href')) {
                $anchors[] = $anchor;
            }
        }

        $checked = 0;
        $removed = 0;
        $updated = 0;
        $failed = 0;
        $links = [];

        foreach ($anchors as $anchor) {
            if ($shouldContinue && !$shouldContinue()) {
                throw new \RuntimeException('Run stopped by user.');
            }

            $href = (string) $anchor->getAttribute('href');
            $normalized = $this->normalizeUrl($href);
            if (!$normalized) {
                continue;
            }

            $checked++;
            $probe = $this->probe($normalized, $cacheNamespace);
            $links[] = $probe;

            if ($probe['is_broken']) {
                $this->unwrapAnchor($anchor);
                $removed++;
                if ($probe['probe_failed']) {
                    $failed++;
                }
                continue;
            }

            if ($probe['probe_failed']) {
                $failed++;
                continue;
            }

            $finalUrl = $this->normalizeUrl((string) ($probe['final_url'] ?? '')) ?: $normalized;
            if ($finalUrl !== $normalized) {
                $anchor->setAttribute('href', $finalUrl);
                $updated++;
            }
        }

        return [
            'html' => $this->innerHtml($root),
            'checked' => $checked,
            'removed' => $removed,
            'updated' => $updated,
            'failed' => $failed,
            'links' => $links,
        ];
    }

    public function statusTone(?int $statusCode, bool $isBroken): string
    {
        if (!$isBroken && $statusCode !== null && $statusCode >= 200 && $statusCode < 400) {
            return 'green';
        }

        if ($statusCode !== null && in_array($statusCode, [403, 405, 406, 429, 500, 502, 503, 504], true)) {
            return 'amber';
        }

        return 'red';
    }

    public function statusLabel(?int $statusCode): string
    {
        return match ((int) $statusCode) {
            200 => '200 OK',
            301 => '301 Redirect',
            302 => '302 Redirect',
            307 => '307 Redirect',
            308 => '308 Redirect',
            403 => '403 Forbidden',
            404 => '404 Not Found',
            405 => '405 Method Not Allowed',
            406 => '406 Not Acceptable',
            410 => '410 Gone',
            429 => '429 Rate Limited',
            500 => '500 Server Error',
            502 => '502 Bad Gateway',
            503 => '503 Service Unavailable',
            504 => '504 Gateway Timeout',
            default => $statusCode ? ($statusCode . ' Response') : 'No response',
        };
    }

    /**
     * @return array{url: string, status_code: int|null, status_text: string, status_tone: string, checked_via: string, final_url: string, is_broken: bool, probe_failed: bool}
     */
    private function probeUncached(string $url): array
    {
        if (!$this->looksLikeCanonicalArticleUrl($url)) {
            return [
                'url' => $url,
                'status_code' => null,
                'status_text' => 'Non-article URL',
                'status_tone' => 'red',
                'checked_via' => 'validation',
                'final_url' => $url,
                'is_broken' => true,
                'probe_failed' => true,
            ];
        }

        try {
            $response = Http::withHeaders([
                'User-Agent' => 'Hexa Publish Link Checker/1.0',
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            ])
                ->connectTimeout(5)
                ->timeout(10)
                ->withOptions([
                    'allow_redirects' => ['max' => 5, 'track_redirects' => true],
                    'http_errors' => false,
                ])
                ->head($url);

            $checkedVia = 'HEAD';
            $statusCode = $response->status();

            if ($this->shouldRetryWithGet($statusCode)) {
                $response = Http::withHeaders([
                    'User-Agent' => 'Hexa Publish Link Checker/1.0',
                    'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                ])
                    ->connectTimeout(5)
                    ->timeout(10)
                    ->withOptions([
                        'allow_redirects' => ['max' => 5, 'track_redirects' => true],
                        'http_errors' => false,
                    ])
                    ->get($url);
                $checkedVia = 'GET';
                $statusCode = $response->status();
            }

            $finalUrl = $this->resolveFinalUrlFromResponse($url, $response);
            $isBroken = !($statusCode >= 200 && $statusCode < 400);
            $statusText = $this->statusLabel($statusCode);

            if (!$isBroken && !$this->looksLikeCanonicalArticleUrl($finalUrl)) {
                $isBroken = true;
                $statusText = 'Redirected to non-article page';
            }

            return [
                'url' => $url,
                'status_code' => $statusCode,
                'status_text' => $statusText,
                'status_tone' => $this->statusTone($statusCode, $isBroken),
                'checked_via' => $checkedVia,
                'final_url' => $finalUrl,
                'is_broken' => $isBroken,
                'probe_failed' => false,
            ];
        } catch (\Throwable $e) {
            return [
                'url' => $url,
                'status_code' => null,
                'status_text' => 'Check failed: ' . Str::limit($e->getMessage(), 120, ''),
                'status_tone' => 'amber',
                'checked_via' => 'error',
                'final_url' => $url,
                'is_broken' => false,
                'probe_failed' => true,
            ];
        }
    }

    private function shouldRetryWithGet(?int $statusCode): bool
    {
        return in_array((int) $statusCode, [0, 403, 405, 406], true);
    }

    private function resolveFinalUrlFromResponse(string $url, $response): string
    {
        $history = $response->header('X-Guzzle-Redirect-History');

        if (is_array($history) && !empty($history)) {
            $last = end($history);
            return is_string($last) && $last !== '' ? $last : $url;
        }

        if (is_string($history) && trim($history) !== '') {
            $parts = array_values(array_filter(array_map('trim', explode(',', $history))));
            if (!empty($parts)) {
                return $parts[count($parts) - 1];
            }
        }

        return $url;
    }

    private function unwrapAnchor(\DOMElement $anchor): void
    {
        $parent = $anchor->parentNode;
        if (!$parent) {
            return;
        }

        while ($anchor->firstChild) {
            $parent->insertBefore($anchor->firstChild, $anchor);
        }

        $parent->removeChild($anchor);
    }

    private function innerHtml(\DOMElement $root): string
    {
        $html = '';
        foreach ($root->childNodes as $child) {
            $html .= $root->ownerDocument->saveHTML($child);
        }

        return trim($html);
    }

    /**
     * @return array<int, string>
     */
    private function blockedHosts(): array
    {
        return [
            'google.com',
            'www.google.com',
            'news.google.com',
            'webcache.googleusercontent.com',
            'vertexaisearch.cloud.google.com',
            'headtopics.com',
        ];
    }
}
