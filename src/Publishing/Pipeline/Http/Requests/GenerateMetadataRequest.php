<?php

namespace hexa_app_publish\Publishing\Pipeline\Http\Requests;

class GenerateMetadataRequest extends PipelineRequest
{
    public function rules(): array
    {
        return [
            'article_html' => 'required|string',
        ];
    }
}
