<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('ai_activity_logs')) {
            Schema::create('ai_activity_logs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->string('provider', 20)->default('anthropic');
                $table->string('model', 100);
                $table->string('agent', 100)->default('unknown');
                $table->integer('prompt_tokens')->default(0);
                $table->integer('completion_tokens')->default(0);
                $table->integer('total_tokens')->default(0);
                $table->decimal('cost', 10, 6)->default(0);
                $table->string('ip_address', 45)->nullable();
                $table->string('api_key_masked', 20)->nullable();
                $table->text('system_prompt')->nullable();
                $table->text('user_message')->nullable();
                $table->longText('response_content')->nullable();
                $table->boolean('success')->default(true);
                $table->text('error_message')->nullable();
                $table->timestamps();

                $table->index('user_id');
                $table->index('provider');
                $table->index('model');
                $table->index('agent');
                $table->index('created_at');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_activity_logs');
    }
};
