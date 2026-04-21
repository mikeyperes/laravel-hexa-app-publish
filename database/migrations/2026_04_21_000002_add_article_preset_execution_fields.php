<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('publish_templates', function (Blueprint $table) {
            if (!Schema::hasColumn('publish_templates', 'headline_rules')) {
                $table->text('headline_rules')->nullable()->after('ai_prompt');
            }
            if (!Schema::hasColumn('publish_templates', 'search_online_for_additional_context')) {
                $table->boolean('search_online_for_additional_context')->default(true)->after('headline_rules');
            }
            if (!Schema::hasColumn('publish_templates', 'online_search_model_fallback')) {
                $table->string('online_search_model_fallback', 100)->nullable()->after('searching_agent');
            }
            if (!Schema::hasColumn('publish_templates', 'scrape_ai_model_fallback')) {
                $table->string('scrape_ai_model_fallback', 100)->nullable()->after('scraping_agent');
            }
            if (!Schema::hasColumn('publish_templates', 'spin_model_fallback')) {
                $table->string('spin_model_fallback', 100)->nullable()->after('spinning_agent');
            }
            if (!Schema::hasColumn('publish_templates', 'h2_notation')) {
                $table->string('h2_notation', 50)->default('capital_case')->after('max_links');
            }
            if (!Schema::hasColumn('publish_templates', 'inline_photo_min')) {
                $table->unsignedTinyInteger('inline_photo_min')->default(2)->after('h2_notation');
            }
            if (!Schema::hasColumn('publish_templates', 'inline_photo_max')) {
                $table->unsignedTinyInteger('inline_photo_max')->default(3)->after('inline_photo_min');
            }
            if (!Schema::hasColumn('publish_templates', 'featured_image_required')) {
                $table->boolean('featured_image_required')->default(true)->after('inline_photo_max');
            }
            if (!Schema::hasColumn('publish_templates', 'featured_image_must_be_landscape')) {
                $table->boolean('featured_image_must_be_landscape')->default(true)->after('featured_image_required');
            }
        });
    }

    public function down(): void
    {
        Schema::table('publish_templates', function (Blueprint $table) {
            foreach ([
                'featured_image_must_be_landscape',
                'featured_image_required',
                'inline_photo_max',
                'inline_photo_min',
                'h2_notation',
                'spin_model_fallback',
                'scrape_ai_model_fallback',
                'online_search_model_fallback',
                'search_online_for_additional_context',
                'headline_rules',
            ] as $column) {
                if (Schema::hasColumn('publish_templates', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
