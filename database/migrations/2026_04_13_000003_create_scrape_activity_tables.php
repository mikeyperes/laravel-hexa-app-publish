<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('publish_scrape_logs')) {
            Schema::create('publish_scrape_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('url', 2048);
                $table->string('domain');
                $table->string('method')->nullable();
                $table->string('user_agent')->nullable();
                $table->integer('timeout')->nullable();
                $table->integer('retries')->nullable();
                $table->integer('http_status')->nullable();
                $table->integer('response_time_ms')->nullable();
                $table->integer('word_count')->nullable();
                $table->boolean('success')->default(false);
                $table->text('error_message')->nullable();
                $table->string('fallback_used')->nullable();
                $table->string('source')->default('pipeline');
                $table->unsignedBigInteger('draft_id')->nullable();
                $table->timestamps();

                $table->index('domain');
                $table->index('success');
                $table->index('created_at');
            });
        }

        if (!Schema::hasTable('publish_banned_sources')) {
            Schema::create('publish_banned_sources', function (Blueprint $table) {
                $table->id();
                $table->string('domain')->unique();
                $table->string('reason')->nullable();
                $table->foreignId('banned_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('publish_source_notes')) {
            Schema::create('publish_source_notes', function (Blueprint $table) {
                $table->id();
                $table->string('domain')->unique();
                $table->text('notes')->nullable();
                $table->string('recommended_method')->nullable();
                $table->string('recommended_ua')->nullable();
                $table->text('working_instructions')->nullable();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('publish_source_notes');
        Schema::dropIfExists('publish_banned_sources');
        Schema::dropIfExists('publish_scrape_logs');
    }
};
