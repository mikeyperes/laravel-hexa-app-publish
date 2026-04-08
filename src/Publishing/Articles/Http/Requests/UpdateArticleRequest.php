<?php

namespace hexa_app_publish\Publishing\Articles\Http\Requests;

class UpdateArticleRequest extends ArticleRequest
{
    public function rules(): array
    {
        return [
            'title' => 'nullable|string|max:500',
            'body' => 'nullable|string',
            'excerpt' => 'nullable|string|max:1000',
            'article_type' => 'nullable|string|max:50',
            'status' => 'nullable|in:' . implode(',', config('hws-publish.article_statuses', [])),
            'delivery_mode' => 'nullable|in:draft-local,draft-wordpress,auto-publish,review,notify',
            'photos' => 'nullable|array',
            'links_injected' => 'nullable|array',
            'notes' => 'nullable|string',
        ];
    }
}
