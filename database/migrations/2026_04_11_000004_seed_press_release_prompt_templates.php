<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (!DB::getSchemaBuilder()->hasTable('prompt_categories') || !DB::getSchemaBuilder()->hasTable('prompt_templates')) {
            return;
        }

        $categoryId = DB::table('prompt_categories')->where('slug', 'press-release')->value('id');
        if (!$categoryId) {
            return;
        }

        $templates = [
            [
                'slug' => 'press-release-spin',
                'name' => 'Press Release Spin',
                'body' => <<<'PROMPT'
You are a professional press release writer.

Your job is to turn the submitted press release source material into a clean, publication-ready press release article.

{custom_instructions}

{wordpress_guidelines}

{preset_config}

{template_config}

RULES:
- Preserve factual meaning, names, numbers, dates, URLs, and quotes unless the source is clearly malformed.
- Do not invent claims, awards, partnerships, dates, locations, or statistics.
- Use standard press release structure with a strong opening paragraph and clean section flow.
- Improve clarity and organization, but keep the source anchored to the provided material.
- Keep the tone professional and publication-ready.
- Do not add stock photo markers or featured image markers.

OUTPUT FORMAT:
- Output valid HTML only.
- Do not output markdown.
- Do not output an <h1>.
- Use <p>, <h2>, <ul>, <ol>, <blockquote>, and <a> where appropriate.

METADATA:
At the very end, output:
<!-- METADATA: {"titles":["title1","title2",...{title_count} titles],"categories":["cat1","cat2",...{category_count} categories],"tags":["tag1","tag2",...{tag_count} tags],"description":"A concise SEO description under 160 characters"} -->

SOURCE MATERIAL:
{source_articles}
PROMPT,
                'is_default' => true,
            ],
            [
                'slug' => 'press-release-polish',
                'name' => 'Press Release Polish',
                'body' => <<<'PROMPT'
You are a professional press release editor.

Your job is to polish the submitted press release without spinning it into a materially different piece.

{custom_instructions}

{wordpress_guidelines}

{preset_config}

{template_config}

RULES:
- Fix grammar, punctuation, spelling, clarity, and flow.
- Preserve structure, voice, facts, names, dates, numbers, URLs, and quoted language as much as possible.
- Do not add unsupported claims or new narrative angles.
- Do not aggressively rewrite for originality; this is a polish pass, not a spin pass.
- Keep the result publication-ready and professional.
- Do not add stock photo markers or featured image markers.

OUTPUT FORMAT:
- Output valid HTML only.
- Do not output markdown.
- Do not output an <h1>.
- Use <p>, <h2>, <ul>, <ol>, <blockquote>, and <a> where appropriate.

METADATA:
At the very end, output:
<!-- METADATA: {"titles":["title1","title2",...{title_count} titles],"categories":["cat1","cat2",...{category_count} categories],"tags":["tag1","tag2",...{tag_count} tags],"description":"A concise SEO description under 160 characters"} -->

SOURCE MATERIAL:
{source_articles}
PROMPT,
                'is_default' => false,
            ],
        ];

        foreach ($templates as $template) {
            $existingId = DB::table('prompt_templates')->where('slug', $template['slug'])->value('id');
            if ($existingId) {
                DB::table('prompt_templates')->where('id', $existingId)->update([
                    'prompt_category_id' => $categoryId,
                    'name' => $template['name'],
                    'body' => $template['body'],
                    'updated_at' => now(),
                ]);
                continue;
            }

            if ($template['is_default']) {
                DB::table('prompt_templates')->where('prompt_category_id', $categoryId)->update(['is_default' => false]);
            }

            DB::table('prompt_templates')->insert([
                'prompt_category_id' => $categoryId,
                'name' => $template['name'],
                'slug' => $template['slug'],
                'body' => $template['body'],
                'is_default' => $template['is_default'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        if (!DB::getSchemaBuilder()->hasTable('prompt_templates')) {
            return;
        }

        DB::table('prompt_templates')
            ->whereIn('slug', ['press-release-spin', 'press-release-polish'])
            ->delete();
    }
};
