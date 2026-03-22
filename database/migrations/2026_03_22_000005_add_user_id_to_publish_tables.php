<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add user_id (nullable FK to users) to publish tables that currently use publish_account_id.
     * This migration does NOT remove publish_account_id — old data stays for reference.
     */
    public function up(): void
    {
        $tables = [
            'publish_sites',
            'publish_templates',
            'publish_campaigns',
            'publish_articles',
            'publish_used_sources',
            'publish_link_lists',
            'publish_sitemaps',
        ];

        foreach ($tables as $table) {
            if (Schema::hasTable($table) && !Schema::hasColumn($table, 'user_id')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->unsignedBigInteger('user_id')->nullable()->after('id');
                    $t->foreign('user_id')->references('id')->on('users')->nullOnDelete();
                });
            }
        }
    }

    /**
     * Reverse the migration — drop user_id columns.
     */
    public function down(): void
    {
        $tables = [
            'publish_sites',
            'publish_templates',
            'publish_campaigns',
            'publish_articles',
            'publish_used_sources',
            'publish_link_lists',
            'publish_sitemaps',
        ];

        foreach ($tables as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'user_id')) {
                Schema::table($table, function (Blueprint $t) use ($table) {
                    $t->dropForeign([$table . '_user_id_foreign']);
                    $t->dropColumn('user_id');
                });
            }
        }
    }
};
