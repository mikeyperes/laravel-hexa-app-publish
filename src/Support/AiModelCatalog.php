<?php

namespace hexa_app_publish\Support;

use hexa_core\AI\Services\AiModelCatalog as CoreAiModelCatalog;

class AiModelCatalog extends CoreAiModelCatalog
{
    private const SEARCH_PRIORITY = [
        'gemini-2.5-flash',
        'claude-haiku-4-5-20251001',
        'gpt-4o-mini',
        'grok-3-mini',
        'gemini-2.5-flash-lite',
        'grok-3',
        'gpt-4o',
        'grok-4-1-fast',
    ];

    private const SPIN_PRIORITY = [
        'grok-3',
        'grok-4.20-reasoning',
        'claude-sonnet-4-6',
        'claude-sonnet-4-20250514',
        'gemini-2.5-pro',
        'gemini-2.5-flash',
        'gpt-4o',
    ];

    private const PHOTO_META_PRIORITY = [
        'gemini-2.5-flash-lite',
        'claude-haiku-4-5-20251001',
        'gpt-4o',
        'grok-3-mini',
    ];

    public function defaultSearchModel(): ?string
    {
        return $this->preferredModel(self::SEARCH_PRIORITY);
    }

    public function defaultSearchFallbackModel(?string $primary = null): ?string
    {
        return $this->preferredModel(self::SEARCH_PRIORITY, [$primary]);
    }

    public function defaultSpinModel(): ?string
    {
        return $this->preferredModel(self::SPIN_PRIORITY);
    }

    public function defaultSpinFallbackModel(?string $primary = null): ?string
    {
        return $this->preferredModel(self::SPIN_PRIORITY, [$primary]);
    }

    public function defaultPhotoMetaModel(): ?string
    {
        return $this->preferredModel(self::PHOTO_META_PRIORITY);
    }
}
