<?php

namespace hexa_app_publish\Publishing\Campaigns\Services;

class CampaignChecklistService
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function definitions(string $deliveryMode = 'draft-wordpress'): array
    {
        $wpMode = in_array($deliveryMode, ['draft-wordpress', 'auto-publish'], true);
        $publishMode = $deliveryMode === 'auto-publish';

        return [
            $this->item('settings', 'Resolve Settings', 'Load the campaign preset, article preset, AI model, delivery mode, and content defaults.', ['settings']),
            $this->item('site', 'Validate Site', 'Confirm the target site, author, connection type, and publish path are usable.', ['site']),
            $this->item('discovery', 'Discover Sources', 'Build the search context and find current candidate source URLs.', ['discovery']),
            $this->item('extraction', 'Extract Sources', 'Fetch source bodies and reject empty or failed extractions.', ['extraction']),
            $this->item('article', 'Create Draft Record', 'Create and keep the campaign article record alive through the whole run.', ['article']),
            $this->item('generation', 'Generate Article', 'Spin the article HTML, title, excerpt, categories, tags, and SEO metadata.', ['generation']),
            $this->item('photos', 'Auto Select Photos', 'Pick a featured image plus inline images, filter weak candidates, and build safe metadata.', ['photos', 'html_media']),
            $this->item(
                'wp_connection',
                'Connect To WordPress',
                'Resolve the WordPress transport and verify the target author/account.',
                ['connection'],
                $wpMode
            ),
            $this->item(
                'wp_html',
                'Prepare HTML',
                'Sanitize article HTML and get it ready for WordPress.',
                ['html'],
                $wpMode
            ),
            $this->item(
                'wp_media',
                'Upload Media',
                'Upload inline and featured images to WordPress and replace source URLs.',
                ['media'],
                $wpMode
            ),
            $this->item(
                'wp_taxonomies',
                'Sync Categories And Tags',
                'Create or resolve WordPress categories and tags for the article.',
                ['taxonomy', 'category', 'tag'],
                $wpMode
            ),
            $this->item(
                'wp_integrity',
                'Run Integrity Checks',
                'Remove broken placeholders, fix HTML issues, and confirm the prepared content is publish-safe.',
                ['integrity'],
                $wpMode
            ),
            $this->item(
                'delivery',
                $publishMode ? 'Publish To WordPress' : 'Create WordPress Draft',
                $publishMode
                    ? 'Create the live WordPress post and attach the prepared assets.'
                    : 'Create the in-domain WordPress draft and attach the prepared assets.',
                ['delivery'],
                $wpMode
            ),
            $this->item(
                'local_draft',
                'Save Local Draft',
                'Save the article locally when the campaign is not targeting WordPress.',
                ['delivery'],
                !$wpMode
            ),
            $this->item('persistence', 'Persist Results', 'Store the final article state, WordPress result, and campaign log trail.', ['persistence']),
            $this->item('schedule', 'Refresh Campaign Schedule', 'Update the campaign next-run timestamp after the run completes.', ['schedule']),
        ];
    }

    /**
     * @param array<int, string> $stages
     * @return array<string, mixed>
     */
    private function item(string $key, string $label, string $detail, array $stages, bool $enabled = true): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'detail' => $detail,
            'stages' => $stages,
            'status' => $enabled ? 'pending' : 'skipped',
            'enabled' => $enabled,
        ];
    }
}
