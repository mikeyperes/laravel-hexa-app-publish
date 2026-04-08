<?php

namespace hexa_app_publish\Publishing\Pipeline\Http\Requests;

class GeneratePhotoMetaRequest extends PipelineRequest
{
    public function rules(): array
    {
        return [
            'search_term' => 'required|string|max:200',
            'article_title' => 'nullable|string|max:500',
            'article_text' => 'nullable|string|max:2000',
        ];
    }
}
