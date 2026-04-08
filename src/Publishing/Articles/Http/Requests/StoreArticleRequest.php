<?php

namespace hexa_app_publish\Publishing\Articles\Http\Requests;

class StoreArticleRequest extends ArticleRequest
{
    public function rules(): array
    {
        return [
            'publish_account_id' => 'required|exists:publish_accounts,id',
            'publish_site_id' => 'required|exists:publish_sites,id',
            'publish_template_id' => 'nullable|exists:publish_templates,id',
            'title' => 'required|string|max:500',
            'body' => 'nullable|string',
            'excerpt' => 'nullable|string|max:1000',
            'article_type' => 'nullable|string|max:50',
            'delivery_mode' => 'nullable|in:draft-local,draft-wordpress,auto-publish,review,notify',
            'notes' => 'nullable|string',
        ];
    }
}
