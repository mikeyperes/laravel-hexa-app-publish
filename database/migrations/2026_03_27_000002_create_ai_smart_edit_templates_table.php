<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('ai_smart_edit_templates')) {
            Schema::create('ai_smart_edit_templates', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->text('prompt');
                $table->string('category', 50)->default('general');
                $table->integer('sort_order')->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });

            // Seed defaults
            \DB::table('ai_smart_edit_templates')->insert([
                ['name' => 'Make More Formal', 'prompt' => 'Rewrite this article in a more formal, professional tone. Maintain all factual content but adjust language to be suitable for a business audience.', 'category' => 'tone', 'sort_order' => 1, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
                ['name' => 'Make More Casual', 'prompt' => 'Rewrite this article in a casual, conversational tone. Make it feel like a friendly blog post while keeping all the facts.', 'category' => 'tone', 'sort_order' => 2, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
                ['name' => 'Add SEO Keywords', 'prompt' => 'Optimize this article for SEO. Add relevant keywords naturally throughout, improve headings for search, and ensure meta-friendly structure. Do not keyword stuff.', 'category' => 'seo', 'sort_order' => 3, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
                ['name' => 'Shorten Article', 'prompt' => 'Condense this article to approximately half its current length. Keep the most important points and remove redundancy. Maintain the key message.', 'category' => 'length', 'sort_order' => 4, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
                ['name' => 'Expand with More Detail', 'prompt' => 'Expand this article with more detail, examples, and supporting points. Add depth to each section. Target approximately double the current word count.', 'category' => 'length', 'sort_order' => 5, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
                ['name' => 'Improve Introduction', 'prompt' => 'Rewrite only the introduction paragraph to be more compelling and hook the reader immediately. Use a strong opening statement or question.', 'category' => 'structure', 'sort_order' => 6, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
                ['name' => 'Add Strong Conclusion', 'prompt' => 'Add or rewrite the conclusion paragraph. Summarize key takeaways, include a call to action, and leave the reader with a memorable final thought.', 'category' => 'structure', 'sort_order' => 7, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
                ['name' => 'Fix Grammar & Style', 'prompt' => 'Proofread and fix all grammar, spelling, punctuation, and style issues. Improve sentence flow and readability. Do not change the meaning or structure.', 'category' => 'polish', 'sort_order' => 8, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
                ['name' => 'Make More Engaging', 'prompt' => 'Rewrite this article to be more engaging and compelling. Add rhetorical questions, vivid language, and stronger transitions between sections.', 'category' => 'tone', 'sort_order' => 9, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
                ['name' => 'Rewrite for Different Audience', 'prompt' => 'Rewrite this article targeting a general consumer audience (not industry experts). Simplify technical terms, add context for jargon, and use everyday language.', 'category' => 'tone', 'sort_order' => 10, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_smart_edit_templates');
    }
};
