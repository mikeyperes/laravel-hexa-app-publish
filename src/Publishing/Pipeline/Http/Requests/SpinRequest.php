<?php

namespace hexa_app_publish\Publishing\Pipeline\Http\Requests;

class SpinRequest extends PipelineRequest
{
    public function rules(): array
    {
        return [
            'source_texts' => 'required|array|min:1',
            'source_texts.*' => 'required|string',
            'template_id' => 'nullable|integer|exists:publish_templates,id',
            'preset_id' => 'nullable|integer|exists:publish_presets,id',
            'model' => 'required|string|max:100',
            'change_request' => 'nullable|string|max:2000',
            'custom_prompt' => 'nullable|string|max:5000',
            'master_setting_ids' => 'nullable|array',
            'master_setting_ids.*' => 'integer|exists:publish_master_settings,id',
        ];
    }
}
