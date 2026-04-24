{{-- Legacy row partial — now delegates to the shared article-card partial so articles/index and campaigns/show share one card. --}}
@foreach($articles as $article)
    @include('app-publish::publishing.articles.partials.article-card', ['article' => $article])
@endforeach
