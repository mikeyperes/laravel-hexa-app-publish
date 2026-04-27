<?php

namespace hexa_app_publish\Publishing\Pipeline\Http\Requests;

class PressReleaseImportNotionEpisodeRequest extends PipelineRequest
{
    public function rules(): array
    {
        return [
            'draft_id' => 'required|integer|exists:publish_articles,id',
            'page_id' => 'required|string|max:255',
        ];
    }
}
