<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('publish_sites') && !Schema::hasColumn('publish_sites', 'default_author')) {
            Schema::table('publish_sites', function (Blueprint $table) {
                $table->string('default_author')->nullable()->after('wp_application_password');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('publish_sites', 'default_author')) {
            Schema::table('publish_sites', function (Blueprint $table) {
                $table->dropColumn('default_author');
            });
        }
    }
};
