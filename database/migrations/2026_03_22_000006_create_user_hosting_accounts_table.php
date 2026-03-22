<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('user_hosting_accounts')) {
            Schema::create('user_hosting_accounts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->unsignedBigInteger('hosting_account_id');
                $table->timestamps();

                $table->unique(['user_id', 'hosting_account_id']);
                $table->index('hosting_account_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('user_hosting_accounts');
    }
};
