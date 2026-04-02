<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('ai_detection_logs')) {
            Schema::create('ai_detection_logs', function (Blueprint $table) {
                $table->id();
                $table->string('detector', 50);
                $table->unsignedBigInteger('user_id')->nullable();
                $table->unsignedBigInteger('article_id')->nullable();
                $table->integer('text_length')->nullable();
                $table->text('text_sent')->nullable();
                $table->longText('raw_response')->nullable();
                $table->decimal('score', 5, 2)->nullable();
                $table->decimal('cost', 10, 6)->nullable();
                $table->boolean('debug_mode')->default(false);
                $table->boolean('success')->default(true);
                $table->string('error_message')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_detection_logs');
    }
};
