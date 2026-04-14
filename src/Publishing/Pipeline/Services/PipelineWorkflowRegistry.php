<?php

namespace hexa_app_publish\Publishing\Pipeline\Services;

class PipelineWorkflowRegistry
{
    public function definitions(): array
    {
        return [
            'default' => [
                'step_labels' => [
                    'User',
                    'Article Configuration',
                    'Find Articles',
                    'Fetch Articles from Source',
                    'AI & Spin',
                    'Create Article',
                    'Review & Publish',
                ],
            ],
            'generate' => [
                'step_labels' => [
                    'User',
                    'Article Configuration',
                    'Generate Content',
                    'Fetch Articles from Source',
                    'AI & Spin',
                    'Create Article',
                    'Review & Publish',
                ],
            ],
            'press-release' => [
                'step_labels' => [
                    'User',
                    'Article Configuration',
                    'Submit Content',
                    'Submit Content',
                    'AI & Spin',
                    'Create Article',
                    'Review & Publish',
                ],
                'step_labels_polish' => [
                    'User',
                    'Article Configuration',
                    'Submit Content',
                    'Submit Content',
                    'Polish',
                    'Create Article',
                    'Review & Publish',
                ],
            ],
        ];
    }
}
