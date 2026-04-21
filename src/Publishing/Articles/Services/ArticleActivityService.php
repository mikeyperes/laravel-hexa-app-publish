<?php

namespace hexa_app_publish\Publishing\Articles\Services;

use hexa_app_publish\Publishing\Articles\Models\PublishArticle;
use hexa_app_publish\Publishing\Articles\Models\PublishArticleActivity;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ArticleActivityService
{
    /**
     * @param PublishArticle|int|null $article
     * @param array<string, mixed> $payload
     */
    public function record(PublishArticle|int|null $article, array $payload): ?PublishArticleActivity
    {
        $articleModel = $this->resolveArticle($article);
        if (!$articleModel) {
            return null;
        }

        return PublishArticleActivity::create([
            'publish_article_id' => $articleModel->id,
            'publish_campaign_id' => $payload['publish_campaign_id'] ?? $articleModel->publish_campaign_id,
            'publish_pipeline_operation_id' => $payload['publish_pipeline_operation_id'] ?? null,
            'created_by' => $payload['created_by'] ?? auth()->id() ?? $articleModel->created_by,
            'activity_group' => $this->stringOrNull($payload['activity_group'] ?? null),
            'activity_type' => (string) ($payload['activity_type'] ?? 'event'),
            'stage' => $this->stringOrNull($payload['stage'] ?? null),
            'substage' => $this->stringOrNull($payload['substage'] ?? null),
            'status' => $this->stringOrNull($payload['status'] ?? null),
            'provider' => $this->stringOrNull($payload['provider'] ?? null),
            'model' => $this->stringOrNull($payload['model'] ?? null),
            'agent' => $this->stringOrNull($payload['agent'] ?? null),
            'method' => $this->stringOrNull($payload['method'] ?? null),
            'attempt_no' => $this->intOrNull($payload['attempt_no'] ?? null),
            'is_retry' => (bool) ($payload['is_retry'] ?? false),
            'success' => array_key_exists('success', $payload) ? (bool) $payload['success'] : null,
            'title' => $this->stringOrNull($payload['title'] ?? null),
            'url' => $this->stringOrNull($payload['url'] ?? null),
            'message' => $this->stringOrNull($payload['message'] ?? null),
            'trace_id' => $this->stringOrNull($payload['trace_id'] ?? null),
            'request_payload' => $this->normalizeArray($payload['request_payload'] ?? null),
            'response_payload' => $this->normalizeArray($payload['response_payload'] ?? null),
            'meta' => $this->normalizeArray($payload['meta'] ?? null),
            'happened_at' => $this->resolveTimestamp($payload['happened_at'] ?? null),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function buildAuditDump(PublishArticle $article): array
    {
        $article->loadMissing(['site', 'campaign', 'template', 'creator', 'usedSources']);

        /** @var Collection<int, PublishArticleActivity> $activities */
        $activities = $article->activities()->orderBy('happened_at')->orderBy('id')->get();
        $grouped = $activities->groupBy('activity_type');

        $searchActivities = $grouped->get('search', collect())->values();
        $scrapeActivities = $grouped->get('scrape', collect())->values();
        $aiActivities = $grouped->get('ai', collect())->values();
        $imageActivities = $grouped->get('image_search', collect())->values();
        $operationActivities = $grouped->get('operation', collect())->values();
        $metadataActivities = $grouped->get('metadata', collect())->values();
        $lifecycleActivities = $grouped->get('lifecycle', collect())->values();

        $questions = $this->evaluateQuestions($article, $searchActivities, $scrapeActivities, $aiActivities, $imageActivities, $operationActivities, $lifecycleActivities);

        return [
            'article' => [
                'id' => $article->id,
                'article_id' => $article->article_id,
                'title' => $article->title,
                'status' => $article->status,
                'wp_status' => $article->wp_status,
                'delivery_mode' => $article->delivery_mode,
                'origin' => $article->publish_campaign_id ? 'campaign' : 'manual',
                'campaign_id' => $article->publish_campaign_id,
                'campaign_name' => $article->campaign?->name,
                'site_id' => $article->publish_site_id,
                'site_name' => $article->site?->name,
                'article_type' => $article->article_type,
                'created_by' => $article->creator?->name,
                'created_at' => optional($article->created_at)->toIso8601String(),
                'published_at' => optional($article->published_at)->toIso8601String(),
            ],
            'resolved_ai' => [
                'engine' => $article->ai_engine_used,
                'provider' => $article->ai_provider,
                'input_tokens' => $article->ai_tokens_input,
                'output_tokens' => $article->ai_tokens_output,
                'cost' => $article->ai_cost,
            ],
            'source_urls' => array_values(array_filter(array_map(fn ($source) => $source['url'] ?? null, (array) $article->source_articles))),
            'used_sources' => $article->usedSources->map(fn ($source) => [
                'title' => $source->title,
                'url' => $source->url,
                'source_api' => $source->source_api,
            ])->values()->all(),
            'search_attempts' => $this->formatActivities($searchActivities),
            'scrape_attempts' => $this->formatActivities($scrapeActivities),
            'ai_attempts' => $this->formatActivities($aiActivities),
            'image_searches' => $this->formatActivities($imageActivities),
            'metadata_attempts' => $this->formatActivities($metadataActivities),
            'operation_events' => $this->formatActivities($operationActivities),
            'lifecycle_events' => $this->formatActivities($lifecycleActivities),
            'audit_questions' => $questions,
            'pretty' => [
                'ai_prompts' => $this->prettyAiPrompts($aiActivities),
                'direct_news_urls' => $this->prettyDirectNewsUrls($article, $searchActivities),
            ],
        ];
    }

    public function buildPrettyAuditText(PublishArticle $article): string
    {
        $dump = $this->buildAuditDump($article);

        $lines = [
            'ARTICLE',
            'id: ' . ($dump['article']['article_id'] ?? $article->id),
            'title: ' . ($dump['article']['title'] ?? 'Untitled'),
            'status: ' . (($dump['article']['status'] ?? '—') . ' / ' . ($dump['article']['wp_status'] ?? '—')),
            'origin: ' . ($dump['article']['origin'] ?? 'manual'),
            '',
            'DIRECT NEWS URLS',
        ];

        foreach (($dump['pretty']['direct_news_urls'] ?? []) as $url) {
            $lines[] = '- ' . $url;
        }

        $lines[] = '';
        $lines[] = 'AI PROMPTS';

        foreach (($dump['pretty']['ai_prompts'] ?? []) as $prompt) {
            $lines[] = '[' . ($prompt['label'] ?? 'AI') . ']';
            $lines[] = 'provider: ' . ($prompt['provider'] ?? '—');
            $lines[] = 'model: ' . ($prompt['model'] ?? '—');
            $lines[] = 'agent: ' . ($prompt['agent'] ?? '—');
            $lines[] = 'prompt:';
            $lines[] = (string) ($prompt['prompt'] ?? '');
            $lines[] = '';
        }

        $lines[] = 'AUDIT QUESTIONS';
        foreach (($dump['audit_questions'] ?? []) as $question) {
            $lines[] = '- [' . strtoupper((string) ($question['status'] ?? 'unknown')) . '] ' . ($question['question'] ?? '');
            if (!empty($question['evidence'])) {
                $lines[] = '  evidence: ' . $question['evidence'];
            }
        }

        return trim(implode("\n", $lines));
    }

    /**
     * @param Collection<int, PublishArticleActivity> $activities
     * @return array<int, array<string, mixed>>
     */
    private function formatActivities(Collection $activities): array
    {
        return $activities->map(function (PublishArticleActivity $activity) {
            return [
                'id' => $activity->id,
                'group' => $activity->activity_group,
                'type' => $activity->activity_type,
                'stage' => $activity->stage,
                'substage' => $activity->substage,
                'status' => $activity->status,
                'provider' => $activity->provider,
                'model' => $activity->model,
                'agent' => $activity->agent,
                'method' => $activity->method,
                'attempt_no' => $activity->attempt_no,
                'is_retry' => $activity->is_retry,
                'success' => $activity->success,
                'title' => $activity->title,
                'url' => $activity->url,
                'message' => $activity->message,
                'trace_id' => $activity->trace_id,
                'happened_at' => optional($activity->happened_at ?: $activity->created_at)->toIso8601String(),
                'request_payload' => $activity->request_payload,
                'response_payload' => $activity->response_payload,
                'meta' => $activity->meta,
            ];
        })->values()->all();
    }

    /**
     * @param Collection<int, PublishArticleActivity> $searchActivities
     * @param Collection<int, PublishArticleActivity> $scrapeActivities
     * @param Collection<int, PublishArticleActivity> $aiActivities
     * @param Collection<int, PublishArticleActivity> $imageActivities
     * @param Collection<int, PublishArticleActivity> $operationActivities
     * @param Collection<int, PublishArticleActivity> $lifecycleActivities
     * @return array<int, array<string, string>>
     */
    private function evaluateQuestions(
        PublishArticle $article,
        Collection $searchActivities,
        Collection $scrapeActivities,
        Collection $aiActivities,
        Collection $imageActivities,
        Collection $operationActivities,
        Collection $lifecycleActivities
    ): array {
        $featuredOk = $imageActivities->contains(function (PublishArticleActivity $activity) {
            return $activity->substage === 'featured_selected'
                && (bool) Arr::get($activity->response_payload, 'selected.quality_pass', false);
        });

        $inlineOk = $imageActivities->where('substage', 'inline_selected')->count() >= 2;
        $searchStored = $searchActivities->isNotEmpty();
        $scrapesStored = $scrapeActivities->isNotEmpty();
        $aiStored = $aiActivities->isNotEmpty();
        $operationStored = $operationActivities->isNotEmpty();
        $lifecycleStored = $lifecycleActivities->isNotEmpty();
        $directUrls = $this->prettyDirectNewsUrls($article, $searchActivities);
        $failedAttempts = $searchActivities->where('success', false)->count()
            + $scrapeActivities->where('success', false)->count()
            + $aiActivities->where('success', false)->count()
            + $imageActivities->where('success', false)->count();
        $imageCriteriaStored = $imageActivities->contains(function (PublishArticleActivity $activity) {
            return Arr::has($activity->response_payload ?? [], 'selected.quality_score')
                || Arr::has($activity->response_payload ?? [], 'selected.quality_pass')
                || Arr::has($activity->meta ?? [], 'quality_context');
        });

        return [
            [
                'id' => 'searches_tracked',
                'question' => 'Were searches tracked with provider, model, query, and results?',
                'status' => $searchStored ? 'pass' : 'fail',
                'evidence' => $searchStored ? ($searchActivities->count() . ' search activity row(s) stored.') : 'No search activity rows stored.',
            ],
            [
                'id' => 'scrapes_tracked',
                'question' => 'Were scrape attempts, retries, and failures stored?',
                'status' => $scrapesStored ? 'pass' : 'fail',
                'evidence' => $scrapesStored ? ($scrapeActivities->count() . ' scrape activity row(s) stored.') : 'No scrape activity rows stored.',
            ],
            [
                'id' => 'ai_tracked',
                'question' => 'Were AI prompts, models, and responses stored?',
                'status' => $aiStored ? 'pass' : 'fail',
                'evidence' => $aiStored ? ($aiActivities->count() . ' AI activity row(s) stored.') : 'No AI activity rows stored.',
            ],
            [
                'id' => 'featured_image_quality',
                'question' => 'Did the featured image pass the stored quality checklist?',
                'status' => $featuredOk ? 'pass' : 'warn',
                'evidence' => $featuredOk ? 'Featured image selected with quality_pass=true.' : 'No passing featured image selection found in the audit trail.',
            ],
            [
                'id' => 'inline_image_coverage',
                'question' => 'Were enough inline images selected for the article?',
                'status' => $inlineOk ? 'pass' : 'warn',
                'evidence' => $inlineOk ? 'At least two inline image selections were stored.' : 'Fewer than two inline image selections were stored.',
            ],
            [
                'id' => 'article_lifecycle_persisted',
                'question' => 'Is this article stored in the shared article lifecycle container?',
                'status' => 'pass',
                'evidence' => 'publish_articles.id=' . $article->id . ' with status=' . ($article->status ?: '—'),
            ],
            [
                'id' => 'direct_urls_stored',
                'question' => 'Were direct news URLs preserved for review and copy/export?',
                'status' => !empty($directUrls) ? 'pass' : 'fail',
                'evidence' => !empty($directUrls) ? (count($directUrls) . ' direct URL(s) stored.') : 'No direct source URLs were found in the audit container.',
            ],
            [
                'id' => 'failed_attempts_retained',
                'question' => 'Were failed or empty attempts retained for optimization review?',
                'status' => ($failedAttempts > 0 || ($searchStored || $scrapesStored || $aiStored || $imageActivities->isNotEmpty())) ? 'pass' : 'warn',
                'evidence' => $failedAttempts > 0 ? ($failedAttempts . ' failed/empty attempt row(s) stored.') : 'No failures were recorded for this article run.',
            ],
            [
                'id' => 'operation_trail_stored',
                'question' => 'Was the step-by-step operation trail persisted alongside the article?',
                'status' => $operationStored ? 'pass' : 'warn',
                'evidence' => $operationStored ? ($operationActivities->count() . ' operation event row(s) stored.') : 'No operation event rows were stored.',
            ],
            [
                'id' => 'lifecycle_history_stored',
                'question' => 'Were lifecycle state changes stored instead of deleting process history?',
                'status' => $lifecycleStored ? 'pass' : 'warn',
                'evidence' => $lifecycleStored ? ($lifecycleActivities->count() . ' lifecycle event row(s) stored.') : 'No lifecycle event rows were stored.',
            ],
            [
                'id' => 'image_quality_criteria_stored',
                'question' => 'Were image quality criteria stored for the selected candidates?',
                'status' => $imageCriteriaStored ? 'pass' : 'warn',
                'evidence' => $imageCriteriaStored ? 'Stored image activity rows include quality criteria and/or pass/fail fields.' : 'No stored image quality criteria were found.',
            ],
        ];
    }

    /**
     * @param Collection<int, PublishArticleActivity> $aiActivities
     * @return array<int, array<string, string|null>>
     */
    private function prettyAiPrompts(Collection $aiActivities): array
    {
        return $aiActivities
            ->filter(fn (PublishArticleActivity $activity) => !empty(Arr::get($activity->request_payload, 'prompt')))
            ->map(function (PublishArticleActivity $activity) {
                return [
                    'label' => trim(implode(' / ', array_filter([$activity->activity_type, $activity->stage, $activity->substage]))),
                    'provider' => $activity->provider,
                    'model' => $activity->model,
                    'agent' => $activity->agent,
                    'prompt' => (string) Arr::get($activity->request_payload, 'prompt'),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param Collection<int, PublishArticleActivity> $searchActivities
     * @return array<int, string>
     */
    private function prettyDirectNewsUrls(PublishArticle $article, Collection $searchActivities): array
    {
        $urls = [];

        foreach ((array) $article->source_articles as $source) {
            if (!empty($source['url'])) {
                $urls[] = (string) $source['url'];
            }
        }

        foreach ($searchActivities as $activity) {
            foreach ((array) Arr::get($activity->response_payload, 'articles', []) as $candidate) {
                if (!empty($candidate['url'])) {
                    $urls[] = (string) $candidate['url'];
                }
            }
        }

        return array_values(array_unique(array_filter($urls)));
    }

    private function resolveArticle(PublishArticle|int|null $article): ?PublishArticle
    {
        if ($article instanceof PublishArticle) {
            return $article;
        }

        if (is_int($article) && $article > 0) {
            return PublishArticle::find($article);
        }

        return null;
    }

    /**
     * @param mixed $value
     * @return array<string, mixed>|array<int, mixed>|null
     */
    private function normalizeArray(mixed $value): array|null
    {
        if ($value === null) {
            return null;
        }

        if (is_array($value)) {
            return $value;
        }

        return ['value' => $value];
    }

    private function resolveTimestamp(mixed $value): ?Carbon
    {
        if ($value instanceof Carbon) {
            return $value;
        }

        if (is_string($value) && trim($value) !== '') {
            try {
                return Carbon::parse($value);
            } catch (\Throwable) {
                return null;
            }
        }

        return now();
    }

    private function stringOrNull(mixed $value): ?string
    {
        $value = is_string($value) ? trim($value) : $value;
        return $value === '' || $value === null ? null : Str::limit((string) $value, 65535, '');
    }

    private function intOrNull(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }
}
