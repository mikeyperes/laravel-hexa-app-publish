<?php

namespace hexa_app_publish\Publishing\Pipeline\Http\Requests;

class CheckSourcesRequest extends PipelineRequest
{
    public function rules(): array
    {
        return [
            'urls' => 'required|array|min:1',
            'urls.*' => 'required|url|max:2048',
            'user_agent' => 'nullable|string|max:100',
            'method' => 'nullable|in:auto,readability,css,regex,claude,gpt',
            'retries' => 'nullable|integer|min:0|max:5',
            'timeout' => 'nullable|integer|min:5|max:60',
            'min_words' => 'nullable|integer|min:10|max:1000',
            'auto_fallback' => 'nullable|boolean',
        ];
    }
}
