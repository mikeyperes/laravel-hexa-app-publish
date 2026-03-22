<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Create the publish_bookmarks table.
 * Stores bookmarked URLs for content sourcing.
 * Users can save articles, references, and research links.
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
        if (!Schema::hasTable('publish_bookmarks')) {
            Schema::create('publish_bookmarks', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('url');
                $table->string('title')->nullable();
                $table->string('source')->default('manual');
                $table->text('tags')->nullable();
                $table->text('notes')->nullable();
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
        Schema::dropIfExists('publish_bookmarks');
    }
};
