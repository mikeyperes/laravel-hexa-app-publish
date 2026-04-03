<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('campaign_presets') && !Schema::hasColumn('campaign_presets', 'ai_instructions')) {
            Schema::table('campaign_presets', function (Blueprint $table) {
                $table->text('ai_instructions')->nullable()->after('auto_select_sources');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('campaign_presets', 'ai_instructions')) {
            Schema::table('campaign_presets', function (Blueprint $table) {
                $table->dropColumn('ai_instructions');
            });
        }
    }
};
