<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (!DB::getSchemaBuilder()->hasTable('publish_templates')) {
            return;
        }

        $templates = [
            [
                'name' => 'Hexa PR Wire - PR Full Feature',
                'article_type' => 'pr-full-feature',
                'description' => 'Subject-led editorial feature for PR clients using Notion profile context, related records, and real subject photos.',
                'ai_prompt' => 'Write a calm, polished, journalistic feature about the main subject. The piece should market the subject through credible reporting, narrative structure, and relevant background instead of overt promotion. Use the selected Notion subject dossier, relations, and images.',
                'headline_rules' => 'Feature-style headline that naturally includes the main subject name and the key angle. No clickbait.',
                'tone' => json_encode(['Journalistic', 'Editorial', 'Authoritative']),
                'word_count_min' => 900,
                'word_count_max' => 1400,
                'photos_per_article' => 3,
                'max_links' => 5,
                'h2_notation' => 'capital_case',
                'inline_photo_min' => 2,
                'inline_photo_max' => 3,
                'featured_image_required' => true,
                'featured_image_must_be_landscape' => false,
                'structure' => json_encode([
                    'strong_feature_lede',
                    'context_section',
                    'subject_background',
                    'current_work_or_relevance',
                    'quotes_and_analysis',
                    'forward-looking_close',
                ]),
                'rules' => json_encode([
                    'Do not sound promotional or sensational.',
                    'Use the subject dossier and selected records only where relevant.',
                    'Prefer subject imagery from Notion/Drive over stock.',
                    'Use 1-3 insightful quotes if direct quotes are not supplied.',
                ]),
            ],
            [
                'name' => 'Hexa PR Wire - Expert Article',
                'article_type' => 'expert-article',
                'description' => 'News-topic-first expert article that weaves a PR client into the analysis with credible insight and quotes.',
                'ai_prompt' => 'Write a serious expert article focused on the requested topic or imported news context. The PR client should appear as a knowledgeable authority supporting the thesis with analysis, perspective, and quotes, without overt promotion.',
                'headline_rules' => 'Headline should clearly signal the issue or topic first. Include the expert only when it improves clarity and authority.',
                'tone' => json_encode(['Journalistic', 'Analytical', 'Expert']),
                'word_count_min' => 900,
                'word_count_max' => 1500,
                'photos_per_article' => 3,
                'max_links' => 6,
                'h2_notation' => 'capital_case',
                'inline_photo_min' => 2,
                'inline_photo_max' => 3,
                'featured_image_required' => false,
                'featured_image_must_be_landscape' => false,
                'structure' => json_encode([
                    'topic_framing',
                    'core_thesis',
                    'expert_analysis',
                    'quoted_positioning',
                    'practical_implications',
                    'close',
                ]),
                'rules' => json_encode([
                    'Keep the article topic-first.',
                    'Do not turn the article into a disguised advertisement.',
                    'Use 3-5 expert quotes if direct quotes are not supplied.',
                    'Respect the chosen photo mode for the subject.',
                ]),
            ],
        ];

        foreach ($templates as $template) {
            $payload = [
                'user_id' => 1,
                'publish_account_id' => 1,
                'name' => $template['name'],
                'status' => 'active',
                'is_default' => false,
                'article_type' => $template['article_type'],
                'description' => $template['description'],
                'ai_prompt' => $template['ai_prompt'],
                'headline_rules' => $template['headline_rules'],
                'search_online_for_additional_context' => false,
                'ai_engine' => null,
                'tone' => $template['tone'],
                'word_count_min' => $template['word_count_min'],
                'word_count_max' => $template['word_count_max'],
                'photos_per_article' => $template['photos_per_article'],
                'photo_sources' => null,
                'max_links' => $template['max_links'],
                'h2_notation' => $template['h2_notation'],
                'inline_photo_min' => $template['inline_photo_min'],
                'inline_photo_max' => $template['inline_photo_max'],
                'featured_image_required' => $template['featured_image_required'],
                'featured_image_must_be_landscape' => $template['featured_image_must_be_landscape'],
                'structure' => $template['structure'],
                'rules' => $template['rules'],
                'searching_agent' => null,
                'online_search_model_fallback' => null,
                'scraping_agent' => null,
                'scrape_ai_model_fallback' => null,
                'spinning_agent' => null,
                'spin_model_fallback' => null,
                'updated_at' => now(),
            ];

            $existingId = DB::table('publish_templates')
                ->where('publish_account_id', 1)
                ->where('name', $template['name'])
                ->value('id');

            if ($existingId) {
                DB::table('publish_templates')->where('id', $existingId)->update($payload);
            } else {
                DB::table('publish_templates')->insert($payload + ['created_at' => now()]);
            }
        }
    }

    public function down(): void
    {
        if (!DB::getSchemaBuilder()->hasTable('publish_templates')) {
            return;
        }

        DB::table('publish_templates')
            ->where('publish_account_id', 1)
            ->whereIn('name', [
                'Hexa PR Wire - PR Full Feature',
                'Hexa PR Wire - Expert Article',
            ])
            ->delete();
    }
};
