<?php

require "/home/scalemypublicati/public_html/publish.scalemypublication.com/vendor/autoload.php";
$app = require "/home/scalemypublicati/public_html/publish.scalemypublication.com/bootstrap/app.php";
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use hexa_core\Models\User;
use Illuminate\Http\Request;
use hexa_app_publish\Publishing\Articles\Models\PublishArticle;
use hexa_app_publish\Publishing\Articles\Http\Controllers\DraftController;
use hexa_app_publish\Publishing\Articles\Http\Controllers\ArticleController;
use hexa_app_publish\Publishing\Delivery\Services\WordPressDeletionService;
use hexa_app_publish\Publishing\Pipeline\Services\PipelineStateService;

$result = [];

try {
    $user = User::findOrFail(1);
    $source = PublishArticle::with('pipelineState')->findOrFail(436);

    $temp = PublishArticle::create([
        'user_id' => $source->user_id,
        'created_by' => $source->created_by,
        'publish_account_id' => $source->publish_account_id,
        'publish_site_id' => $source->publish_site_id,
        'article_id' => PublishArticle::generateArticleId(),
        'title' => $source->title . ' TEMP DRAFT TEST',
        'body' => $source->body,
        'excerpt' => $source->excerpt,
        'article_type' => $source->article_type,
        'status' => 'drafting',
        'delivery_mode' => 'draft-local',
        'categories' => $source->categories,
        'tags' => $source->tags,
        'word_count' => $source->word_count,
        'author' => $source->author,
        'photo_suggestions' => $source->photo_suggestions,
        'featured_image_search' => $source->featured_image_search,
    ]);

    $payload = is_array($source->pipelineState?->payload ?? null) ? $source->pipelineState->payload : [];
    foreach ([
        'existingWpPostId',
        'existingWpStatus',
        'existingWpPostUrl',
        'existingWpAdminUrl',
        'preparedFeaturedMediaId',
        'preparedFeaturedWpUrl',
        'featured_media_id',
        'uploadedImages',
        'category_ids',
        'tag_ids',
    ] as $key) {
        unset($payload[$key]);
    }

    app(PipelineStateService::class)->save($temp, $payload, $temp->article_type ?: null);

    $draftController = app(DraftController::class);
    $articleController = app(ArticleController::class);

    $prepareRequest = Request::create("/article/articles/{$temp->id}/prepare-wordpress", 'POST');
    $prepareRequest->setUserResolver(fn() => $user);
    $prepare = json_decode($draftController->prepareWordPress($prepareRequest, $temp->id)->getContent(), true);

    $publishRequest = Request::create("/publish/articles/{$temp->id}/publish", 'POST', ['status' => 'draft']);
    $publish = json_decode($articleController->publish($publishRequest, $temp->id)->getContent(), true);

    $temp->refresh();

    $deleteLog = app(WordPressDeletionService::class)->delete($temp);
    $pipelineState = $temp->pipelineState;
    if ($pipelineState) {
        $pipelineState->delete();
    }
    $temp->delete();

    $result = [
        'temp_id' => $temp->id,
        'prepare_success' => $prepare['success'] ?? null,
        'prepare_steps' => count($prepare['steps'] ?? []),
        'publish_success' => $publish['success'] ?? null,
        'publish_message' => $publish['message'] ?? null,
        'created_wp_post_id' => $publish['article']['wp_post_id'] ?? null,
        'created_wp_status' => $publish['article']['wp_status'] ?? null,
        'cleanup_success' => $deleteLog['success'] ?? null,
        'cleanup_log_count' => count($deleteLog['log'] ?? []),
    ];
} catch (Throwable $e) {
    $result = [
        'error' => get_class($e) . ': ' . $e->getMessage(),
        'trace' => $e->getTraceAsString(),
    ];
}

echo json_encode($result, JSON_PRETTY_PRINT), PHP_EOL;
