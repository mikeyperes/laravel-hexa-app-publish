<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('publish_article_approval_emails')) {
            Schema::create('publish_article_approval_emails', function (Blueprint $table) {
                $table->id();
                $table->foreignId('publish_article_id')->constrained('publish_articles')->cascadeOnDelete();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->unsignedBigInteger('smtp_account_id')->nullable();
                $table->string('context', 80)->default('draft-approval');
                $table->string('status', 40)->default('draft');
                $table->string('image_mode', 40)->default('links');
                $table->json('to_recipients')->nullable();
                $table->json('cc_recipients')->nullable();
                $table->string('from_email')->nullable();
                $table->string('from_name')->nullable();
                $table->string('reply_to')->nullable();
                $table->string('subject');
                $table->longText('body_html')->nullable();
                $table->longText('body_text')->nullable();
                $table->longText('preview_html')->nullable();
                $table->json('headers')->nullable();
                $table->json('diagnostics')->nullable();
                $table->json('snapshot')->nullable();
                $table->text('error')->nullable();
                $table->string('public_token', 80)->unique();
                $table->timestamp('sent_at')->nullable();
                $table->timestamp('viewed_at')->nullable();
                $table->timestamp('reviewed_at')->nullable();
                $table->json('review_payload')->nullable();
                $table->timestamps();

                $table->index(['publish_article_id', 'created_at'], 'paae_article_created_idx');
                $table->index(['publish_article_id', 'status'], 'paae_article_status_idx');
                $table->index('public_token', 'paae_public_token_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('publish_article_approval_emails');
    }
};
