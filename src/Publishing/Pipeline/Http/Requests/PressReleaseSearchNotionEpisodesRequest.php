<?php

namespace hexa_app_publish\Publishing\Pipeline\Http\Requests;

class PressReleaseSearchNotionEpisodesRequest extends PipelineRequest
{
    public function rules(): array
    {
        return [
            'draft_id' => 'required|integer|exists:publish_articles,id',
            'query' => 'nullable|string|max:255',
            'limit' => 'nullable|integer|min:1|max:15',
        ];
    }
}
