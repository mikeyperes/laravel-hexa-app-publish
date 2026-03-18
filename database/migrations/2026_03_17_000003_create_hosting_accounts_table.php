<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('hosting_accounts')) {
            return;
        }

        Schema::create('hosting_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('whm_server_id')->constrained('whm_servers')->cascadeOnDelete();
            $table->string('username');
            $table->string('domain');
            $table->string('owner')->default('root');
            $table->string('email')->nullable();
            $table->string('package')->nullable();
            $table->enum('status', ['active', 'suspended', 'removed'])->default('active');
            $table->string('suspend_reason')->nullable();
            $table->string('ip_address')->nullable();
            $table->unsignedBigInteger('disk_used_mb')->default(0);
            $table->unsignedBigInteger('disk_limit_mb')->default(0);
            $table->unsignedBigInteger('bandwidth_used_mb')->default(0);
            $table->boolean('shell_access')->default(false);
            $table->string('theme')->nullable();
            $table->timestamp('server_created_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['whm_server_id', 'username']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hosting_accounts');
    }
};
