<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add is_default column to publish_templates.
 */
return new class extends Migration
{
    /**
     * @return void
     */
    public function up(): void
    {
        if (Schema::hasTable('publish_templates') && !Schema::hasColumn('publish_templates', 'is_default')) {
            Schema::table('publish_templates', function (Blueprint $table) {
                $table->boolean('is_default')->default(false)->after('status');
            });
        }
    }

    /**
     * @return void
     */
    public function down(): void
    {
        if (Schema::hasColumn('publish_templates', 'is_default')) {
            Schema::table('publish_templates', function (Blueprint $table) {
                $table->dropColumn('is_default');
            });
        }
    }
};
