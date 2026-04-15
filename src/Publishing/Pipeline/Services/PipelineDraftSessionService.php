<?php

namespace hexa_app_publish\Publishing\Pipeline\Services;

use hexa_app_publish\Publishing\Articles\Models\PublishArticle;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class PipelineDraftSessionService
{
    private const TTL_SECONDS = 180;

    public function conflictFor(PublishArticle $draft, ?string $tabId, ?int $userId = null): ?array
    {
        $tabId = trim((string) $tabId);
        if ($tabId === '') {
            return null;
        }

        $session = $this->current($draft);
        if (!$session) {
            return null;
        }

        if (($session['tab_id'] ?? '') === $tabId) {
            return null;
        }

        if (($session['user_id'] ?? null) !== null && $userId !== null && (int) $session['user_id'] !== (int) $userId) {
            return null;
        }

        $lastSeenAt = $this->parseTime($session['last_seen_at'] ?? null);
        if (!$lastSeenAt || $lastSeenAt->lt(now()->subSeconds(self::TTL_SECONDS))) {
            return null;
        }

        return $this->format($session);
    }

    public function claim(PublishArticle $draft, ?string $tabId, ?int $userId = null, array $meta = []): ?array
    {
        $tabId = trim((string) $tabId);
        if ($tabId === '') {
            return null;
        }

        $key = $this->cacheKey($draft->id);
        $lock = Cache::lock($key . ':lock', 3);

        return $lock->block(2, function () use ($draft, $tabId, $userId, $meta, $key) {
            $existing = Cache::get($key);
            $claimedAt = $existing['claimed_at'] ?? now()->toIso8601String();

            $session = [
                'draft_id' => $draft->id,
                'tab_id' => $tabId,
                'user_id' => $userId,
                'claimed_at' => $claimedAt,
                'last_seen_at' => now()->toIso8601String(),
                'meta' => $meta,
            ];

            Cache::put($key, $session, now()->addSeconds(self::TTL_SECONDS));

            return $this->format($session);
        });
    }

    public function current(PublishArticle $draft): ?array
    {
        $session = Cache::get($this->cacheKey($draft->id));
        if (!is_array($session)) {
            return null;
        }

        return $session;
    }

    public function ttlSeconds(): int
    {
        return self::TTL_SECONDS;
    }

    private function cacheKey(int $draftId): string
    {
        return 'publish-pipeline:draft-session:' . $draftId;
    }

    private function parseTime(?string $value): ?Carbon
    {
        if (!$value) {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    private function format(array $session): array
    {
        return [
            'draft_id' => (int) ($session['draft_id'] ?? 0),
            'tab_id' => (string) ($session['tab_id'] ?? ''),
            'user_id' => isset($session['user_id']) ? (int) $session['user_id'] : null,
            'claimed_at' => $session['claimed_at'] ?? null,
            'last_seen_at' => $session['last_seen_at'] ?? null,
            'ttl_seconds' => self::TTL_SECONDS,
            'meta' => is_array($session['meta'] ?? null) ? $session['meta'] : [],
        ];
    }
}
