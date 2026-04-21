<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campaign_presets', function (Blueprint $table) {
            if (!Schema::hasColumn('campaign_presets', 'search_queries')) {
                $table->json('search_queries')->nullable()->after('keywords');
            }
            if (!Schema::hasColumn('campaign_presets', 'campaign_instructions')) {
                $table->text('campaign_instructions')->nullable()->after('search_queries');
            }
            if (!Schema::hasColumn('campaign_presets', 'posts_per_run')) {
                $table->unsignedInteger('posts_per_run')->default(1)->after('campaign_instructions');
            }
            if (!Schema::hasColumn('campaign_presets', 'frequency')) {
                $table->string('frequency', 20)->default('daily')->after('posts_per_run');
            }
            if (!Schema::hasColumn('campaign_presets', 'run_at_time')) {
                $table->string('run_at_time', 10)->nullable()->after('frequency');
            }
            if (!Schema::hasColumn('campaign_presets', 'drip_minutes')) {
                $table->unsignedInteger('drip_minutes')->default(60)->after('run_at_time');
            }
        });

        DB::table('campaign_presets')
            ->whereNull('search_queries')
            ->orWhereNull('campaign_instructions')
            ->orderBy('id')
            ->chunkById(100, function ($rows): void {
                foreach ($rows as $row) {
                    $updates = [];

                    if ($row->search_queries === null && $row->keywords !== null) {
                        $updates['search_queries'] = $row->keywords;
                    }

                    if ($row->campaign_instructions === null && $row->ai_instructions !== null) {
                        $updates['campaign_instructions'] = $row->ai_instructions;
                    }

                    if (!empty($updates)) {
                        DB::table('campaign_presets')->where('id', $row->id)->update($updates);
                    }
                }
            });
    }

    public function down(): void
    {
        Schema::table('campaign_presets', function (Blueprint $table) {
            foreach ([
                'drip_minutes',
                'run_at_time',
                'frequency',
                'posts_per_run',
                'campaign_instructions',
                'search_queries',
            ] as $column) {
                if (Schema::hasColumn('campaign_presets', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
