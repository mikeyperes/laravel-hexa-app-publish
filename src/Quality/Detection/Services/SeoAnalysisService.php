<?php

namespace hexa_app_publish\Quality\Detection\Services;

/**
 * SeoAnalysisService — article SEO scoring and analysis.
 *
 * Analyzes: word count, readability (Flesch-Kincaid), heading structure,
 * links, images, alt text, paragraph structure, title length.
 */
class SeoAnalysisService
{
    /**
     * Analyze SEO metrics for article content.
     *
     * @param string $html Raw HTML content
     * @param string $title Article title
     * @return array{score: int, word_count: int, sentence_count: int, avg_words_per_sentence: float, flesch_readability: float, heading_count: int, has_h2: bool, link_count: int, image_count: int, images_with_alt: int, paragraph_count: int, title_length: int}
     */
    public function analyze(string $html, string $title = ''): array
    {
        $text = strip_tags($html);
        $wordCount = str_word_count($text);
        $sentenceCount = max(1, preg_match_all('/[.!?]+/', $text));
        $avgWordsPerSentence = $wordCount / $sentenceCount;

        // Flesch-Kincaid readability
        $syllableCount = $this->countSyllables($text);
        $fleschScore = 206.835 - (1.015 * $avgWordsPerSentence) - (84.6 * ($syllableCount / max(1, $wordCount)));
        $fleschScore = max(0, min(100, $fleschScore));

        // Heading structure
        preg_match_all('/<h([1-6])[^>]*>/i', $html, $headingMatches);
        $headingCount = count($headingMatches[0]);
        $hasH2 = in_array('2', $headingMatches[1] ?? []);

        // Links
        preg_match_all('/<a\s/i', $html, $linkMatches);
        $linkCount = count($linkMatches[0]);

        // Images
        preg_match_all('/<img\s/i', $html, $imgMatches);
        $imageCount = count($imgMatches[0]);

        // Images with alt text
        preg_match_all('/<img[^>]+alt\s*=\s*"[^"]+"/i', $html, $imgAltMatches);
        $imagesWithAlt = count($imgAltMatches[0]);

        // Paragraph count
        preg_match_all('/<p[\s>]/i', $html, $pMatches);
        $paragraphCount = count($pMatches[0]);

        // Title length
        $titleLength = strlen($title);
        $titleScore = ($titleLength >= 30 && $titleLength <= 70) ? 10 : ($titleLength > 0 ? 5 : 0);

        // Scoring
        $score = 0;
        if ($wordCount >= 800) $score += 15;
        elseif ($wordCount >= 500) $score += 10;
        elseif ($wordCount >= 300) $score += 5;

        if ($fleschScore >= 60) $score += 20;
        elseif ($fleschScore >= 40) $score += 15;
        elseif ($fleschScore >= 20) $score += 10;
        else $score += 5;

        if ($hasH2 && $headingCount >= 3) $score += 15;
        elseif ($headingCount >= 2) $score += 10;
        elseif ($headingCount >= 1) $score += 5;

        if ($linkCount >= 3) $score += 10;
        elseif ($linkCount >= 1) $score += 5;

        if ($imageCount >= 2) $score += 10;
        elseif ($imageCount >= 1) $score += 7;

        if ($imageCount > 0 && $imagesWithAlt === $imageCount) $score += 10;
        elseif ($imagesWithAlt > 0) $score += 5;

        $score += $titleScore;

        if ($paragraphCount >= 5) $score += 10;
        elseif ($paragraphCount >= 3) $score += 7;
        elseif ($paragraphCount >= 1) $score += 3;

        return [
            'score' => min(100, $score),
            'word_count' => $wordCount,
            'sentence_count' => $sentenceCount,
            'avg_words_per_sentence' => round($avgWordsPerSentence, 1),
            'flesch_readability' => round($fleschScore, 1),
            'heading_count' => $headingCount,
            'has_h2' => $hasH2,
            'link_count' => $linkCount,
            'image_count' => $imageCount,
            'images_with_alt' => $imagesWithAlt,
            'paragraph_count' => $paragraphCount,
            'title_length' => $titleLength,
        ];
    }

    /**
     * @param string $text
     * @return int
     */
    private function countSyllables(string $text): int
    {
        $words = preg_split('/\s+/', strtolower($text));
        $total = 0;
        foreach ($words as $word) {
            $word = preg_replace('/[^a-z]/', '', $word);
            if (strlen($word) <= 3) { $total += 1; continue; }
            $word = preg_replace('/(?:[^laeiouy]es|ed|[^laeiouy]e)$/', '', $word);
            preg_match_all('/[aeiouy]{1,2}/', $word, $matches);
            $total += max(1, count($matches[0]));
        }
        return $total;
    }
}
