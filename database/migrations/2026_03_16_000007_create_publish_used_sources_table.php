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
        Schema::create('publish_used_sources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('publish_account_id')->constrained('publish_accounts')->cascadeOnDelete();
            $table->foreignId('publish_article_id')->nullable()->constrained('publish_articles')->nullOnDelete();
            $table->string('url', 2048);
            $table->string('title')->nullable();
            $table->string('source_api')->nullable()->comment('gnews, newsdata, google-rss, web-scrape, etc.');
            $table->timestamps();

            $table->index(['publish_account_id', 'url'], 'used_sources_account_url_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('publish_used_sources');
    }
};
