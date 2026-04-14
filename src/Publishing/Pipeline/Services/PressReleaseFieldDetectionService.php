<?php

namespace hexa_app_publish\Publishing\Pipeline\Services;

use hexa_package_anthropic\Services\AnthropicService;

class PressReleaseFieldDetectionService
{
    public function __construct(
        private AnthropicService $anthropic
    ) {}

    public function detect(array $state, string $sourceText, string $model = 'claude-sonnet-4-20250514'): array
    {
        $log = [];
        $log[] = $this->entry('info', 'Starting AI field detection.', [
            'model' => $model,
            'source_length' => strlen($sourceText),
        ]);

        $existing = $state['details'] ?? [];

        $systemPrompt = "You are a press release intake assistant. "
            . "Read the submitted press release content and extract the most likely field values. "
            . "Return ONLY valid JSON with keys: date, location, contact, contact_url. "
            . "If a field is not clearly supported, return an empty string for that field. "
            . "Do not invent values.";

        $userPrompt = "Existing field values:\n"
            . json_encode($existing, JSON_PRETTY_PRINT) . "\n\n"
            . "Press release source material:\n\n"
            . mb_substr($sourceText, 0, 30000);

        $result = $this->anthropic->chat($systemPrompt, $userPrompt, $model, 1200);
        if (!$result['success']) {
            $log[] = $this->entry('error', 'AI field detection failed: ' . ($result['message'] ?? 'Unknown error'));

            return [
                'success' => false,
                'fields' => $existing,
                'log' => $log,
                'message' => $result['message'] ?? 'AI detection failed.',
            ];
        }

        $raw = trim((string) ($result['data']['content'] ?? ''));
        $raw = preg_replace('/^```json\s*/i', '', $raw);
        $raw = preg_replace('/\s*```$/', '', $raw);

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            $log[] = $this->entry('error', 'AI response was not valid JSON.', [
                'raw' => mb_substr($raw, 0, 1000),
            ]);

            return [
                'success' => false,
                'fields' => $existing,
                'log' => $log,
                'message' => 'Failed to parse AI detection response.',
            ];
        }

        $fields = [
            'date' => trim((string) ($decoded['date'] ?? '')),
            'location' => trim((string) ($decoded['location'] ?? '')),
            'contact' => trim((string) ($decoded['contact'] ?? '')),
            'contact_url' => trim((string) ($decoded['contact_url'] ?? '')),
        ];

        foreach ($fields as $key => $value) {
            $log[] = $this->entry($value !== '' ? 'success' : 'warning', 'Detected field: ' . $key, [
                'value' => $value,
            ]);
        }

        return [
            'success' => true,
            'fields' => $fields,
            'log' => $log,
            'message' => 'Custom fields detected and updated.',
            'usage' => $result['data']['usage'] ?? [],
            'model' => $result['data']['model'] ?? $model,
        ];
    }

    private function entry(string $type, string $message, array $context = []): array
    {
        return [
            'type' => $type,
            'message' => $message,
            'timestamp' => now()->toIso8601String(),
            'context' => $context,
        ];
    }
}
