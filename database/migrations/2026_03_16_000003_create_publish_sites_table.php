<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('publish_sites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('publish_account_id')->constrained('publish_accounts')->cascadeOnDelete();
            $table->string('name');
            $table->string('url');
            $table->enum('connection_type', ['wptoolkit', 'wp_rest_api'])->default('wp_rest_api');
            $table->unsignedBigInteger('hosting_account_id')->nullable()->comment('FK to hosting_accounts if connected via cPanel/WP Toolkit');
            $table->unsignedBigInteger('wordpress_install_id')->nullable()->comment('FK to wordpress_installs if detected via WP Toolkit');
            $table->string('wp_username')->nullable()->comment('WordPress REST API username');
            $table->text('wp_application_password')->nullable()->comment('WordPress REST API application password (encrypted)');
            $table->enum('status', ['connected', 'disconnected', 'error'])->default('disconnected');
            $table->text('last_error')->nullable();
            $table->timestamp('last_connected_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('publish_sites');
    }
};
