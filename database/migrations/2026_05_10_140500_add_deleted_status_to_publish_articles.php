<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            ALTER TABLE publish_articles
            MODIFY COLUMN status ENUM(
                'sourcing',
                'drafting',
                'spinning',
                'review',
                'ai-check',
                'ready',
                'published',
                'failed',
                'completed',
                'deleted'
            ) NOT NULL DEFAULT 'sourcing'
        ");
    }

    public function down(): void
    {
        DB::statement("
            ALTER TABLE publish_articles
            MODIFY COLUMN status ENUM(
                'sourcing',
                'drafting',
                'spinning',
                'review',
                'ai-check',
                'ready',
                'published',
                'failed',
                'completed'
            ) NOT NULL DEFAULT 'sourcing'
        ");
    }
};
