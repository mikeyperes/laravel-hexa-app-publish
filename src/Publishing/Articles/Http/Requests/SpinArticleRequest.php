<?php

namespace hexa_app_publish\Publishing\Articles\Http\Requests;

class SpinArticleRequest extends ArticleRequest
{
    public function rules(): array
    {
        return [
            'ai_engine' => 'required|in:anthropic,chatgpt,grok,gemini',
            'instruction' => 'nullable|string|max:2000',
        ];
    }
}
