<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('publish_pipeline_operations')) {
            Schema::create('publish_pipeline_operations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('publish_article_id')->constrained('publish_articles')->cascadeOnDelete();
                $table->foreignId('publish_site_id')->nullable()->constrained('publish_sites')->nullOnDelete();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->string('operation_type', 20);
                $table->string('status', 20)->default('queued');
                $table->string('workflow_type', 80)->nullable();
                $table->string('transport', 20)->nullable();
                $table->string('queue_connection', 60)->nullable();
                $table->string('queue_name', 60)->nullable();
                $table->string('client_trace', 160);
                $table->string('trace_id', 160)->unique('ppo_trace_uidx');
                $table->boolean('debug_enabled')->default(false);
                $table->unsignedInteger('event_sequence')->default(0);
                $table->unsignedInteger('total_events')->default(0);
                $table->string('last_stage', 80)->nullable();
                $table->string('last_substage', 80)->nullable();
                $table->text('last_message')->nullable();
                $table->text('error_message')->nullable();
                $table->json('request_summary')->nullable();
                $table->json('result_payload')->nullable();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamp('last_event_at')->nullable();
                $table->timestamps();

                $table->index(['publish_article_id', 'operation_type', 'status'], 'ppo_art_type_stat_idx');
                $table->index(['publish_article_id', 'created_at'], 'ppo_art_created_idx');
                $table->index(['client_trace'], 'ppo_client_trace_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('publish_pipeline_operations');
    }
};
