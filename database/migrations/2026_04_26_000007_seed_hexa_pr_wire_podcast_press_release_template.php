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

        $payload = [
            'user_id' => 1,
            'publish_account_id' => 1,
            'name' => 'Hexa PR Wire - Podcast Press Release',
            'status' => 'active',
            'is_default' => false,
            'article_type' => 'press-release',
            'description' => 'Hexa PR Wire podcast press release preset for Michael Peres Podcast episodes imported from Notion.',
            'ai_prompt' => 'Write a formal Hexa PR Wire podcast press release using only the imported episode and related guest facts. Use a dateline lead, embed YouTube when available, include About the guest, About Michael Peres, About The Michael Peres Podcast, and Contact Information. Do not invent facts or external links.',
            'headline_rules' => 'Headline should follow the Hexa PR Wire podcast style: guest name plus the discussion topic plus The Michael Peres Podcast.',
            'search_online_for_additional_context' => false,
            'ai_engine' => null,
            'tone' => json_encode(['Professional', 'Press Release', 'Journalistic']),
            'word_count_min' => 650,
            'word_count_max' => 950,
            'photos_per_article' => 0,
            'photo_sources' => null,
            'max_links' => 4,
            'h2_notation' => 'capital_case',
            'inline_photo_min' => 0,
            'inline_photo_max' => 0,
            'featured_image_required' => true,
            'featured_image_must_be_landscape' => true,
            'structure' => json_encode([
                'opening_dateline',
                'episode_summary',
                'youtube_embed_when_available',
                'about_guest',
                'about_michael_peres',
                'about_the_michael_peres_podcast',
                'contact_information',
            ]),
            'rules' => json_encode([
                'Only use episode and guest facts explicitly present in the imported source package.',
                'Do not invent quotes or supporting links.',
                'Use formal Hexa PR Wire press release structure.',
                'Prefer imported episode and guest images over generic stock.',
            ]),
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
            ->where('name', 'Hexa PR Wire - Podcast Press Release')
            ->value('id');

        if ($existingId) {
            DB::table('publish_templates')->where('id', $existingId)->update($payload);
            return;
        }

        DB::table('publish_templates')->insert($payload + ['created_at' => now()]);
    }

    public function down(): void
    {
        if (!DB::getSchemaBuilder()->hasTable('publish_templates')) {
            return;
        }

        DB::table('publish_templates')
            ->where('publish_account_id', 1)
            ->where('name', 'Hexa PR Wire - Podcast Press Release')
            ->delete();
    }
};
