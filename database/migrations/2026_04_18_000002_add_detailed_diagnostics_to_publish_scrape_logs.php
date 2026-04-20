<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('publish_scrape_logs')) {
            return;
        }

        Schema::table('publish_scrape_logs', function (Blueprint $table) {
            if (!Schema::hasColumn('publish_scrape_logs', 'http_method')) {
                $table->string('http_method', 12)->nullable()->after('method');
            }
            if (!Schema::hasColumn('publish_scrape_logs', 'effective_url')) {
                $table->text('effective_url')->nullable()->after('url');
            }
            if (!Schema::hasColumn('publish_scrape_logs', 'request_headers')) {
                $table->json('request_headers')->nullable()->after('user_agent');
            }
            if (!Schema::hasColumn('publish_scrape_logs', 'request_meta')) {
                $table->json('request_meta')->nullable()->after('request_headers');
            }
            if (!Schema::hasColumn('publish_scrape_logs', 'response_headers')) {
                $table->json('response_headers')->nullable()->after('retries');
            }
            if (!Schema::hasColumn('publish_scrape_logs', 'response_reason')) {
                $table->string('response_reason')->nullable()->after('http_status');
            }
            if (!Schema::hasColumn('publish_scrape_logs', 'response_meta')) {
                $table->json('response_meta')->nullable()->after('response_headers');
            }
            if (!Schema::hasColumn('publish_scrape_logs', 'attempt_log')) {
                $table->json('attempt_log')->nullable()->after('response_meta');
            }
            if (!Schema::hasColumn('publish_scrape_logs', 'fetch_info')) {
                $table->json('fetch_info')->nullable()->after('attempt_log');
            }
            if (!Schema::hasColumn('publish_scrape_logs', 'response_body_snippet')) {
                $table->longText('response_body_snippet')->nullable()->after('error_message');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('publish_scrape_logs')) {
            return;
        }

        Schema::table('publish_scrape_logs', function (Blueprint $table) {
            foreach ([
                'http_method',
                'effective_url',
                'request_headers',
                'request_meta',
                'response_headers',
                'response_reason',
                'response_meta',
                'attempt_log',
                'fetch_info',
                'response_body_snippet',
            ] as $column) {
                if (Schema::hasColumn('publish_scrape_logs', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
