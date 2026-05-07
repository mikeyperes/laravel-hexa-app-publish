<?php

namespace hexa_app_publish\Publishing\Pipeline\Http\Requests;

class PrepareForWordpressRequest extends PipelineRequest
{
    public function rules(): array
    {
        return [
            'html' => 'required|string',
            'title' => 'nullable|string|max:500',
            'site_id' => 'required|integer|exists:publish_sites,id',
            'categories' => 'nullable|array',
            'publication_term_ids' => 'nullable|array',
            'tags' => 'nullable|array',
            'pipeline_session_id' => 'nullable|string|max:100',
            'draft_id' => 'required|integer|exists:publish_articles,id',
            'photo_suggestions' => 'nullable|array',
            'photo_meta' => 'nullable|array',
            'photo_meta.*.alt_text' => 'nullable|string',
            'photo_meta.*.caption' => 'nullable|string',
            'photo_meta.*.filename' => 'nullable|string',
            'featured_meta' => 'nullable|array',
            'featured_meta.alt_text' => 'nullable|string',
            'featured_meta.caption' => 'nullable|string',
            'featured_meta.filename' => 'nullable|string',
            'featured_url' => 'nullable|string',
            'existing_uploads' => 'nullable|array',
            'existing_featured_media_id' => 'nullable|integer',
        ];
    }
}
