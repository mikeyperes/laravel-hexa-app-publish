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
        Schema::create('publish_campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('publish_account_id')->constrained('publish_accounts')->cascadeOnDelete();
            $table->foreignId('publish_site_id')->constrained('publish_sites')->cascadeOnDelete();
            $table->foreignId('publish_template_id')->nullable()->constrained('publish_templates')->nullOnDelete();
            $table->string('name');
            $table->string('campaign_id')->unique()->comment('Auto-generated: CMP-YYYYMMDD-NNN');
            $table->text('description')->nullable();
            $table->text('topic')->nullable()->comment('Topic or keywords to search for');
            $table->json('keywords')->nullable()->comment('Array of keywords for article sourcing');
            $table->string('article_type')->nullable()->comment('Override template article type if set');
            $table->string('ai_engine')->nullable()->comment('Override template AI engine if set');
            $table->enum('delivery_mode', ['draft-local', 'draft-wordpress', 'auto-publish', 'review', 'notify'])->default('review');
            $table->unsignedInteger('articles_per_interval')->default(1);
            $table->string('interval_unit')->default('daily')->comment('hourly, daily, weekly, monthly');
            $table->json('article_sources')->nullable()->comment('Array of enabled article sources for this campaign');
            $table->json('photo_sources')->nullable()->comment('Override template photo sources if set');
            $table->json('link_list')->nullable()->comment('URLs to inject into articles');
            $table->json('sitemap_urls')->nullable()->comment('Sitemap URLs for internal linking');
            $table->unsignedInteger('max_links_per_article')->nullable();
            $table->enum('status', ['draft', 'active', 'paused', 'completed'])->default('draft');
            $table->timestamp('last_run_at')->nullable();
            $table->timestamp('next_run_at')->nullable();
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
        Schema::dropIfExists('publish_campaigns');
    }
};
