<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('publish_presets', 'is_default')) {
            Schema::table('publish_presets', function (Blueprint $table) {
                $table->boolean('is_default')->default(false)->after('name');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('publish_presets', 'is_default')) {
            Schema::table('publish_presets', function (Blueprint $table) {
                $table->dropColumn('is_default');
            });
        }
    }
};
