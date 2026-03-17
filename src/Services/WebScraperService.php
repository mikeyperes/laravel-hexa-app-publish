<?php

namespace hexa_app_publish\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WebScraperService
{
    /**
     * Fetch and extract article content from a URL.
     *
     * @param string $url The article URL to scrape.
     * @return array{success: bool, message: string, data: array|null}
     */
    public function extractArticle(string $url): array
    {
        try {
            $response = Http::withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (compatible; HWSPublishBot/1.0)',
                    'Accept' => 'text/html,application/xhtml+xml',
                ])
                ->timeout(20)
                ->get($url);

            if (!$response->successful()) {
                return ['success' => false, 'message' => "HTTP {$response->status()} fetching {$url}.", 'data' => null];
            }

            $html = $response->body();

            // Extract title
            $title = $this->extractTag($html, 'title');
            $ogTitle = $this->extractMeta($html, 'og:title');
            $title = $ogTitle ?: $title;

            // Extract meta description
            $description = $this->extractMeta($html, 'description');
            $ogDescription = $this->extractMeta($html, 'og:description');
            $description = $ogDescription ?: $description;

            // Extract featured image
            $ogImage = $this->extractMeta($html, 'og:image');

            // Extract main content using <article> tag or largest text block
            $articleContent = $this->extractArticleBody($html);

            // Extract plain text
            $plainText = strip_tags($articleContent);
            $plainText = preg_replace('/\s+/', ' ', $plainText);
            $plainText = trim($plainText);

            $wordCount = str_word_count($plainText);

            return [
                'success' => true,
                'message' => "Extracted article: {$wordCount} words.",
                'data' => [
                    'title' => $title,
                    'description' => $description,
                    'content_html' => $articleContent,
                    'content_text' => $plainText,
                    'image' => $ogImage,
                    'word_count' => $wordCount,
                    'url' => $url,
                ],
            ];
        } catch (\Exception $e) {
            Log::error('WebScraperService::extractArticle error', ['url' => $url, 'error' => $e->getMessage()]);
            return ['success' => false, 'message' => 'Error: ' . $e->getMessage(), 'data' => null];
        }
    }

    /**
     * Extract content from the first matching HTML tag.
     *
     * @param string $html
     * @param string $tag
     * @return string
     */
    private function extractTag(string $html, string $tag): string
    {
        if (preg_match("/<{$tag}[^>]*>(.*?)<\/{$tag}>/si", $html, $m)) {
            return trim(strip_tags($m[1]));
        }
        return '';
    }

    /**
     * Extract meta tag content by name or property.
     *
     * @param string $html
     * @param string $name
     * @return string
     */
    private function extractMeta(string $html, string $name): string
    {
        // Match property="..." or name="..."
        if (preg_match('/<meta[^>]+(?:property|name)\s*=\s*["\']' . preg_quote($name, '/') . '["\'][^>]+content\s*=\s*["\']([^"\']*)["\'][^>]*>/si', $html, $m)) {
            return trim($m[1]);
        }
        // Reversed order (content before property)
        if (preg_match('/<meta[^>]+content\s*=\s*["\']([^"\']*)["\'][^>]+(?:property|name)\s*=\s*["\']' . preg_quote($name, '/') . '["\'][^>]*>/si', $html, $m)) {
            return trim($m[1]);
        }
        return '';
    }

    /**
     * Extract the main article body from HTML.
     *
     * @param string $html
     * @return string
     */
    private function extractArticleBody(string $html): string
    {
        // Remove script and style tags
        $clean = preg_replace('/<script[^>]*>.*?<\/script>/si', '', $html);
        $clean = preg_replace('/<style[^>]*>.*?<\/style>/si', '', $clean);
        $clean = preg_replace('/<!--.*?-->/s', '', $clean);

        // Try <article> tag first
        if (preg_match('/<article[^>]*>(.*?)<\/article>/si', $clean, $m)) {
            return $this->cleanHtml($m[1]);
        }

        // Try common content containers
        $selectors = [
            'class\s*=\s*["\'][^"\']*(?:entry-content|article-body|post-content|story-body|article-content|main-content)[^"\']*["\']',
            'id\s*=\s*["\'](?:content|article|main-content|story|post-content)["\']',
            'role\s*=\s*["\']main["\']',
        ];

        foreach ($selectors as $selector) {
            if (preg_match('/<div[^>]+' . $selector . '[^>]*>(.*?)<\/div>/si', $clean, $m)) {
                return $this->cleanHtml($m[1]);
            }
        }

        // Fallback: collect all <p> tags
        preg_match_all('/<p[^>]*>(.*?)<\/p>/si', $clean, $matches);
        if (!empty($matches[1])) {
            $paragraphs = array_filter($matches[1], fn($p) => str_word_count(strip_tags($p)) > 10);
            if (!empty($paragraphs)) {
                return '<p>' . implode('</p><p>', array_values($paragraphs)) . '</p>';
            }
        }

        return '';
    }

    /**
     * Clean extracted HTML to keep only safe tags.
     *
     * @param string $html
     * @return string
     */
    private function cleanHtml(string $html): string
    {
        return strip_tags($html, '<p><br><h1><h2><h3><h4><h5><h6><ul><ol><li><a><strong><b><em><i><blockquote><img>');
    }
}
