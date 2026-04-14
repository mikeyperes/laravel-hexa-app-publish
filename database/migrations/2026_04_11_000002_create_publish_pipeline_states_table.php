<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('publish_pipeline_states')) {
            Schema::create('publish_pipeline_states', function (Blueprint $table) {
                $table->id();
                $table->foreignId('publish_article_id')->constrained('publish_articles')->cascadeOnDelete();
                $table->string('workflow_type', 80)->nullable();
                $table->unsignedInteger('state_version')->default(1);
                $table->json('payload')->nullable();
                $table->timestamps();

                $table->unique('publish_article_id');
                $table->index('workflow_type');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('publish_pipeline_states');
    }
};
