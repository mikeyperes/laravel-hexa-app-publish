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
        Schema::create('publish_articles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('publish_account_id')->constrained('publish_accounts')->cascadeOnDelete();
            $table->foreignId('publish_site_id')->constrained('publish_sites')->cascadeOnDelete();
            $table->foreignId('publish_campaign_id')->nullable()->constrained('publish_campaigns')->nullOnDelete();
            $table->foreignId('publish_template_id')->nullable()->constrained('publish_templates')->nullOnDelete();
            $table->string('article_id')->unique()->comment('Auto-generated: ART-YYYYMMDD-NNN');
            $table->string('title')->nullable();
            $table->longText('body')->nullable()->comment('Rich text content with photo placeholders');
            $table->text('excerpt')->nullable();
            $table->string('article_type')->nullable();
            $table->string('ai_engine_used')->nullable()->comment('Which AI engine generated this');
            $table->enum('status', [
                'sourcing', 'drafting', 'spinning', 'review',
                'ai-check', 'ready', 'published', 'failed', 'completed'
            ])->default('sourcing');
            $table->enum('delivery_mode', ['draft-local', 'draft-wordpress', 'auto-publish', 'review', 'notify'])->nullable();
            $table->json('source_articles')->nullable()->comment('Array of source article URLs and titles used for spinning');
            $table->json('photos')->nullable()->comment('Array of photo objects: source, url, alt_text, placement');
            $table->json('links_injected')->nullable()->comment('Array of links placed by AI with confirmation status');
            $table->decimal('ai_detection_score', 5, 2)->nullable()->comment('Sapling AI detection score 0-100');
            $table->decimal('seo_score', 5, 2)->nullable()->comment('SEO analysis score 0-100');
            $table->json('seo_data')->nullable()->comment('Detailed SEO analysis: keyword density, readability, etc.');
            $table->unsignedInteger('word_count')->nullable();
            $table->unsignedBigInteger('wp_post_id')->nullable()->comment('WordPress post ID after publishing');
            $table->string('wp_post_url')->nullable()->comment('WordPress post URL after publishing');
            $table->string('wp_status')->nullable()->comment('WordPress post status: draft, publish, pending');
            $table->timestamp('published_at')->nullable();
            $table->timestamp('scheduled_for')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('publish_articles');
    }
};
