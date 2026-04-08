<?php

namespace hexa_app_publish\Publishing\Articles\Http\Requests;

class InsertLinksRequest extends ArticleRequest
{
    public function rules(): array
    {
        return [
            'max_links' => 'nullable|integer|min:1|max:20',
            'link_ids' => 'nullable|array',
        ];
    }
}
