<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Create the publish_presets table.
 * Stores user-defined publishing presets that combine default settings
 * for article format, tone, images, and publishing behavior.
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
        if (!Schema::hasTable('publish_presets')) {
            Schema::create('publish_presets', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('name');
                $table->unsignedBigInteger('default_site_id')->nullable();
                $table->string('follow_links')->default('nofollow');
                $table->string('article_format')->nullable()->comment('Pulls from article_formats list');
                $table->string('tone')->nullable()->comment('Pulls from tones list');
                $table->string('image_preference')->nullable()->comment('Pulls from image_preferences list');
                $table->string('default_publish_action')->default('draft_local');
                $table->integer('default_category_count')->default(3);
                $table->integer('default_tag_count')->default(5);
                $table->string('image_layout')->nullable()->comment('Pulls from image_layout_rules list');
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
        Schema::dropIfExists('publish_presets');
    }
};
