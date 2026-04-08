<?php

namespace hexa_app_publish\Publishing\Articles\Http\Requests;

class ScrapeUrlRequest extends ArticleRequest
{
    public function rules(): array
    {
        return [
            'url' => 'required|url|max:2048',
        ];
    }
}
