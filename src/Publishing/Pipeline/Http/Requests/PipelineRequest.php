<?php

namespace hexa_app_publish\Publishing\Pipeline\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

abstract class PipelineRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }
}
