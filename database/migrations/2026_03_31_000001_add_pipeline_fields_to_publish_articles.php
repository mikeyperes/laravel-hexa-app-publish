<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('publish_articles', function (Blueprint $table) {
            if (!Schema::hasColumn('publish_articles', 'pipeline_session_id')) {
                $table->string('pipeline_session_id')->nullable()->after('id');
            }
            if (!Schema::hasColumn('publish_articles', 'categories')) {
                $table->json('categories')->nullable()->after('links_injected');
            }
            if (!Schema::hasColumn('publish_articles', 'tags')) {
                $table->json('tags')->nullable()->after('categories');
            }
            if (!Schema::hasColumn('publish_articles', 'wp_images')) {
                $table->json('wp_images')->nullable()->after('photos');
            }
            if (!Schema::hasColumn('publish_articles', 'ai_cost')) {
                $table->decimal('ai_cost', 10, 6)->nullable()->after('ai_engine_used');
            }
            if (!Schema::hasColumn('publish_articles', 'author')) {
                $table->string('author')->nullable()->after('created_by');
            }
            if (!Schema::hasColumn('publish_articles', 'preset_id')) {
                $table->unsignedBigInteger('preset_id')->nullable()->after('publish_template_id');
            }
            if (!Schema::hasColumn('publish_articles', 'user_id') && Schema::hasColumn('publish_articles', 'created_by')) {
                $table->unsignedBigInteger('user_id')->nullable()->after('id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('publish_articles', function (Blueprint $table) {
            $cols = ['pipeline_session_id', 'categories', 'tags', 'wp_images', 'ai_cost', 'author', 'preset_id'];
            foreach ($cols as $col) {
                if (Schema::hasColumn('publish_articles', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
