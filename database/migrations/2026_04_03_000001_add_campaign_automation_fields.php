<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('publish_campaigns', function (Blueprint $table) {
            if (!Schema::hasColumn('publish_campaigns', 'user_id')) {
                $table->unsignedBigInteger('user_id')->nullable()->after('id')->comment('Assigned user');
            }
            if (!Schema::hasColumn('publish_campaigns', 'campaign_preset_id')) {
                $table->unsignedBigInteger('campaign_preset_id')->nullable()->after('publish_template_id');
            }
            if (!Schema::hasColumn('publish_campaigns', 'preset_id')) {
                $table->unsignedBigInteger('preset_id')->nullable()->after('campaign_preset_id')->comment('WP preset');
            }
            if (!Schema::hasColumn('publish_campaigns', 'auto_publish')) {
                $table->boolean('auto_publish')->default(false)->after('delivery_mode')->comment('Full automation — no manual steps');
            }
            if (!Schema::hasColumn('publish_campaigns', 'author')) {
                $table->string('author')->nullable()->after('auto_publish');
            }
            if (!Schema::hasColumn('publish_campaigns', 'post_status')) {
                $table->enum('post_status', ['publish', 'draft', 'pending'])->default('draft')->after('author');
            }
            if (!Schema::hasColumn('publish_campaigns', 'timezone')) {
                $table->string('timezone', 50)->default('America/New_York')->after('interval_unit');
            }
            if (!Schema::hasColumn('publish_campaigns', 'run_at_time')) {
                $table->string('run_at_time', 10)->nullable()->after('timezone')->comment('HH:MM in user timezone');
            }
        });
    }

    public function down(): void
    {
        Schema::table('publish_campaigns', function (Blueprint $table) {
            $cols = ['user_id', 'campaign_preset_id', 'preset_id', 'auto_publish', 'author', 'post_status', 'timezone', 'run_at_time'];
            foreach ($cols as $col) {
                if (Schema::hasColumn('publish_campaigns', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
