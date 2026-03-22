<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Create the publish_prompts table.
 * Stores reusable AI prompts for content generation.
 * Users can save and manage their own prompt library.
 */
return new class extends Migration
{
    /**
     * Run the migration.
     *
     * @return void
     */
    public function up(): void
    {
        if (!Schema::hasTable('publish_prompts')) {
            Schema::create('publish_prompts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('name');
                $table->longText('content');
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migration.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('publish_prompts');
    }
};
