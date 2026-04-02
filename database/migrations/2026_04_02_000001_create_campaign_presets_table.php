<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('campaign_presets')) {
            Schema::create('campaign_presets', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->string('name');
                $table->json('keywords')->nullable();
                $table->string('local_preference')->nullable()->comment('City or state name for local news');
                $table->enum('source_method', ['trending', 'genre', 'local'])->default('trending')->comment('Default source method');
                $table->string('genre')->nullable()->comment('News category/genre from lists table');
                $table->json('trending_categories')->nullable()->comment('Selected trending categories');
                $table->boolean('auto_select_sources')->default(false)->comment('System auto-picks sources');
                $table->boolean('is_active')->default(true);
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_presets');
    }
};
