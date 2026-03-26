<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add status column (draft/active) to publish_templates and publish_presets.
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
        if (Schema::hasTable('publish_templates') && !Schema::hasColumn('publish_templates', 'status')) {
            Schema::table('publish_templates', function (Blueprint $table) {
                $table->string('status', 20)->default('draft')->after('name');
            });
        }

        if (Schema::hasTable('publish_presets') && !Schema::hasColumn('publish_presets', 'status')) {
            Schema::table('publish_presets', function (Blueprint $table) {
                $table->string('status', 20)->default('draft')->after('name');
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
        if (Schema::hasColumn('publish_templates', 'status')) {
            Schema::table('publish_templates', function (Blueprint $table) {
                $table->dropColumn('status');
            });
        }

        if (Schema::hasColumn('publish_presets', 'status')) {
            Schema::table('publish_presets', function (Blueprint $table) {
                $table->dropColumn('status');
            });
        }
    }
};
