<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Create the publish_master_settings table.
 * Stores system-wide publishing guidelines and rules.
 * Supports WordPress content guidelines and spinning/rewriting guidelines.
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
        if (!Schema::hasTable('publish_master_settings')) {
            Schema::create('publish_master_settings', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->enum('type', ['wordpress_guidelines', 'spinning_guidelines']);
                $table->longText('content');
                $table->boolean('is_active')->default(true);
                $table->integer('sort_order')->default(0);
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
        Schema::dropIfExists('publish_master_settings');
    }
};
