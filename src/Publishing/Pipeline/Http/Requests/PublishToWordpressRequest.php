<?php

namespace hexa_app_publish\Publishing\Pipeline\Http\Requests;

class PublishToWordpressRequest extends PipelineRequest
{
    public function rules(): array
    {
        return [
            'html' => 'required|string',
            'title' => 'required|string|max:500',
            'site_id' => 'required|integer|exists:publish_sites,id',
            'category_ids' => 'nullable|array',
            'tag_ids' => 'nullable|array',
            'status' => 'required|in:publish,draft,future',
            'date' => 'nullable|date',
            'pipeline_session_id' => 'nullable|string|max:100',
            'categories' => 'nullable|array',
            'tags' => 'nullable|array',
            'wp_images' => 'nullable|array',
            'word_count' => 'nullable|integer',
            'ai_model' => 'nullable|string|max:100',
            'ai_cost' => 'nullable|numeric',
            'ai_provider' => 'nullable|string|max:50',
            'ai_tokens_input' => 'nullable|integer',
            'ai_tokens_output' => 'nullable|integer',
            'resolved_prompt' => 'nullable|string',
            'photo_suggestions' => 'nullable|array',
            'featured_image_search' => 'nullable|string|max:500',
            'author' => 'nullable|string|max:255',
            'sources' => 'nullable|array',
            'template_id' => 'nullable|integer',
            'preset_id' => 'nullable|integer',
            'user_id' => 'nullable|integer',
            'draft_id' => 'nullable|integer|exists:publish_articles,id',
        ];
    }
}
