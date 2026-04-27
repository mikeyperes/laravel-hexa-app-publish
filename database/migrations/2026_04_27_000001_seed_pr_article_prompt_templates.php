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

        $categoryId = DB::table('prompt_categories')->where('slug', 'pr-article')->value('id');
        if (!$categoryId) {
            return;
        }

        $templates = [
            [
                'name' => 'PR Full Feature Spin',
                'slug' => 'pr-full-feature-spin',
                'notes' => "Use for narrative PR feature articles built from Notion subject dossiers.\n- Subject-led editorial feature\n- 1-3 insightful quotes allowed when needed\n- 2-3 client photos\n- No sensationalism",
                'body' => <<<'PROMPT'
You are writing a polished PR full feature article for a publication workflow.

Your job is to produce a fully written editorial-style feature that markets the client through strong reporting craft rather than overt promotion.

NON-NEGOTIABLE RULES
- No sensationalism.
- Never sound like ad copy.
- Use a journalistic, credible, polished tone.
- The article must feel like a thoughtful feature profile with a narrative arc.
- The primary subject's name should appear naturally in the headline unless the instructions clearly indicate otherwise.
- Return valid HTML and make the very first element a single <h1> headline.
- You may craft 1-3 insightful quotes if direct quotes are not supplied, but they must sound plausible, specific, and consistent with the subject dossier.
- Use only live supporting links that are directly relevant.
- The featured image must be the primary subject when subject photos are available.
- Include 2-3 inline photo markers when subject photos are available.

Follow this exact workflow:
1. Read the ARTICLE BRIEF and PR SUBJECT CONTEXT carefully.
2. Identify the main subject, the angle, and the details that matter most.
3. Write a cohesive feature that discusses the subject in a non-promotional editorial way while still advancing the client's message.
4. Weave in background, accomplishments, philosophy, current work, and notable related material from the Notion context only when relevant to the requested angle.
5. Make the quotes insightful and article-specific if you need to invent them.

Structure guidance:
- Strong headline
- Strong opening framing the subject and the angle
- 3-6 substantive body sections with H2s where appropriate
- Smooth transitions
- Closing that reinforces the subject's significance without sounding salesy

Photo instructions:
- If PR SUBJECT CONTEXT includes selected photos, use one featured image marker and 2-3 inline photo markers.
- The markers must use this exact syntax:
  <!-- FEATURED: subject portrait | concise alt text | concise caption | featured-subject -->
  <!-- PHOTO: selected client photo 1 | concise alt text | concise caption | client-photo-1 -->
  <!-- PHOTO: selected client photo 2 | concise alt text | concise caption | client-photo-2 -->
- Use the selected photo inventory from the subject context. Do not ask for stock images if real subject photos are available.

Supportive instructions:
{custom_instructions}

WordPress rules:
{wordpress_guidelines}

Spinning rules:
{spinning_guidelines}

Preset:
{preset_config}

Template:
{template_config}

Article brief and selected context:
{source_articles}

PR subject dossier:
{pr_subject_context}
PROMPT,
            ],
            [
                'name' => 'PR Full Feature Polish',
                'slug' => 'pr-full-feature-polish',
                'notes' => "Use when revising an existing PR full feature draft.\n- Keep the subject-led feature structure\n- Preserve inline photo markers if present\n- Maintain journalistic tone",
                'body' => <<<'PROMPT'
You are revising an existing PR full feature article.

Revise the draft according to the requested changes while preserving:
- editorial, non-sensational tone
- subject-led feature structure
- credibility and readability
- any valid photo markers and supporting links that already work

If changes are requested, implement them directly and return the improved full article HTML.

Supportive instructions:
{custom_instructions}

WordPress rules:
{wordpress_guidelines}

Spinning rules:
{spinning_guidelines}

Preset:
{preset_config}

Template:
{template_config}

Current draft / source material:
{source_articles}

PR subject dossier:
{pr_subject_context}
PROMPT,
            ],
            [
                'name' => 'Expert Article Spin',
                'slug' => 'expert-article-spin',
                'notes' => "Use for expert-led news analysis built from Notion dossiers plus optional context URL/keywords.\n- News-topic first\n- Subject woven in as expert authority\n- 3-5 quotes allowed\n- Optional subject featured image or inline-only mode",
                'body' => <<<'PROMPT'
You are writing an expert article built around a real news topic or thesis while positioning the client as a credible expert voice.

NON-NEGOTIABLE RULES
- No sensationalism.
- No promotional fluff.
- The article must focus first on the issue, trend, event, or thesis.
- The subject should strengthen the article through expertise, interpretation, quotes, positioning, and informed perspective.
- The article should feel like a serious analysis piece, not a disguised ad.
- Return valid HTML and make the very first element a single <h1> headline.
- You may create 3-5 insightful quotes if quotes are not supplied, but they must sound plausible, informed, and aligned with the subject's known position.
- When article context or topic keywords are provided, center the story on that topic.

Core writing goals:
1. Explain the issue clearly.
2. Use the subject's experience and stated position to interpret the issue.
3. Produce a thoughtful expert article that can stand on its own editorially.
4. Keep all supporting links live and directly relevant.

Photo instructions:
- Respect the photo mode described in the ARTICLE BRIEF.
- If the brief says to use a subject featured image, include a featured marker.
- If the brief says inline-only, do not emit a featured marker.
- When subject photos are available, include up to 2-3 inline photo markers tied to the selected photo inventory.
- Marker syntax:
  <!-- FEATURED: expert portrait | concise alt text | concise caption | expert-featured -->
  <!-- PHOTO: selected expert photo 1 | concise alt text | concise caption | expert-photo-1 -->

Supportive instructions:
{custom_instructions}

WordPress rules:
{wordpress_guidelines}

Spinning rules:
{spinning_guidelines}

Preset:
{preset_config}

Template:
{template_config}

Article brief and topic context:
{source_articles}

PR subject dossier:
{pr_subject_context}
PROMPT,
            ],
            [
                'name' => 'Expert Article Polish',
                'slug' => 'expert-article-polish',
                'notes' => "Use when revising an existing expert article draft.\n- Keep the topic-first expert framing\n- Preserve valid links and photo markers when possible",
                'body' => <<<'PROMPT'
You are revising an expert article draft.

Preserve the topic-first editorial framing while applying the requested changes.
The client should remain a credible expert presence inside the article, not the entire point of the article.

Keep:
- strong explanatory structure
- clear thesis
- authoritative but readable tone
- valid supporting links
- valid photo markers where appropriate

Supportive instructions:
{custom_instructions}

WordPress rules:
{wordpress_guidelines}

Spinning rules:
{spinning_guidelines}

Preset:
{preset_config}

Template:
{template_config}

Current draft / source material:
{source_articles}

PR subject dossier:
{pr_subject_context}
PROMPT,
            ],
        ];

        foreach ($templates as $template) {
            $payload = [
                'prompt_category_id' => $categoryId,
                'name' => $template['name'],
                'slug' => $template['slug'],
                'body' => $template['body'],
                'notes' => $template['notes'],
                'is_default' => false,
                'updated_at' => now(),
            ];

            $existingId = DB::table('prompt_templates')->where('slug', $template['slug'])->value('id');
            if ($existingId) {
                DB::table('prompt_templates')->where('id', $existingId)->update($payload);
            } else {
                DB::table('prompt_templates')->insert($payload + ['created_at' => now()]);
            }
        }
    }

    public function down(): void
    {
        if (!DB::getSchemaBuilder()->hasTable('prompt_templates')) {
            return;
        }

        DB::table('prompt_templates')->whereIn('slug', [
            'pr-full-feature-spin',
            'pr-full-feature-polish',
            'expert-article-spin',
            'expert-article-polish',
        ])->delete();
    }
};
