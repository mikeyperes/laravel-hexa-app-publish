<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add AI agent model fields to publish_templates (article presets).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('publish_templates', function (Blueprint $table) {
            $table->string('searching_agent')->nullable()->after('rules');
            $table->string('scraping_agent')->nullable()->after('searching_agent');
            $table->string('spinning_agent')->nullable()->after('scraping_agent');
        });
    }

    public function down(): void
    {
        Schema::table('publish_templates', function (Blueprint $table) {
            $table->dropColumn(['searching_agent', 'scraping_agent', 'spinning_agent']);
        });
    }
};
