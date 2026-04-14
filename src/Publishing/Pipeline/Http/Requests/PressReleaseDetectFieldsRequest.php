<?php

namespace hexa_app_publish\Publishing\Pipeline\Http\Requests;

class PressReleaseDetectFieldsRequest extends PipelineRequest
{
    public function rules(): array
    {
        return [
            'draft_id' => 'required|integer|exists:publish_articles,id',
            'model' => 'nullable|string|max:100',
        ];
    }
}
