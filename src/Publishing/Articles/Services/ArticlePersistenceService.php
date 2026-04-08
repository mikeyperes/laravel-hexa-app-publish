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
                $article->update($data);
                return $article;
            }
        }

        $data['article_id'] = PublishArticle::generateArticleId();
        return PublishArticle::create($data);
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
        return PublishArticle::create($data);
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
        $article->update([
            'wp_post_id'   => $deliveryResult['post_id'] ?? null,
            'wp_post_url'  => $deliveryResult['post_url'] ?? null,
            'wp_status'    => $wpStatus,
            'status'       => $isPublished ? 'completed' : 'drafting',
            'published_at' => $isPublished ? now() : null,
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
        $article->update(['status' => 'failed']);
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
        $article->update(['status' => 'drafting', 'delivery_mode' => 'draft-local']);
        return $article;
    }
}
