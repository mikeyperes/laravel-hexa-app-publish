<?php

namespace hexa_app_publish\Publishing\Pipeline\Http\Requests;

class SaveDraftRequest extends PipelineRequest
{
    public function rules(): array
    {
        return [
            'draft_id' => 'nullable|integer|exists:publish_articles,id',
            'title' => 'nullable|string|max:500',
            'body' => 'nullable|string',
            'editor_ready' => 'nullable|boolean',
            'excerpt' => 'nullable|string|max:1000',
            'user_id' => 'nullable|integer|exists:users,id',
            'site_id' => 'nullable|integer|exists:publish_sites,id',
            'preset_id' => 'nullable|integer',
            'prompt_id' => 'nullable|integer',
            'article_type' => 'nullable|string|max:100',
            'ai_model' => 'nullable|string|max:100',
            'author' => 'nullable|string|max:255',
            'sources' => 'nullable|array',
            'tags' => 'nullable|array',
            'categories' => 'nullable|array',
            'notes' => 'nullable|string',
            'template_id' => 'nullable|integer',
            'photo_suggestions' => 'nullable|array',
            'featured_image_search' => 'nullable|string|max:500',
        ];
    }
}
