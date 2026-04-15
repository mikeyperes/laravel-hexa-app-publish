<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('publish_pipeline_runs')) {
            Schema::create('publish_pipeline_runs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('publish_article_id')->constrained('publish_articles')->cascadeOnDelete();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('client_trace', 160);
                $table->string('workflow_type', 80)->nullable();
                $table->boolean('debug_enabled')->default(false);
                $table->timestamp('started_at')->nullable();
                $table->timestamp('last_event_at')->nullable();
                $table->string('last_scope', 40)->nullable();
                $table->string('last_type', 40)->nullable();
                $table->string('last_stage', 80)->nullable();
                $table->string('last_substage', 80)->nullable();
                $table->unsignedInteger('total_events')->default(0);
                $table->timestamps();

                $table->unique(['publish_article_id', 'client_trace'], 'ppr_runs_article_trace_uidx');
                $table->index(['publish_article_id', 'last_event_at'], 'ppr_runs_article_last_event_idx');
            });
        }

        if (!Schema::hasTable('publish_pipeline_run_events')) {
            Schema::create('publish_pipeline_run_events', function (Blueprint $table) {
                $table->id();
                $table->foreignId('publish_pipeline_run_id')->constrained('publish_pipeline_runs')->cascadeOnDelete();
                $table->foreignId('publish_article_id')->constrained('publish_articles')->cascadeOnDelete();
                $table->string('client_event_id', 190);
                $table->string('run_trace', 160)->nullable();
                $table->timestamp('captured_at')->nullable();
                $table->unsignedInteger('client_sequence')->nullable();
                $table->string('scope', 40)->nullable();
                $table->string('type', 40)->nullable();
                $table->text('message')->nullable();
                $table->string('stage', 80)->nullable();
                $table->string('substage', 80)->nullable();
                $table->string('trace_id', 160)->nullable();
                $table->unsignedInteger('sequence_no')->nullable();
                $table->string('method', 20)->nullable();
                $table->unsignedSmallInteger('status_code')->nullable();
                $table->unsignedInteger('duration_ms')->nullable();
                $table->unsignedSmallInteger('step')->nullable();
                $table->text('url')->nullable();
                $table->longText('details')->nullable();
                $table->longText('payload_preview')->nullable();
                $table->longText('response_preview')->nullable();
                $table->boolean('debug_only')->default(false);
                $table->json('meta')->nullable();
                $table->timestamps();

                $table->unique('client_event_id', 'ppr_events_client_event_uidx');
                $table->index(['publish_pipeline_run_id', 'client_sequence'], 'ppr_events_run_seq_idx');
                $table->index(['publish_article_id', 'captured_at'], 'ppr_events_article_time_idx');
                $table->index('trace_id', 'ppr_events_trace_idx');
                $table->index(['scope', 'type'], 'ppr_events_scope_type_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('publish_pipeline_run_events');
        Schema::dropIfExists('publish_pipeline_runs');
    }
};
