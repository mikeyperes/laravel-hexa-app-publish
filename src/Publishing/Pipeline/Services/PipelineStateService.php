<?php

namespace hexa_app_publish\Publishing\Pipeline\Services;

use hexa_app_publish\Publishing\Articles\Models\PublishArticle;
use hexa_app_publish\Publishing\Pipeline\Models\PublishPipelineState;

class PipelineStateService
{
    public function __construct(
        private PressReleaseWorkflowService $pressReleaseWorkflow
    ) {}

    public function load(PublishArticle $article): ?PublishPipelineState
    {
        if ($article->relationLoaded('pipelineState')) {
            return $article->getRelation('pipelineState');
        }

        return $article->pipelineState()->first();
    }

    public function payload(PublishArticle $article): array
    {
        $state = $this->load($article);

        return $this->normalizePayload($state?->payload ?? []);
    }

    public function save(PublishArticle $article, array $payload, ?string $workflowType = null): PublishPipelineState
    {
        $normalized = $this->normalizePayload($payload);

        return PublishPipelineState::updateOrCreate(
            ['publish_article_id' => $article->id],
            [
                'workflow_type' => $workflowType ?: $this->detectWorkflowType($normalized),
                'state_version' => (int) ($normalized['_v'] ?? 1),
                'payload' => $normalized,
            ]
        );
    }

    public function updatePressRelease(PublishArticle $article, array $pressRelease): PublishPipelineState
    {
        $payload = $this->payload($article);
        $payload['pressRelease'] = $this->pressReleaseWorkflow->normalizeState($pressRelease);

        return $this->save($article, $payload, 'press-release');
    }

    private function normalizePayload(array $payload): array
    {
        $legacyPressRelease = [
            'details' => [
                'date' => (string) ($payload['pressReleaseDate'] ?? ''),
                'location' => (string) ($payload['pressReleaseLocation'] ?? ''),
                'contact' => (string) ($payload['pressReleaseContact'] ?? ''),
                'contact_url' => (string) ($payload['pressReleaseContactUrl'] ?? ''),
            ],
            'content_dump' => (string) ($payload['pressReleaseContent'] ?? ''),
        ];

        $payload['_v'] = (int) ($payload['_v'] ?? 1);
        $payload['pressRelease'] = $this->pressReleaseWorkflow->normalizeState(array_replace_recursive(
            $legacyPressRelease,
            (array) ($payload['pressRelease'] ?? [])
        ));

        return $payload;
    }

    private function detectWorkflowType(array $payload): ?string
    {
        $articleType = data_get($payload, 'template_overrides.article_type')
            ?? data_get($payload, 'selectedTemplate.article_type')
            ?? data_get($payload, 'pressRelease.article_type');

        return $articleType === 'press-release' ? 'press-release' : $articleType;
    }
}
