<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('publish_sites', function (Blueprint $table) {
            if (!Schema::hasColumn('publish_sites', 'author_cast')) {
                $table->json('author_cast')->nullable()->after('default_author');
            }
        });
    }

    public function down(): void
    {
        Schema::table('publish_sites', function (Blueprint $table) {
            if (Schema::hasColumn('publish_sites', 'author_cast')) {
                $table->dropColumn('author_cast');
            }
        });
    }
};
