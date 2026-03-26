<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Make publish_site_id nullable on publish_articles so drafts can be saved without a site.
 */
return new class extends Migration
{
    /**
     * @return void
     */
    public function up(): void
    {
        Schema::table('publish_articles', function (Blueprint $table) {
            $table->unsignedBigInteger('publish_site_id')->nullable()->change();
        });
    }

    /**
     * @return void
     */
    public function down(): void
    {
        Schema::table('publish_articles', function (Blueprint $table) {
            $table->unsignedBigInteger('publish_site_id')->nullable(false)->change();
        });
    }
};
