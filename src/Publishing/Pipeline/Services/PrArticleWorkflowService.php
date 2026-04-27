<?php

namespace hexa_app_publish\Publishing\Pipeline\Services;

class PrArticleWorkflowService
{
    public function defaultState(): array
    {
        return [
            'main_subject_id' => null,
            'focus_instructions' => '',
            'subject_position' => '',
            'promotional_level' => 'editorial-subtle',
            'tone' => 'journalistic',
            'quote_guidance' => '',
            'quote_count' => 2,
            'include_subject_name_in_title' => true,
            'feature_photo_mode' => 'featured_and_inline',
            'inline_photo_target' => 3,
            'expert_source_mode' => 'keywords',
            'expert_keywords' => '',
            'expert_context_url' => '',
            'expert_context_extracted' => [],
        ];
    }

    public function normalizeState(array $state): array
    {
        $normalized = array_replace_recursive($this->defaultState(), $state);

        $normalized['main_subject_id'] = $normalized['main_subject_id'] ? (int) $normalized['main_subject_id'] : null;
        $normalized['focus_instructions'] = trim((string) ($normalized['focus_instructions'] ?? ''));
        $normalized['subject_position'] = trim((string) ($normalized['subject_position'] ?? ''));
        $normalized['promotional_level'] = trim((string) ($normalized['promotional_level'] ?? 'editorial-subtle'));
        $normalized['tone'] = trim((string) ($normalized['tone'] ?? 'journalistic'));
        $normalized['quote_guidance'] = trim((string) ($normalized['quote_guidance'] ?? ''));
        $normalized['quote_count'] = max(0, (int) ($normalized['quote_count'] ?? 0));
        $normalized['include_subject_name_in_title'] = (bool) ($normalized['include_subject_name_in_title'] ?? true);
        $normalized['feature_photo_mode'] = trim((string) ($normalized['feature_photo_mode'] ?? 'featured_and_inline'));
        $normalized['inline_photo_target'] = max(0, min(6, (int) ($normalized['inline_photo_target'] ?? 0)));
        $normalized['expert_source_mode'] = trim((string) ($normalized['expert_source_mode'] ?? 'keywords'));
        $normalized['expert_keywords'] = trim((string) ($normalized['expert_keywords'] ?? ''));
        $normalized['expert_context_url'] = trim((string) ($normalized['expert_context_url'] ?? ''));
        $normalized['expert_context_extracted'] = is_array($normalized['expert_context_extracted'] ?? null)
            ? [
                'url' => trim((string) ($normalized['expert_context_extracted']['url'] ?? '')),
                'title' => trim((string) ($normalized['expert_context_extracted']['title'] ?? '')),
                'excerpt' => trim((string) ($normalized['expert_context_extracted']['excerpt'] ?? '')),
                'text' => trim((string) ($normalized['expert_context_extracted']['text'] ?? '')),
                'word_count' => (int) ($normalized['expert_context_extracted']['word_count'] ?? 0),
                'image_url' => trim((string) ($normalized['expert_context_extracted']['image_url'] ?? '')),
            ]
            : [];

        return $normalized;
    }

    public function promptSlug(string $articleType, bool $polish = false): ?string
    {
        return match ($articleType) {
            'pr-full-feature' => $polish ? 'pr-full-feature-polish' : 'pr-full-feature-spin',
            'expert-article' => $polish ? 'expert-article-polish' : 'expert-article-spin',
            default => null,
        };
    }
}
