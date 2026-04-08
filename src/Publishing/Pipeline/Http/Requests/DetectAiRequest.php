<?php

namespace hexa_app_publish\Publishing\Pipeline\Http\Requests;

class DetectAiRequest extends PipelineRequest
{
    public function rules(): array
    {
        return [
            'text' => 'required|string|min:10',
            'article_id' => 'nullable|integer',
        ];
    }
}
