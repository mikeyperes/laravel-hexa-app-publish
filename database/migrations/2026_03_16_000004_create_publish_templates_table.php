<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('publish_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('publish_account_id')->constrained('publish_accounts')->cascadeOnDelete();
            $table->string('name');
            $table->string('article_type')->nullable()->comment('editorial, opinion, news-report, local-news, expert-article, pr-full-feature, press-release');
            $table->text('description')->nullable();
            $table->text('ai_prompt')->nullable()->comment('Custom AI instructions for this template');
            $table->string('ai_engine')->nullable()->comment('anthropic or chatgpt');
            $table->string('tone')->nullable()->comment('e.g. formal, casual, authoritative');
            $table->unsignedInteger('word_count_min')->nullable();
            $table->unsignedInteger('word_count_max')->nullable();
            $table->unsignedInteger('photos_per_article')->nullable();
            $table->json('photo_sources')->nullable()->comment('Array of enabled photo sources');
            $table->unsignedInteger('max_links')->nullable();
            $table->json('structure')->nullable()->comment('Section structure: intro, body sections, conclusion, etc.');
            $table->json('rules')->nullable()->comment('Additional rules and criteria as key-value pairs');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('publish_templates');
    }
};
