<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_checkout_attempts', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('business_id')->constrained()->restrictOnDelete();
            $table->foreignId('business_subscription_id')->constrained()->restrictOnDelete();
            $table->foreignId('billing_plan_price_id')->constrained()->restrictOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('provider', 32);
            $table->string('provider_transaction_id')->unique();
            $table->string('provider_subscription_id')->nullable()->index();
            $table->string('status', 24)->default('pending')->index();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
            $table->index(['business_id', 'status', 'created_at'], 'billing_checkout_recovery_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_checkout_attempts');
    }
};
