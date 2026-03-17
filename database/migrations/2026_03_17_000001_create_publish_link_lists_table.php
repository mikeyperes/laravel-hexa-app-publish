<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('publish_link_lists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('publish_account_id')->constrained('publish_accounts')->cascadeOnDelete();
            $table->string('name');
            $table->enum('type', ['backlink', 'internal', 'sitemap'])->default('backlink');
            $table->text('url');
            $table->string('anchor_text')->nullable()->comment('Preferred anchor text for this link');
            $table->text('context')->nullable()->comment('AI hint: when/where to place this link');
            $table->unsignedInteger('priority')->default(0)->comment('Higher = more likely to be used');
            $table->unsignedInteger('times_used')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('publish_link_lists');
    }
};
