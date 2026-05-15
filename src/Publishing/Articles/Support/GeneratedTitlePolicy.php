<?php

namespace hexa_app_publish\Publishing\Articles\Support;

use Illuminate\Support\Str;

class GeneratedTitlePolicy
{
    public function promptRulesText(?string $articleType, int $titleCount = 10): string
    {
        return "TITLE AND METADATA RULES:\n- " . implode("\n- ", $this->promptRuleLines($articleType, $titleCount));
    }

    /**
     * @return array<int, string>
     */
    public function promptRuleLines(?string $articleType, int $titleCount = 10): array
    {
        $rules = [
            "Return exactly {$titleCount} distinct headline options. Never return a single title.",
            'Never use em dashes or en dashes. Use commas, periods, colons, or a standard hyphen instead.',
            'Never use first-person phrasing such as I, I\'m, I\'ve, my, we, our, or us.',
        ];

        if (in_array($articleType, ['editorial', 'opinion', 'expert-article', 'pr-full-feature', 'press-release'], true)) {
            $rules[] = 'Keep every headline in third person unless you are preserving a direct quote copied from source material.';
        } else {
            $rules[] = 'Prefer third person in every headline unless the workflow explicitly requests a first-person essay.';
        }

        if (in_array($articleType, ['expert-article', 'pr-full-feature'], true)) {
            $rules[] = 'Make the headline read like a sharp editorial hook or thesis, not puffed attribution.';
            $rules[] = 'Do not use fluffy constructions such as "Name on ...", "Name says ...", "Name thinks ...", "Name believes ...", "Name explains ...", "What Name thinks ...", or "Why Name believes ...".';
            $rules[] = 'If you use the subject name, put it early and pair it with a real hook, ideally in the form "Name: [statement]" or another tight editorial construction.';
            $rules[] = 'Keep these headlines concise. Aim for roughly 45 to 75 characters when possible, and never exceed 88 characters.';
            $rules[] = 'Front-load the person or company when it matters for SEO. Do not bury the subject after a long generic setup.';
        } elseif ($articleType === 'press-release') {
            $rules[] = 'Keep press release headlines concise and front-load the company, executive, or core announcement when possible.';
            $rules[] = 'Aim for roughly 45 to 80 characters when possible, and never exceed 95 characters.';
        } else {
            $rules[] = 'Keep headlines concise, publication-ready, and SEO-friendly. Avoid bloated, stitched, or repetitive phrasing.';
        }

        return $rules;
    }

    /**
     * @param array<int, string> $titles
     * @param array<int, string> $sourceTitles
     * @return array<int, string>
     */
    public function filterValidTitles(array $titles, ?string $articleType, array $sourceTitles = []): array
    {
        $normalizedTitles = collect($titles)
            ->map(fn ($title) => $this->normalizeTitleText((string) $title))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $normalizedSourceTitles = collect($sourceTitles)
            ->map(fn ($title) => $this->normalizeTitleText((string) $title))
            ->filter()
            ->values()
            ->all();

        $validTitles = [];
        foreach ($normalizedTitles as $candidate) {
            if ($this->titleMatchesArticleType($candidate, $articleType, $normalizedSourceTitles)) {
                $validTitles[] = $candidate;
            }
        }

        return array_values(array_unique($validTitles));
    }

    public function normalizeTitleText(string $title): string
    {
        $title = html_entity_decode(strip_tags($title), ENT_QUOTES, 'UTF-8');
        $title = $this->normalizeGeneratedPunctuation($title);
        $title = preg_replace('/\s+/', ' ', (string) $title);

        return trim((string) $title);
    }

    /**
     * @param array<int, string> $sourceTitles
     */
    public function titleMatchesArticleType(string $title, ?string $articleType, array $sourceTitles = []): bool
    {
        if ($title === '') {
            return false;
        }

        [$minLength, $maxLength] = $this->titleLengthBounds($articleType);
        $titleLength = mb_strlen($title);
        if ($titleLength < $minLength || $titleLength > $maxLength) {
            return false;
        }

        if (str_contains($title, '...') || preg_match('/\.\.\.$/', $title)) {
            return false;
        }

        if (preg_match('/\b(source|sources|round-?up|live updates?)\b/i', $title)) {
            return false;
        }

        if ($this->titleUsesFirstPerson($title)) {
            return false;
        }

        foreach ($sourceTitles as $sourceTitle) {
            if ($this->titlesAreTooSimilar($title, $sourceTitle)) {
                return false;
            }
        }

        if (in_array($articleType, ['editorial', 'opinion'], true) && preg_match('/^(breaking|live|watch)\b/i', $title)) {
            return false;
        }

        if (in_array($articleType, ['news-report', 'local-news'], true) && str_contains($title, '?')) {
            return false;
        }

        if (in_array($articleType, ['pr-full-feature', 'expert-article'], true)) {
            if (preg_match('/\b(?:a|an)?\s*(?:feature|expert|company|person|executive)\s+profile\b/i', $title)) {
                return false;
            }

            if (preg_match('/^[^:]+:\s*(?:a|an)?\s*(?:feature|expert|company|person|executive)\s+profile$/i', $title)) {
                return false;
            }

            if ($this->titleUsesFluffyExpertOrPrPattern($title)) {
                return false;
            }
        }

        return true;
    }

    private function titleLengthBounds(?string $articleType): array
    {
        return match ($articleType) {
            'expert-article', 'pr-full-feature' => [30, 88],
            'press-release' => [28, 95],
            default => [24, 110],
        };
    }

    private function titleUsesFirstPerson(string $title): bool
    {
        return preg_match("/\\b(i|im|i'm|i’m|ive|i've|i’ve|id|i'd|i’d|ill|i'll|i’ll|me|my|mine|myself|we|we're|we’re|weve|we've|we’ve|our|ours|us)\\b/i", $title) === 1;
    }

    private function titleUsesFluffyExpertOrPrPattern(string $title): bool
    {
        $patterns = [
            '/^[^:]{2,90}\s+on\s+/i',
            '/^[^:]{2,90}\s+(?:says|thinks|believes|explains|sees|views|argues|discusses|shares|reveals|predicts|warns)\b/i',
            '/^(?:what|why|how)\s+[^:]{2,90}\s+(?:says|thinks|believes|explains|sees|argues|reveals|predicts|warns)\b/i',
            '/\bexplains\s+why\b/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $title)) {
                return true;
            }
        }

        return false;
    }

    private function titlesAreTooSimilar(string $left, string $right): bool
    {
        $left = Str::of(Str::lower($left))->replaceMatches('/[^a-z0-9\s]/', ' ')->replaceMatches('/\s+/', ' ')->trim()->value();
        $right = Str::of(Str::lower($right))->replaceMatches('/[^a-z0-9\s]/', ' ')->replaceMatches('/\s+/', ' ')->trim()->value();

        if ($left === '' || $right === '') {
            return false;
        }

        if ($left === $right) {
            return true;
        }

        similar_text($left, $right, $percent);
        if ($percent >= 72.0) {
            return true;
        }

        $leftTokens = array_values(array_filter(explode(' ', $left)));
        $rightTokens = array_values(array_filter(explode(' ', $right)));
        if ($leftTokens === [] || $rightTokens === []) {
            return false;
        }

        $leftSet = array_unique($leftTokens);
        $rightSet = array_unique($rightTokens);
        $overlap = array_intersect($leftSet, $rightSet);
        $coverage = count($overlap) / max(1, min(count($leftSet), count($rightSet)));

        return $coverage >= 0.8;
    }

    private function normalizeGeneratedPunctuation(string $text): string
    {
        return str_replace(['—', '–', '―'], '-', $text);
    }
}
