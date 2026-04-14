<?php

namespace hexa_app_publish\Publishing\Pipeline\Http\Requests;

class PressReleaseDetectPhotosRequest extends PipelineRequest
{
    public function rules(): array
    {
        return [
            'draft_id' => 'required|integer|exists:publish_articles,id',
        ];
    }
}
