<?php

namespace hexa_app_publish\Publishing\Campaigns\Services;

use hexa_app_publish\Discovery\Media\Services\MediaSearchService;
use hexa_app_publish\Publishing\Articles\Services\ArticleActivityService;
use hexa_core\Models\Setting;
use Illuminate\Support\Str;

class CampaignPhotoAutomationService
{
    public function __construct(
        private MediaSearchService $mediaSearch,
        private ArticleActivityService $activities,
    ) {}

    /**
     * @param array<int, array<string, mixed>> $photoSuggestions
     * @param array<int, string> $preferredSources
     * @return array{
     *     html: string,
     *     photo_suggestions: array<int, array<string, mixed>>,
     *     featured_photo: ?array,
     *     featured_url: ?string,
     *     featured_meta: ?array,
     *     inline_count: int,
     *     featured_count: int,
     *     warnings: array<int, string>
     * }
     */
    public function hydrate(
        string $html,
        array $photoSuggestions,
        ?string $featuredSearch,
        ?array $featuredHint,
        array $preferredSources,
        string $articleTitle,
        string $articleText,
        ?int $articleId = null,
        ?callable $onProgress = null
    ): array {
        $send = $onProgress ?? static function (): void {
        };

        $warnings = [];
        $usedUrls = [];
        $hydratedSuggestions = [];
        $inlineCount = 0;

        foreach ($photoSuggestions as $idx => $suggestion) {
            $searchTerm = trim((string) ($suggestion['search_term'] ?? ''));
            if ($searchTerm === '') {
                $hydratedSuggestions[] = $suggestion;
                continue;
            }

            $this->emit($send, 'info', "Searching inline photo " . ($idx + 1) . ': ' . $searchTerm, 'photos', 'search', [
                'photo_index' => $idx + 1,
                'search_term' => $searchTerm,
            ]);

            $selected = $this->pickPhoto($searchTerm, $preferredSources, $usedUrls, 'inline', $articleId);
            if (!$selected) {
                $warning = "No usable inline photo found for: {$searchTerm}";
                $warnings[] = $warning;
                $this->emit($send, 'warning', $warning, 'photos', 'inline_missing', [
                    'photo_index' => $idx + 1,
                    'search_term' => $searchTerm,
                ]);
                $this->recordPhotoActivity($articleId, 'inline_missing', false, $searchTerm, null, [
                    'quality_context' => 'inline',
                    'preferred_sources' => array_values($preferredSources),
                ]);
                $hydratedSuggestions[] = $suggestion;
                continue;
            }

            $usedUrls[] = (string) ($selected['url_large'] ?? $selected['url'] ?? '');
            $meta = $this->buildMeta(
                $selected,
                $searchTerm,
                $articleTitle,
                $articleText,
                $suggestion['alt_text'] ?? null,
                $suggestion['caption'] ?? null,
                $suggestion['suggestedFilename'] ?? null
            );

            $hydrated = array_merge($suggestion, [
                'alt_text' => $meta['alt_text'],
                'caption' => $meta['caption'],
                'suggestedFilename' => $meta['filename'],
                'autoPhoto' => $selected,
                'confirmed' => true,
            ]);

            $html = $this->replacePlaceholder($html, (int) ($suggestion['position'] ?? $idx), $selected, $meta);
            $hydratedSuggestions[] = $hydrated;
            $inlineCount++;
            $this->recordPhotoActivity($articleId, 'inline_selected', true, $searchTerm, $selected, [
                'quality_context' => 'inline',
                'meta' => $meta,
            ]);

            $this->emit($send, 'success', "Inline photo selected via {$selected['source']}", 'photos', 'inline_selected', [
                'photo_index' => $idx + 1,
                'search_term' => $searchTerm,
                'source' => $selected['source'] ?? '',
                'url' => $selected['url_large'] ?? $selected['url'] ?? '',
                'source_url' => $selected['source_url'] ?? '',
                'width' => $selected['width'] ?? null,
                'height' => $selected['height'] ?? null,
                'aspect_ratio' => $selected['aspect_ratio'] ?? null,
                'file_size_bytes' => $selected['file_size_bytes'] ?? null,
                'mime_type' => $selected['mime_type'] ?? null,
                'quality_score' => $selected['quality_score'] ?? null,
                'quality_pass' => $selected['quality_pass'] ?? null,
            ]);
        }

        $featuredPhoto = null;
        $featuredMeta = null;
        $featuredUrl = null;

        if (trim((string) $featuredSearch) !== '') {
            $this->emit($send, 'info', 'Searching featured image: ' . trim((string) $featuredSearch), 'photos', 'featured_search', [
                'search_term' => trim((string) $featuredSearch),
            ]);

            $featuredPhoto = $this->pickPhoto((string) $featuredSearch, $preferredSources, $usedUrls, 'featured', $articleId);
            if ($featuredPhoto) {
                $featuredUrl = (string) ($featuredPhoto['url_large'] ?? $featuredPhoto['url'] ?? '');
                $featuredMeta = $this->buildMeta(
                    $featuredPhoto,
                    (string) $featuredSearch,
                    $articleTitle,
                    $articleText,
                    $featuredHint['alt'] ?? null,
                    $featuredHint['caption'] ?? null,
                    $featuredHint['filename'] ?? null
                );
                $this->recordPhotoActivity($articleId, 'featured_selected', true, (string) $featuredSearch, $featuredPhoto, [
                    'quality_context' => 'featured',
                    'meta' => $featuredMeta,
                ]);

                $this->emit($send, 'success', "Featured image selected via {$featuredPhoto['source']}", 'photos', 'featured_selected', [
                    'source' => $featuredPhoto['source'] ?? '',
                    'url' => $featuredUrl,
                    'source_url' => $featuredPhoto['source_url'] ?? '',
                    'width' => $featuredPhoto['width'] ?? null,
                    'height' => $featuredPhoto['height'] ?? null,
                    'aspect_ratio' => $featuredPhoto['aspect_ratio'] ?? null,
                    'file_size_bytes' => $featuredPhoto['file_size_bytes'] ?? null,
                    'mime_type' => $featuredPhoto['mime_type'] ?? null,
                    'quality_score' => $featuredPhoto['quality_score'] ?? null,
                    'quality_pass' => $featuredPhoto['quality_pass'] ?? null,
                ]);
            } else {
                $warning = 'No usable featured image found.';
                $warnings[] = $warning;
                $this->recordPhotoActivity($articleId, 'featured_missing', false, (string) $featuredSearch, null, [
                    'quality_context' => 'featured',
                    'preferred_sources' => array_values($preferredSources),
                ]);
                $this->emit($send, 'warning', $warning, 'photos', 'featured_missing');
            }
        }

        return [
            'html' => $this->cleanupHydratedHtml($html),
            'photo_suggestions' => $hydratedSuggestions,
            'featured_photo' => $featuredPhoto,
            'featured_url' => $featuredUrl,
            'featured_meta' => $featuredMeta,
            'inline_count' => $inlineCount,
            'featured_count' => $featuredPhoto ? 1 : 0,
            'warnings' => $warnings,
        ];
    }

    /**
     * @param array<int, string> $preferredSources
     * @param array<int, string> $usedUrls
     * @return array<string, mixed>|null
     */
    private function pickPhoto(
        string $searchTerm,
        array $preferredSources,
        array $usedUrls = [],
        string $qualityContext = 'inline',
        ?int $articleId = null
    ): ?array
    {
        $candidates = [];
        foreach ($this->resolveProviders($preferredSources, $qualityContext) as $provider) {
            $photos = $this->searchProvider($provider, $searchTerm);
            $this->recordProviderSearch($articleId, $provider, $searchTerm, $qualityContext, $photos);
            foreach ($photos as $photo) {
                $url = (string) ($photo['url_large'] ?? $photo['url'] ?? '');
                if ($url === '' || in_array($url, $usedUrls, true)) {
                    continue;
                }
                $candidates[] = $photo;
            }
        }

        if (empty($candidates)) {
            return null;
        }

        $ranked = $this->mediaSearch->rankPhotos($candidates, $qualityContext, true);
        $this->activities->record($articleId, [
            'activity_group' => 'campaign-images:' . md5($searchTerm . '|' . $qualityContext),
            'activity_type' => 'image_search',
            'stage' => 'images',
            'substage' => 'ranked_candidates',
            'status' => 'success',
            'provider' => implode(',', $this->resolveProviders($preferredSources, $qualityContext)),
            'method' => 'rankPhotos',
            'success' => true,
            'message' => count($ranked) . ' ranked image candidate(s).',
            'request_payload' => [
                'query' => $searchTerm,
                'quality_context' => $qualityContext,
            ],
            'response_payload' => [
                'ranked_candidates' => array_slice($ranked, 0, 12),
            ],
        ]);
        foreach ($ranked as $candidate) {
            if (!empty($candidate['quality_pass'])) {
                return $candidate;
            }
        }

        return $ranked[0] ?? null;
    }

    /**
     * @param array<int, string> $preferredSources
     * @return array<int, string>
     */
    private function resolveProviders(array $preferredSources, string $qualityContext = 'inline'): array
    {
        $providers = [];

        if (Setting::getValue('use_google_image_search', '0') === '1' && class_exists(\hexa_package_google_cse\Services\GoogleCseService::class)) {
            $providers[] = 'google-cse';
        }

        if (Setting::getValue('use_serpapi_search', '0') === '1' && class_exists(\hexa_package_serpapi\Services\SerpApiService::class)) {
            $providers[] = 'google';
        }

        if ($qualityContext === 'featured') {
            return $providers;
        }

        foreach ($preferredSources as $source) {
            $normalized = strtolower(trim((string) $source));
            if ($normalized !== '' && !in_array($normalized, $providers, true)) {
                $providers[] = $normalized;
            }
        }

        return !empty($providers) ? $providers : ['pexels', 'pixabay'];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function searchProvider(string $provider, string $searchTerm): array
    {
        return match ($provider) {
            'google-cse' => $this->searchGoogleCse($searchTerm),
            'google', 'serpapi' => $this->searchSerp($searchTerm),
            default => $this->searchStock($searchTerm, $provider),
        };
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function searchGoogleCse(string $searchTerm): array
    {
        if (!class_exists(\hexa_package_google_cse\Services\GoogleCseService::class)) {
            return [];
        }

        $service = app(\hexa_package_google_cse\Services\GoogleCseService::class);
        if ($service->isQuotaExhausted()) {
            return [];
        }

        $result = $service->searchImages($searchTerm, 8, 1);
        return array_values((array) ($result['data']['photos'] ?? []));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function searchSerp(string $searchTerm): array
    {
        if (!class_exists(\hexa_package_serpapi\Services\SerpApiService::class)) {
            return [];
        }

        $result = app(\hexa_package_serpapi\Services\SerpApiService::class)->searchImages($searchTerm, 8, 0, 'photo');
        return array_values((array) ($result['data']['photos'] ?? []));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function searchStock(string $searchTerm, string $provider): array
    {
        $result = $this->mediaSearch->searchPhotos($searchTerm, [$provider], 8, 1);
        return array_values((array) ($result['photos'] ?? []));
    }

    /**
     * @return array{alt_text: string, caption: string, filename: string}
     */
    private function buildMeta(
        array $photo,
        string $searchTerm,
        string $articleTitle,
        string $articleText,
        ?string $altHint = null,
        ?string $captionHint = null,
        ?string $filenameHint = null
    ): array {
        $source = strtolower((string) ($photo['source'] ?? ''));
        $providerAlt = $this->cleanText((string) ($photo['alt'] ?? ''));
        $searchTerm = $this->cleanText($searchTerm);
        $articleTitle = $this->cleanText($articleTitle);

        $baseAlt = $this->cleanText((string) $altHint);
        if ($baseAlt === '') {
            $baseAlt = $providerAlt !== '' ? $providerAlt : ($searchTerm !== '' ? $searchTerm : $articleTitle);
        }

        $alt = Str::limit(rtrim($baseAlt, '.!?'), 125, '');
        $caption = $this->cleanText((string) $captionHint);
        if ($caption === '') {
            $caption = in_array($source, ['google-cse', 'google'], true) && $providerAlt !== ''
                ? $this->ensureSentence($providerAlt . ($articleTitle !== '' ? ' related to ' . $articleTitle : ''))
                : $this->ensureSentence('Illustration related to ' . ($searchTerm !== '' ? $searchTerm : Str::limit(strip_tags($articleText), 80, '')));
        }

        $filenameSource = $this->cleanText((string) $filenameHint);
        if ($filenameSource === '') {
            $filenameSource = $searchTerm !== '' ? $searchTerm : ($providerAlt !== '' ? $providerAlt : 'article-image');
        }

        $filename = Str::limit(Str::slug($filenameSource), 80, '');

        return [
            'alt_text' => $alt !== '' ? $alt : 'Article image',
            'caption' => $caption !== '' ? $caption : 'Article illustration.',
            'filename' => $filename !== '' ? $filename : 'article-image',
        ];
    }

    /**
     * Insert a real figure where the AI left the placeholder block.
     *
     * @param array<string, mixed> $photo
     * @param array{alt_text: string, caption: string, filename: string} $meta
     */
    private function replacePlaceholder(string $html, int $position, array $photo, array $meta): string
    {
        $imgUrl = (string) ($photo['url_large'] ?? $photo['url'] ?? $photo['url_thumb'] ?? '');
        if ($imgUrl === '') {
            return $html;
        }

        $figure = '<figure class="campaign-auto-photo">'
            . '<img src="' . e($imgUrl) . '" alt="' . e($meta['alt_text']) . '">'
            . '<figcaption>' . e($meta['caption']) . '</figcaption>'
            . '</figure>';

        $pattern = '/<div[^>]*class="[^"]*photo-placeholder[^"]*"[^>]*data-idx="' . $position . '"[^>]*>.*?<\/div>(?:\s*<style[^>]*>@keyframes spin\{to\{transform:rotate\(360deg\)\}\}<\/style>)?(?:\s*<\/div>)?/is';
        if (preg_match($pattern, $html)) {
            return preg_replace($pattern, $figure, $html, 1) ?? $html;
        }

        return $html;
    }

    private function cleanupHydratedHtml(string $html): string
    {
        $html = preg_replace('/<\/figure>\s*<\/div>/i', '</figure>', $html) ?? $html;
        $html = preg_replace('/<div[^>]*class="[^"]*photo-placeholder[^"]*"[^>]*>.*?<\/div>/is', '', $html) ?? $html;
        $html = preg_replace('/<span[^>]*>\s*Loading photo\.\.\.\s*<\/span>/i', '', $html) ?? $html;
        $html = preg_replace('/<p[^>]*>\s*<\/p>/i', '', $html) ?? $html;

        return trim($html);
    }

    private function emit(callable $send, string $type, string $message, string $stage, string $substage, array $extra = []): void
    {
        $send($type, $message, array_merge($extra, [
            'stage' => $stage,
            'substage' => $substage,
        ]));
    }

    private function cleanText(string $value): string
    {
        $value = html_entity_decode(strip_tags($value), ENT_QUOTES, 'UTF-8');
        $value = preg_replace('/\s+/u', ' ', $value);

        return trim((string) $value, " \t\n\r\0\x0B\"'");
    }

    private function ensureSentence(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        return preg_match('/[.!?]$/', $value) ? $value : ($value . '.');
    }

    /**
     * @param array<int, array<string, mixed>> $photos
     */
    private function recordProviderSearch(?int $articleId, string $provider, string $searchTerm, string $qualityContext, array $photos): void
    {
        $this->activities->record($articleId, [
            'activity_group' => 'campaign-images:' . md5($searchTerm . '|' . $qualityContext),
            'activity_type' => 'image_search',
            'stage' => 'images',
            'substage' => 'provider_search',
            'status' => !empty($photos) ? 'success' : 'empty',
            'provider' => $provider,
            'method' => 'searchProvider',
            'success' => !empty($photos),
            'message' => count($photos) . ' image candidate(s) returned from ' . $provider . '.',
            'request_payload' => [
                'query' => $searchTerm,
                'quality_context' => $qualityContext,
                'provider' => $provider,
            ],
            'response_payload' => [
                'photos' => array_slice($photos, 0, 12),
            ],
        ]);
    }

    /**
     * @param array<string, mixed>|null $photo
     * @param array<string, mixed> $meta
     */
    private function recordPhotoActivity(?int $articleId, string $substage, bool $success, string $searchTerm, ?array $photo, array $meta = []): void
    {
        $this->activities->record($articleId, [
            'activity_group' => 'campaign-images:' . md5($searchTerm . '|' . $substage),
            'activity_type' => 'image_search',
            'stage' => 'images',
            'substage' => $substage,
            'status' => $success ? 'success' : 'failed',
            'provider' => $photo['source'] ?? null,
            'method' => 'pickPhoto',
            'success' => $success,
            'title' => $photo['alt'] ?? null,
            'url' => $photo['url_large'] ?? $photo['url'] ?? $photo['source_url'] ?? null,
            'message' => $success ? 'Image selected.' : 'Image selection failed.',
            'request_payload' => [
                'query' => $searchTerm,
            ],
            'response_payload' => [
                'selected' => $photo,
            ],
            'meta' => $meta,
        ]);
    }
}
