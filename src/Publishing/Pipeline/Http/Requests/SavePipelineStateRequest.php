<?php

namespace hexa_app_publish\Publishing\Pipeline\Http\Requests;

class SavePipelineStateRequest extends PipelineRequest
{
    public function rules(): array
    {
        return [
            'draft_id' => 'required|integer|exists:publish_articles,id',
            'workflow_type' => 'nullable|string|max:80',
            'payload' => 'required|array',
        ];
    }
}
