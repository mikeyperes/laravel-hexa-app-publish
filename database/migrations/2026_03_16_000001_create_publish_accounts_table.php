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
        Schema::create('publish_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('account_id')->unique()->comment('Auto-generated: PUB-YYYYMMDD-NNN');
            $table->string('email')->nullable();
            $table->enum('status', ['active', 'suspended', 'canceled'])->default('active');
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('stripe_customer_id')->nullable();
            $table->string('stripe_subscription_id')->nullable();
            $table->string('plan')->nullable()->comment('Subscription plan name');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('publish_accounts');
    }
};
