<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('publish_sitemaps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('publish_account_id')->constrained('publish_accounts')->cascadeOnDelete();
            $table->string('name');
            $table->text('sitemap_url')->comment('URL to the sitemap.xml');
            $table->json('parsed_urls')->nullable()->comment('Cached array of URLs from sitemap');
            $table->unsignedInteger('url_count')->default(0);
            $table->timestamp('last_parsed_at')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('publish_sitemaps');
    }
};
