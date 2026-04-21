<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('publish_article_activities')) {
            Schema::create('publish_article_activities', function (Blueprint $table) {
                $table->id();
                $table->foreignId('publish_article_id')->constrained('publish_articles')->cascadeOnDelete();
                $table->foreignId('publish_campaign_id')->nullable()->constrained('publish_campaigns')->nullOnDelete();
                $table->foreignId('publish_pipeline_operation_id')->nullable()->constrained('publish_pipeline_operations')->nullOnDelete();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('activity_group', 80)->nullable();
                $table->string('activity_type', 60);
                $table->string('stage', 80)->nullable();
                $table->string('substage', 80)->nullable();
                $table->string('status', 40)->nullable();
                $table->string('provider', 60)->nullable();
                $table->string('model', 120)->nullable();
                $table->string('agent', 80)->nullable();
                $table->string('method', 60)->nullable();
                $table->unsignedInteger('attempt_no')->nullable();
                $table->boolean('is_retry')->default(false);
                $table->boolean('success')->nullable();
                $table->string('title', 255)->nullable();
                $table->text('url')->nullable();
                $table->text('message')->nullable();
                $table->string('trace_id', 160)->nullable();
                $table->json('request_payload')->nullable();
                $table->json('response_payload')->nullable();
                $table->json('meta')->nullable();
                $table->timestamp('happened_at')->nullable();
                $table->timestamps();

                $table->index(['publish_article_id', 'created_at'], 'paa_article_created_idx');
                $table->index(['publish_article_id', 'activity_type'], 'paa_article_type_idx');
                $table->index(['publish_article_id', 'stage'], 'paa_article_stage_idx');
                $table->index(['publish_article_id', 'activity_group'], 'paa_article_group_idx');
                $table->index('trace_id', 'paa_trace_idx');
            });
        }

        if (Schema::hasTable('ai_activity_logs') && !Schema::hasColumn('ai_activity_logs', 'publish_article_id')) {
            Schema::table('ai_activity_logs', function (Blueprint $table) {
                $table->unsignedBigInteger('publish_article_id')->nullable()->after('user_id');
                $table->index('publish_article_id', 'ai_activity_logs_article_idx');
            });
        }

        if (Schema::hasTable('publish_scrape_logs') && !Schema::hasColumn('publish_scrape_logs', 'publish_article_id')) {
            Schema::table('publish_scrape_logs', function (Blueprint $table) {
                $table->unsignedBigInteger('publish_article_id')->nullable()->after('draft_id');
                $table->index('publish_article_id', 'publish_scrape_logs_article_idx');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('publish_scrape_logs') && Schema::hasColumn('publish_scrape_logs', 'publish_article_id')) {
            Schema::table('publish_scrape_logs', function (Blueprint $table) {
                $table->dropIndex('publish_scrape_logs_article_idx');
                $table->dropColumn('publish_article_id');
            });
        }

        if (Schema::hasTable('ai_activity_logs') && Schema::hasColumn('ai_activity_logs', 'publish_article_id')) {
            Schema::table('ai_activity_logs', function (Blueprint $table) {
                $table->dropIndex('ai_activity_logs_article_idx');
                $table->dropColumn('publish_article_id');
            });
        }

        Schema::dropIfExists('publish_article_activities');
    }
};
