<?php

namespace hexa_app_publish\Publishing\Articles\Services;

use hexa_app_publish\Publishing\Articles\Models\PublishArticle;

/**
 * ArticlePersistenceService — single source of truth for article create/update.
 *
 * Handles: generate article_id, create-or-update by draft_id, upsert pattern.
 * Replaces duplicated persistence logic from PipelineController::publishToWordpress()
 * and CampaignRunService::run().
 */
class ArticlePersistenceService
{
    public function __construct(
        protected ArticleActivityService $activities,
    ) {
    }

    /**
     * Create a new article or update an existing draft.
     *
     * @param array $data Article data (all columns)
     * @param int|null $draftId Existing draft ID to update (null = create new)
     * @return PublishArticle
     */
    public function createOrUpdate(array $data, ?int $draftId = null): PublishArticle
    {
        if ($draftId) {
            $article = PublishArticle::find($draftId);
            if ($article) {
                $beforeStatus = $article->status;
                $article->update($data);
                $this->recordLifecycle($article, 'updated', 'Article record updated.', [
                    'before_status' => $beforeStatus,
                    'after_status' => $article->status,
                    'changes' => array_keys($data),
                ]);
                return $article;
            }
        }

        $data['article_id'] = PublishArticle::generateArticleId();
        $article = PublishArticle::create($data);
        $this->recordLifecycle($article, 'created', 'Article record created.', [
            'status' => $article->status,
            'delivery_mode' => $article->delivery_mode,
            'article_type' => $article->article_type,
        ]);

        return $article;
    }

    /**
     * Create a fresh article (campaign use — always new, never update draft).
     *
     * @param array $data
     * @return PublishArticle
     */
    public function create(array $data): PublishArticle
    {
        $data['article_id'] = $data['article_id'] ?? PublishArticle::generateArticleId();
        $article = PublishArticle::create($data);
        $this->recordLifecycle($article, 'created', 'Article record created.', [
            'status' => $article->status,
            'delivery_mode' => $article->delivery_mode,
            'article_type' => $article->article_type,
        ]);

        return $article;
    }

    /**
     * Update an existing article's WordPress delivery result.
     *
     * @param PublishArticle $article
     * @param array $deliveryResult {post_id, post_url, mode}
     * @param string $wpStatus publish|draft|future
     * @return PublishArticle
     */
    public function updateDeliveryResult(PublishArticle $article, array $deliveryResult, string $wpStatus): PublishArticle
    {
        $isPublished = ($wpStatus === 'publish');
        $beforeStatus = $article->status;
        $article->update([
            'wp_post_id'   => $deliveryResult['post_id'] ?? null,
            'wp_post_url'  => $deliveryResult['post_url'] ?? null,
            'wp_status'    => $wpStatus,
            'status'       => $isPublished ? 'completed' : 'drafting',
            'published_at' => $isPublished ? now() : null,
        ]);

        $this->recordLifecycle($article, 'delivery_updated', $isPublished ? 'Article published to WordPress.' : 'Article stored as WordPress draft.', [
            'before_status' => $beforeStatus,
            'after_status' => $article->status,
            'wp_status' => $wpStatus,
            'wp_post_id' => $article->wp_post_id,
            'wp_post_url' => $article->wp_post_url,
            'delivery_result' => $deliveryResult,
        ]);

        return $article;
    }

    /**
     * Mark article as failed.
     *
     * @param PublishArticle $article
     * @return PublishArticle
     */
    public function markFailed(PublishArticle $article): PublishArticle
    {
        $beforeStatus = $article->status;
        $article->update(['status' => 'failed']);
        $this->recordLifecycle($article, 'failed', 'Article marked as failed.', [
            'before_status' => $beforeStatus,
            'after_status' => $article->status,
        ]);
        return $article;
    }

    /**
     * Mark article as local draft.
     *
     * @param PublishArticle $article
     * @return PublishArticle
     */
    public function markLocalDraft(PublishArticle $article): PublishArticle
    {
        $beforeStatus = $article->status;
        $article->update(['status' => 'drafting', 'delivery_mode' => 'draft-local']);
        $this->recordLifecycle($article, 'local_draft', 'Article saved as local draft.', [
            'before_status' => $beforeStatus,
            'after_status' => $article->status,
            'delivery_mode' => $article->delivery_mode,
        ]);
        return $article;
    }

    /**
     * @param array<string, mixed> $meta
     */
    private function recordLifecycle(PublishArticle $article, string $substage, string $message, array $meta = []): void
    {
        $this->activities->record($article, [
            'activity_group' => 'lifecycle:' . $article->article_id,
            'activity_type' => 'lifecycle',
            'stage' => 'article',
            'substage' => $substage,
            'status' => $article->status,
            'success' => !in_array($substage, ['failed'], true),
            'title' => $article->title,
            'url' => $article->wp_post_url,
            'message' => $message,
            'meta' => $meta,
        ]);
    }
}
