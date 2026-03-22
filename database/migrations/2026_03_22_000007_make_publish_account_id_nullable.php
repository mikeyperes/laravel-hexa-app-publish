<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('publish_sites', function (Blueprint $table) {
            $table->unsignedBigInteger('publish_account_id')->nullable()->change();
        });

        // Make publish_account_id nullable on all tables that have it
        $tables = ['publish_templates', 'publish_campaigns', 'publish_articles', 'publish_used_sources', 'publish_link_lists', 'publish_sitemaps'];
        foreach ($tables as $t) {
            if (Schema::hasTable($t) && Schema::hasColumn($t, 'publish_account_id')) {
                Schema::table($t, function (Blueprint $table) {
                    $table->unsignedBigInteger('publish_account_id')->nullable()->change();
                });
            }
        }
    }

    public function down(): void
    {
        // Not reversible safely
    }
};
