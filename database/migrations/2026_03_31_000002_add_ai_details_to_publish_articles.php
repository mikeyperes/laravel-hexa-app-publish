<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('publish_articles', function (Blueprint $table) {
            if (!Schema::hasColumn('publish_articles', 'resolved_prompt')) {
                $table->longText('resolved_prompt')->nullable()->after('ai_cost');
            }
            if (!Schema::hasColumn('publish_articles', 'ai_tokens_input')) {
                $table->integer('ai_tokens_input')->nullable()->after('resolved_prompt');
            }
            if (!Schema::hasColumn('publish_articles', 'ai_tokens_output')) {
                $table->integer('ai_tokens_output')->nullable()->after('ai_tokens_input');
            }
            if (!Schema::hasColumn('publish_articles', 'ai_provider')) {
                $table->string('ai_provider', 50)->nullable()->after('ai_tokens_output');
            }
            if (!Schema::hasColumn('publish_articles', 'user_ip')) {
                $table->string('user_ip', 45)->nullable()->after('author');
            }
            if (!Schema::hasColumn('publish_articles', 'photo_suggestions')) {
                $table->json('photo_suggestions')->nullable()->after('wp_images');
            }
            if (!Schema::hasColumn('publish_articles', 'featured_image_search')) {
                $table->string('featured_image_search')->nullable()->after('photo_suggestions');
            }
        });
    }

    public function down(): void
    {
        Schema::table('publish_articles', function (Blueprint $table) {
            $cols = ['resolved_prompt', 'ai_tokens_input', 'ai_tokens_output', 'ai_provider', 'user_ip', 'photo_suggestions', 'featured_image_search'];
            foreach ($cols as $col) {
                if (Schema::hasColumn('publish_articles', $col)) $table->dropColumn($col);
            }
        });
    }
};
