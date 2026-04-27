<?php

namespace hexa_app_publish\Publishing\Pipeline\Http\Requests;

class SpinRequest extends PipelineRequest
{
    public function rules(): array
    {
        return [
            'draft_id' => 'nullable|integer|exists:publish_articles,id',
            'source_texts' => 'required|array|min:1',
            'source_texts.*' => 'required|string',
            'template_id' => 'nullable|integer|exists:publish_templates,id',
            'preset_id' => 'nullable|integer|exists:publish_presets,id',
            'prompt_slug' => 'nullable|string|max:255',
            'model' => 'required|string|max:100',
            'change_request' => 'nullable|string|max:2000',
            'custom_prompt' => 'nullable|string|max:5000',
            'supporting_url_type' => 'nullable|string|max:100',
            'master_setting_ids' => 'nullable|array',
            'master_setting_ids.*' => 'integer|exists:publish_master_settings,id',
            'pr_subject_context' => 'nullable|string|max:50000',
            'article_type' => 'nullable|string|max:100',
        ];
    }
}
