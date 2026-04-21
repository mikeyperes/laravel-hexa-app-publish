<?php

namespace hexa_app_publish\Publishing\Pipeline\Http\Requests;

class GenerateMetadataRequest extends PipelineRequest
{
    public function rules(): array
    {
        return [
            'draft_id' => 'nullable|integer|exists:publish_articles,id',
            'article_html' => 'required|string',
        ];
    }
}
