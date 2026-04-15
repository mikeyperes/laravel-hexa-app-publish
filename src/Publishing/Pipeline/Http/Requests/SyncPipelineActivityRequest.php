<?php

namespace hexa_app_publish\Publishing\Pipeline\Http\Requests;

class SyncPipelineActivityRequest extends PipelineRequest
{
    public function rules(): array
    {
        return [
            'draft_id' => 'required|integer|exists:publish_articles,id',
            'client_trace' => 'required|string|max:160',
            'workflow_type' => 'nullable|string|max:80',
            'debug_enabled' => 'nullable|boolean',
            'entries' => 'required|array|min:1|max:400',
            'entries.*.id' => 'nullable|integer',
            'entries.*.client_event_id' => 'required|string|max:190',
            'entries.*.run_trace' => 'nullable|string|max:160',
            'entries.*.captured_at' => 'nullable|date',
            'entries.*.scope' => 'nullable|string|max:40',
            'entries.*.type' => 'nullable|string|max:40',
            'entries.*.message' => 'nullable|string|max:65535',
            'entries.*.stage' => 'nullable|string|max:80',
            'entries.*.substage' => 'nullable|string|max:80',
            'entries.*.trace_id' => 'nullable|string|max:160',
            'entries.*.duration_ms' => 'nullable|integer|min:0',
            'entries.*.sequence_no' => 'nullable|integer|min:0',
            'entries.*.method' => 'nullable|string|max:20',
            'entries.*.status' => 'nullable|integer|min:0|max:999',
            'entries.*.url' => 'nullable|string|max:4000',
            'entries.*.details' => 'nullable|string|max:65535',
            'entries.*.payload_preview' => 'nullable|string|max:65535',
            'entries.*.response_preview' => 'nullable|string|max:65535',
            'entries.*.debug_only' => 'nullable|boolean',
            'entries.*.step' => 'nullable|integer|min:0|max:999',
            'entries.*.meta' => 'nullable|array',
        ];
    }
}
