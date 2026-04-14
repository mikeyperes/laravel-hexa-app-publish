<?php

namespace hexa_app_publish\Publishing\Pipeline\Http\Requests;

class PreviewPromptRequest extends PipelineRequest
{
    public function rules(): array
    {
        return [
            'source_texts' => 'nullable|array',
            'source_texts.*' => 'nullable|string',
            'template_id' => 'nullable|integer',
            'preset_id' => 'nullable|integer',
            'prompt_slug' => 'nullable|string|max:255',
            'custom_prompt' => 'nullable|string|max:5000',
        ];
    }
}
