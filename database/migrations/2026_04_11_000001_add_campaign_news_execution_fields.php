<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('publish_campaigns') && !Schema::hasColumn('publish_campaigns', 'ai_instructions')) {
            Schema::table('publish_campaigns', function (Blueprint $table) {
                $table->text('ai_instructions')->nullable()->after('description');
            });

            if (Schema::hasColumn('publish_campaigns', 'notes')) {
                DB::table('publish_campaigns')
                    ->whereNull('ai_instructions')
                    ->update(['ai_instructions' => DB::raw('notes')]);
            }
        }

        if (Schema::hasTable('campaign_presets') && !Schema::hasColumn('campaign_presets', 'final_article_method')) {
            Schema::table('campaign_presets', function (Blueprint $table) {
                $table->string('final_article_method', 50)
                    ->default('news-search')
                    ->after('name');
            });
        }

        if (
            Schema::hasTable('campaign_presets')
            && Schema::hasColumn('campaign_presets', 'source_method')
            && DB::getDriverName() !== 'sqlite'
        ) {
            DB::statement("ALTER TABLE campaign_presets MODIFY source_method ENUM('keyword', 'trending', 'genre', 'local') NOT NULL DEFAULT 'keyword' COMMENT 'Campaign discovery mode'");
        }
    }

    public function down(): void
    {
        if (
            Schema::hasTable('campaign_presets')
            && Schema::hasColumn('campaign_presets', 'source_method')
            && DB::getDriverName() !== 'sqlite'
        ) {
            DB::statement("ALTER TABLE campaign_presets MODIFY source_method ENUM('trending', 'genre', 'local') NOT NULL DEFAULT 'trending' COMMENT 'Default source method'");
        }

        if (Schema::hasTable('campaign_presets') && Schema::hasColumn('campaign_presets', 'final_article_method')) {
            Schema::table('campaign_presets', function (Blueprint $table) {
                $table->dropColumn('final_article_method');
            });
        }

        if (Schema::hasTable('publish_campaigns') && Schema::hasColumn('publish_campaigns', 'ai_instructions')) {
            Schema::table('publish_campaigns', function (Blueprint $table) {
                $table->dropColumn('ai_instructions');
            });
        }
    }
};
