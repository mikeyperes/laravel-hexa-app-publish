<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ai_activity_logs') && !Schema::hasColumn('ai_activity_logs', 'request_url')) {
            Schema::table('ai_activity_logs', function (Blueprint $table) {
                $table->string('request_url', 500)->nullable()->after('api_key_masked');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('ai_activity_logs', 'request_url')) {
            Schema::table('ai_activity_logs', function (Blueprint $table) {
                $table->dropColumn('request_url');
            });
        }
    }
};
