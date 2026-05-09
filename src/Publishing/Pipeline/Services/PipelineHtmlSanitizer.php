<?php

namespace hexa_app_publish\Publishing\Pipeline\Services;

class PipelineHtmlSanitizer
{
    public function sanitizeForPersistence(?string $html): string
    {
        $html = trim((string) $html);
        if ($html === "") {
            return "";
        }

        $html = $this->stripPhotoPlaceholders($html);
        $html = $this->stripDanglingPhotoControls($html);
        $html = $this->dedupeManagedFigures($html);
        $html = preg_replace("/\n{3,}/", "\n\n", $html) ?? $html;

        return trim($html);
    }

    private function stripPhotoPlaceholders(string $html): string
    {
        return preg_replace("#<div[^>]*class=\"[^\"]*photo-placeholder[^\"]*\"[^>]*>[\\s\\S]*?<\\/div>#i", "", $html) ?? $html;
    }

    private function stripDanglingPhotoControls(string $html): string
    {
        $html = preg_replace("#<span[^>]*class=\"[^\"]*photo-(?:view|confirm|change|remove)[^\"]*\"[^>]*>[\\s\\S]*?<\\/span>#i", "", $html) ?? $html;
        $html = preg_replace("#<(p|div)\\b[^>]*>\\s*(?:&nbsp;|<br\\s*\\/?>|\\s)*<\\/\\1>#i", "", $html) ?? $html;

        return $html;
    }

    private function dedupeManagedFigures(string $html): string
    {
        if ($html === "" || !class_exists(\DOMDocument::class)) {
            return $html;
        }

        $previous = libxml_use_internal_errors(true);
        $dom = new \DOMDocument("1.0", "UTF-8");
        $wrapped = "<?xml encoding=\"utf-8\" ?><div id=\"hexa-sanitize-root\">" . $html . "</div>";
        if (!$dom->loadHTML($wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD)) {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
            return $html;
        }

        $xpath = new \DOMXPath($dom);
        $query = "//figure[contains(concat(\" \", normalize-space(@class), \" \"), \" pr-inline-subject-photo \") or contains(concat(\" \", normalize-space(@class), \" \"), \" press-release-inline-photo \") or contains(concat(\" \", normalize-space(@class), \" \"), \" podcast-inline-guest-photo \")]";
        $figures = $xpath->query($query);
        if ($figures) {
            $seen = [];
            $nodes = [];
            foreach ($figures as $figure) {
                $nodes[] = $figure;
            }
            foreach ($nodes as $figure) {
                $key = trim((string) ($figure->getAttribute("data-inline-photo-key") ?: $figure->getAttribute("data-asset-key")));
                if ($key === "") {
                    $image = $xpath->query(".//img", $figure)?->item(0);
                    if ($image instanceof \DOMElement) {
                        $key = trim((string) ($image->getAttribute("data-source-url") ?: $image->getAttribute("src")));
                    }
                }
                if ($key === "") {
                    continue;
                }
                $key = html_entity_decode($key, ENT_QUOTES | ENT_HTML5, "UTF-8");
                if (isset($seen[$key])) {
                    $figure->parentNode?->removeChild($figure);
                    continue;
                }
                $seen[$key] = true;
            }
        }

        $root = $dom->getElementById("hexa-sanitize-root");
        $result = $root ? $this->innerHtml($root) : $html;
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return $result;
    }

    private function innerHtml(\DOMNode $node): string
    {
        $html = "";
        foreach ($node->childNodes as $child) {
            $html .= $node->ownerDocument?->saveHTML($child) ?? "";
        }

        return $html;
    }
}
