<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('publish_campaigns') && !Schema::hasColumn('publish_campaigns', 'drip_interval_minutes')) {
            Schema::table('publish_campaigns', function (Blueprint $table) {
                $table->unsignedInteger('drip_interval_minutes')->default(60)->after('run_at_time')->comment('Minutes between each post in a batch');
            });
        }
        if (Schema::hasTable('campaign_presets') && !Schema::hasColumn('campaign_presets', 'is_default')) {
            Schema::table('campaign_presets', function (Blueprint $table) {
                $table->boolean('is_default')->default(false)->after('is_active');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('publish_campaigns', 'drip_interval_minutes')) {
            Schema::table('publish_campaigns', function (Blueprint $table) { $table->dropColumn('drip_interval_minutes'); });
        }
        if (Schema::hasColumn('campaign_presets', 'is_default')) {
            Schema::table('campaign_presets', function (Blueprint $table) { $table->dropColumn('is_default'); });
        }
    }
};
