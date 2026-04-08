<?php

namespace hexa_app_publish\Publishing\Articles\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

abstract class ArticleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }
}
