<?php

namespace hexa_app_publish\Discovery\Sources\Health\Services;

use hexa_app_publish\Discovery\Sources\Models\BannedSource;
use hexa_app_publish\Discovery\Sources\Models\ScrapeLog;
use hexa_app_publish\Discovery\Sources\Models\SourceNote;
use Illuminate\Support\Facades\Cache;

class SourceAccessStrategyService
{
    /**
     * @return array<int, array{method: string, user_agent: string, reason: string, label: string}>
     */
    public function buildAttemptMatrix(string $url, string $requestedMethod = 'auto', string $requestedUserAgent = 'chrome'): array
    {
        $domain = $this->normalizeDomain($url);
        $note = $this->noteFor($domain);
        $health = $this->healthFor($domain);
        $attempts = [];

        $noteMethod = $this->cleanValue($note?->recommended_method);
        $noteUserAgent = $this->cleanValue($note?->recommended_ua);
        $bestMethod = $this->cleanValue($health['best_success_method'] ?? null);
        $bestUserAgent = $this->cleanValue($health['best_success_ua'] ?? null);

        if ($noteMethod || $noteUserAgent) {
            $this->pushAttempt(
                $attempts,
                $noteMethod ?: $requestedMethod,
                $noteUserAgent ?: $requestedUserAgent,
                'domain-note',
                'Domain note'
            );
        }

        if ($bestMethod || $bestUserAgent) {
            $this->pushAttempt(
                $attempts,
                $bestMethod ?: $requestedMethod,
                $bestUserAgent ?: $requestedUserAgent,
                'recent-success',
                'Recent success'
            );
        }

        $this->pushAttempt($attempts, $requestedMethod, $requestedUserAgent, 'requested', 'Requested');

        if ($this->supportsUserAgentFallbacks($requestedMethod)) {
            foreach ($this->preferredUserAgentFallbacks($health) as $userAgent) {
                $this->pushAttempt($attempts, $requestedMethod, $userAgent, 'ua-fallback', 'UA fallback');
            }
        }

        return array_slice($attempts, 0, 6);
    }

    public function shouldBlockDiscoveryCandidate(string $url): bool
    {
        $domain = $this->normalizeDomain($url);
        if ($domain === '') {
            return false;
        }

        $health = $this->healthFor($domain);

        if (!empty($health['banned'])) {
            return true;
        }

        if (($health['passes'] ?? 0) > 0) {
            return false;
        }

        $total = (int) ($health['total'] ?? 0);
        $hardBlocks = (int) ($health['hard_blocks'] ?? 0);
        $passRate = (float) ($health['pass_rate'] ?? 0.0);

        if ($total >= 5 && $hardBlocks >= 3 && ($health['passes'] ?? 0) === 0) {
            return true;
        }

        return $total >= 10 && $hardBlocks >= 5 && $passRate < 0.10;
    }

    /**
     * @return array<string, mixed>
     */
    public function healthFor(string $urlOrDomain): array
    {
        $domain = $this->normalizeDomain($urlOrDomain);
        if ($domain === '') {
            return [
                'domain' => '',
                'banned' => false,
                'total' => 0,
                'passes' => 0,
                'fails' => 0,
                'hard_blocks' => 0,
                'pass_rate' => 0.0,
                'best_success_method' => null,
                'best_success_ua' => null,
            ];
        }

        return Cache::remember('publish:source-health:' . $domain, now()->addMinutes(15), function () use ($domain) {
            $recent = ScrapeLog::query()
                ->where('domain', $domain)
                ->where('created_at', '>=', now()->subDays(45))
                ->orderByDesc('id')
                ->limit(60)
                ->get(['success', 'http_status', 'method', 'user_agent']);

            $total = $recent->count();
            $passes = $recent->where('success', true)->count();
            $fails = $recent->where('success', false)->count();
            $hardBlocks = $recent->filter(function ($log) {
                return !$log->success && in_array((int) $log->http_status, [401, 403, 429, 451], true);
            })->count();

            $lastSuccess = $recent->first(function ($log) {
                return (bool) $log->success;
            });

            return [
                'domain' => $domain,
                'banned' => BannedSource::query()->where('domain', $domain)->exists(),
                'total' => $total,
                'passes' => $passes,
                'fails' => $fails,
                'hard_blocks' => $hardBlocks,
                'pass_rate' => $total > 0 ? round($passes / $total, 4) : 0.0,
                'best_success_method' => $this->cleanValue($lastSuccess?->method),
                'best_success_ua' => $this->cleanValue($lastSuccess?->user_agent),
            ];
        });
    }

    protected function supportsUserAgentFallbacks(string $method): bool
    {
        return !in_array($method, ['jina', 'ai', 'claude', 'gpt', 'grok', 'gemini'], true);
    }

    protected function preferredUserAgentFallbacks(array $health): array
    {
        $hardBlocks = (int) ($health['hard_blocks'] ?? 0);
        $passes = (int) ($health['passes'] ?? 0);
        $fails = (int) ($health['fails'] ?? 0);
        $passRate = (float) ($health['pass_rate'] ?? 0.0);

        if ($hardBlocks >= 3 || ($fails >= 5 && $passRate < 0.20 && $passes <= 1)) {
            return ['mobile', 'googlebot', 'firefox', 'safari', 'bingbot'];
        }

        return ['mobile', 'firefox', 'safari', 'googlebot', 'bingbot'];
    }

    protected function pushAttempt(array &$attempts, string $method, string $userAgent, string $reason, string $label): void
    {
        $method = $this->cleanValue($method) ?: 'auto';
        $userAgent = $this->cleanValue($userAgent) ?: 'chrome';
        $signature = $method . '|' . $userAgent;

        foreach ($attempts as $existing) {
            if (($existing['method'] . '|' . $existing['user_agent']) === $signature) {
                return;
            }
        }

        $attempts[] = [
            'method' => $method,
            'user_agent' => $userAgent,
            'reason' => $reason,
            'label' => $label,
        ];
    }

    protected function noteFor(string $domain): ?SourceNote
    {
        if ($domain === '') {
            return null;
        }

        return Cache::remember('publish:source-note:' . $domain, now()->addMinutes(15), function () use ($domain) {
            return SourceNote::query()->where('domain', $domain)->first();
        });
    }

    protected function normalizeDomain(string $urlOrDomain): string
    {
        $value = trim(strtolower($urlOrDomain));
        if ($value === '') {
            return '';
        }

        $host = parse_url($value, PHP_URL_HOST);
        if (is_string($host) && $host !== '') {
            return strtolower($host);
        }

        return preg_replace('/^www\./', 'www.', $value) ?: '';
    }

    protected function cleanValue(?string $value): ?string
    {
        $value = trim((string) $value);
        return $value !== '' ? $value : null;
    }
}
