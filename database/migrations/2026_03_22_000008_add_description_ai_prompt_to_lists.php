<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add description and ai_prompt columns to the lists table.
 * These are used by the publishing app's list categories (Article Formats, Tones, etc.)
 * Core lists that don't need these fields simply leave them null.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lists', function (Blueprint $table) {
            if (!Schema::hasColumn('lists', 'description')) {
                $table->text('description')->nullable()->after('list_value');
            }
            if (!Schema::hasColumn('lists', 'ai_prompt')) {
                $table->text('ai_prompt')->nullable()->after('description');
            }
        });
    }

    public function down(): void
    {
        Schema::table('lists', function (Blueprint $table) {
            if (Schema::hasColumn('lists', 'description')) {
                $table->dropColumn('description');
            }
            if (Schema::hasColumn('lists', 'ai_prompt')) {
                $table->dropColumn('ai_prompt');
            }
        });
    }
};
