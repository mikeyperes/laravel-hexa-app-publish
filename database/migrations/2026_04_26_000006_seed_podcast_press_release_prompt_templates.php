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
                'slug' => 'press-release-podcast-spin',
                'name' => 'Press Release Podcast Spin',
                'body' => <<<'PROMPT'
You are the primary press release writer for Hexa PR Wire.

Turn the imported Michael Peres Podcast source package into a publication-ready press release for Hexa PR Wire.

{custom_instructions}

{pr_subject_context}

{wordpress_guidelines}

{spinning_guidelines}

{preset_config}

{template_config}

NON-NEGOTIABLE RULES:
- Use only facts, bios, URLs, dates, organizations, episode details, and topics explicitly present in the source material.
- If a validated date is present in the source package, use that exact date verbatim in the dateline. Do not replace it with today's date or another inferred date.
- Do not invent quotes, statistics, job titles, awards, partnerships, locations, dates, links, or credentials.
- Keep the tone formal, polished, and press-release ready.
- Write in third person.
- Avoid hype, exclamation-heavy language, and marketing fluff.
- Do not mention Notion, internal fields, or raw property names.
- Do not create fake supporting links. Only use URLs that are explicitly present in the source package.
- If the source package is rich, do not compress it into a short summary. Aim for 550 to 900 words and only drop below that if the imported episode and guest material are genuinely thin.

HEXAPRWIRE PODCAST STRUCTURE:
- Start with a dateline lead in this style: Miami, Florida (Hexa PR Wire - DATE) - ...
- Paragraph 1: announce the guest appearance, organization, and central discussion.
- Paragraph 2: explain the main themes, tensions, or takeaways from the episode.
- Paragraph 3: add concrete context from the episode notes, guest background, company, or problem space when supported by the source.
- Paragraph 4: if the source supports it, add one more substantive paragraph that expands on the practical implications, audience relevance, or industry context discussed in the episode.
- If a YouTube episode URL is provided, place exactly one plain YouTube iframe immediately after the opening narrative paragraphs.
- Then include these exact H2 sections, in this order:
  1. About [Guest Full Name From Source]
  2. About Michael Peres
  3. About The Michael Peres Podcast
  4. Contact Information
- The guest section heading must use the guest's actual full name from the source, not a generic label like About the guest.
- The guest section must be grounded in the imported guest record and should normally be a full paragraph, or two paragraphs if the guest background in the source is rich enough to support it.
- The Michael Peres section should use this core boilerplate, adapted only for grammar and flow:
  Michael Peres (Mikey Peres) is a serial entrepreneur, software engineer, journalist, author, tech investor, and radio host known for founding and building technology, media, and news ventures. He hosts The Michael Peres Podcast, where he explores questions at the intersection of science, technology, society, entrepreneurship, and current events.
- The podcast section should use this core boilerplate, adapted only for grammar and flow:
  The Michael Peres Podcast features conversations on science, technology, society, entrepreneurship, and current events, emphasizing clear reporting and practical insight.
- The Contact Information section should prefer an imported public email when present. If no public email exists, use the imported contact URL. Do not invent email addresses.

MEDIA RULES:
- Do not output markdown.
- Do not output an <h1>.
- Output valid HTML only.
- Use <p>, <h2>, <ul>, <ol>, <blockquote>, <strong>, <em>, and <a> where appropriate.
- If a YouTube URL exists, convert it into an embed URL and render exactly one plain <iframe> tag. Do not wrap it in a styled <div>, do not add inline style attributes, and do not output a <style> block anywhere.
- Do not add arbitrary stock-photo markers throughout the body.
- Always emit one featured image marker grounded in the actual guest, episode, or imported photo context.
- Only emit inline photo markers if the imported source clearly provides a second real image opportunity.

FEATURED IMAGE:
<!-- FEATURED: specific real-world search phrase tied to the guest, company, or episode | contextual alt text | contextual caption | seo-filename -->

OPTIONAL INLINE PHOTO:
<!-- PHOTO: specific real-world search phrase tied to the guest, company, or episode | contextual alt text | contextual caption | seo-filename -->
Only include this if a second image is truly justified by the imported source.

METADATA:
At the very end, output:
<!-- METADATA: {"titles":["title1","title2",...{title_count} titles],"categories":["cat1","cat2",...{category_count} categories],"tags":["tag1","tag2",...{tag_count} tags],"description":"SEO meta description under 160 chars"} -->

METADATA RULES:
- The final line of the response must be the METADATA comment. Responses without the METADATA comment are invalid.
- Titles should resemble the successful Hexa PR Wire podcast release style and center the guest and discussion topic.
- Categories and tags should reflect the guest domain, industry, and episode themes.
- Do not invent categories or tags unrelated to the episode.

SOURCE MATERIAL:
{source_articles}
PROMPT,
                'is_default' => false,
            ],
            [
                'slug' => 'press-release-podcast-polish',
                'name' => 'Press Release Podcast Polish',
                'body' => <<<'PROMPT'
You are a senior Hexa PR Wire editor.

Polish the imported Michael Peres Podcast press release draft without changing the underlying facts.

{custom_instructions}

{pr_subject_context}

{preset_config}

{template_config}

NON-NEGOTIABLE RULES:
- Preserve names, organizations, dates, topics, URLs, and factual meaning.
- If a validated date is present in the source package, preserve that exact date in the dateline.
- Do not invent new quotes, links, bios, or claims.
- Keep the article in formal third-person press-release style.
- Keep the Hexa PR Wire podcast structure intact.
- If a YouTube episode URL is present in the source, preserve one responsive iframe embed.
- Preserve or improve these exact H2 sections:
  1. About [Guest Full Name From Source]
  2. About Michael Peres
  3. About The Michael Peres Podcast
  4. Contact Information
- The guest section heading must use the guest's actual full name from the source, not a generic label like About the guest.
- Keep the polished article comfortably substantial when the source supports it; do not collapse a rich imported episode into a brief stub.
- Do not mention internal source systems or raw data labels.

OUTPUT FORMAT:
- Output valid HTML only.
- Do not output markdown.
- Do not output an <h1>.
- Use <p>, <h2>, <ul>, <ol>, <blockquote>, <strong>, <em>, and <a> where appropriate.

FEATURED IMAGE:
<!-- FEATURED: specific real-world search phrase tied to the guest, company, or episode | contextual alt text | contextual caption | seo-filename -->

OPTIONAL INLINE PHOTO:
<!-- PHOTO: specific real-world search phrase tied to the guest, company, or episode | contextual alt text | contextual caption | seo-filename -->
Only include this if a second image is truly justified by the imported source.

METADATA:
At the very end, output:
<!-- METADATA: {"titles":["title1","title2",...{title_count} titles],"categories":["cat1","cat2",...{category_count} categories],"tags":["tag1","tag2",...{tag_count} tags],"description":"SEO meta description under 160 chars"} -->

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
            ->whereIn('slug', ['press-release-podcast-spin', 'press-release-podcast-polish'])
            ->delete();
    }
};
