<?php

namespace hexa_app_publish\Services;

use hexa_app_publish\Models\PublishLinkList;
use hexa_app_publish\Models\PublishSitemap;
use hexa_package_anthropic\Services\AnthropicService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class LinkInsertionService
{
    /**
     * Get available links for a user (backlinks + sitemap URLs).
     *
     * @param int $userId
     * @param int $maxLinks Max links to return.
     * @return array
     */
    public function getAvailableLinks(int $userId, int $maxLinks = 10): array
    {
        $links = PublishLinkList::where('user_id', $userId)
            ->where('active', true)
            ->orderByDesc('priority')
            ->orderBy('times_used')
            ->limit($maxLinks)
            ->get()
            ->map(fn($l) => [
                'id' => $l->id,
                'url' => $l->url,
                'anchor_text' => $l->anchor_text,
                'context' => $l->context,
                'type' => $l->type,
                'times_used' => $l->times_used,
            ])
            ->toArray();

        return $links;
    }

    /**
     * Parse a sitemap URL and cache the results.
     *
     * @param PublishSitemap $sitemap
     * @return array{success: bool, message: string, url_count: int}
     */
    public function parseSitemap(PublishSitemap $sitemap): array
    {
        try {
            $response = Http::timeout(30)->get($sitemap->sitemap_url);

            if (!$response->successful()) {
                return ['success' => false, 'message' => "HTTP {$response->status()} fetching sitemap.", 'url_count' => 0];
            }

            $xml = @simplexml_load_string($response->body());
            if (!$xml) {
                return ['success' => false, 'message' => 'Failed to parse XML.', 'url_count' => 0];
            }

            $urls = [];

            // Standard sitemap format
            if (isset($xml->url)) {
                foreach ($xml->url as $entry) {
                    $urls[] = (string) $entry->loc;
                }
            }

            // Sitemap index format (sitemap of sitemaps)
            if (isset($xml->sitemap)) {
                foreach ($xml->sitemap as $entry) {
                    $urls[] = (string) $entry->loc;
                }
            }

            $sitemap->update([
                'parsed_urls' => $urls,
                'url_count' => count($urls),
                'last_parsed_at' => now(),
            ]);

            return ['success' => true, 'message' => count($urls) . ' URLs parsed.', 'url_count' => count($urls)];

        } catch (\Exception $e) {
            Log::error('LinkInsertionService::parseSitemap error', ['url' => $sitemap->sitemap_url, 'error' => $e->getMessage()]);
            return ['success' => false, 'message' => 'Error: ' . $e->getMessage(), 'url_count' => 0];
        }
    }

    /**
     * Use AI to insert links naturally into article content.
     *
     * @param string $htmlContent Article HTML content.
     * @param array $links Array of links to insert: [['url' => '...', 'anchor_text' => '...', 'context' => '...'], ...]
     * @param int $maxLinks Maximum number of links to insert.
     * @return array{success: bool, message: string, data: array|null}
     */
    public function insertLinks(string $htmlContent, array $links, int $maxLinks = 5): array
    {
        if (empty($links)) {
            return ['success' => false, 'message' => 'No links provided.', 'data' => null];
        }

        $linksToInsert = array_slice($links, 0, $maxLinks);

        $linkList = '';
        foreach ($linksToInsert as $i => $link) {
            $num = $i + 1;
            $linkList .= "{$num}. URL: {$link['url']}";
            if (!empty($link['anchor_text'])) {
                $linkList .= " | Preferred anchor: {$link['anchor_text']}";
            }
            if (!empty($link['context'])) {
                $linkList .= " | Context: {$link['context']}";
            }
            $linkList .= "\n";
        }

        $systemPrompt = "You are a professional editor. Your task is to insert hyperlinks naturally into the article HTML content. "
            . "Rules:\n"
            . "- Insert each link exactly ONCE\n"
            . "- Place links where they are contextually relevant\n"
            . "- Use the preferred anchor text if provided, otherwise choose natural anchor text from existing words\n"
            . "- Do NOT add new sentences just to fit a link — weave them into existing text\n"
            . "- Do NOT change the article content, structure, or meaning\n"
            . "- Output the modified HTML with links inserted as <a href=\"URL\" target=\"_blank\">anchor</a>\n"
            . "- After the HTML, output a JSON block on a new line starting with LINK_REPORT: followed by a JSON array of objects: [{\"url\": \"...\", \"anchor_text\": \"...\", \"placed\": true/false, \"reason\": \"...\"}]\n"
            . "- Output ONLY the modified HTML followed by the LINK_REPORT line. No other text.";

        $userMessage = "Links to insert:\n{$linkList}\n\nArticle HTML:\n\n{$htmlContent}";

        $result = app(AnthropicService::class)->chat($systemPrompt, $userMessage, 'claude-sonnet-4-20250514', 8192);

        if (!$result['success']) {
            return $result;
        }

        $response = $result['data']['content'] ?? '';

        // Parse the response: HTML + LINK_REPORT
        $parts = preg_split('/\nLINK_REPORT:\s*/i', $response, 2);
        $modifiedHtml = trim($parts[0] ?? '');
        $report = [];

        if (isset($parts[1])) {
            $reportJson = trim($parts[1]);
            $decoded = json_decode($reportJson, true);
            if (is_array($decoded)) {
                $report = $decoded;
            }
        }

        // Mark used links
        foreach ($report as $item) {
            if (!empty($item['placed']) && !empty($item['url'])) {
                $linkModel = PublishLinkList::where('url', $item['url'])->first();
                if ($linkModel) {
                    $linkModel->markUsed();
                }
            }
        }

        return [
            'success' => true,
            'message' => 'Links inserted. ' . count(array_filter($report, fn($r) => !empty($r['placed']))) . '/' . count($linksToInsert) . ' placed.',
            'data' => [
                'html' => $modifiedHtml,
                'report' => $report,
            ],
        ];
    }
}
