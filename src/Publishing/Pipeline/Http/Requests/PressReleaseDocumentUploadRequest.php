<?php

namespace hexa_app_publish\Publishing\Pipeline\Http\Requests;

class PressReleaseDocumentUploadRequest extends PipelineRequest
{
    public function rules(): array
    {
        return [
            'draft_id' => 'required|integer|exists:publish_articles,id',
            'documents' => 'required|array|max:10',
            'documents.*' => 'file|mimes:doc,docx,pdf|max:20480',
        ];
    }
}
