<?php

namespace hexa_app_publish\Publishing\Articles\Http\Requests;

class SearchDiscoveryRequest extends ArticleRequest
{
    public function rules(): array
    {
        return [
            'query' => 'required|string|max:255',
            'sources' => 'nullable|array',
            'per_page' => 'nullable|integer|min:1|max:50',
        ];
    }
}
