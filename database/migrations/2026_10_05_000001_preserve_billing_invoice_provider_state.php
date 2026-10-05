<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('business_subscriptions', function (Blueprint $table): void {
            $table->foreignId('scheduled_billing_plan_price_id')->nullable()->constrained('billing_plan_prices');
            $table->timestamp('scheduled_change_at')->nullable();
            $table->timestamp('scheduled_provider_state_at')->nullable();
            $table->timestamp('billing_checked_at')->nullable();
            $table->timestamp('account_checked_at')->nullable();
            $table->unsignedSmallInteger('payment_method_expiry_month')->nullable();
            $table->unsignedSmallInteger('payment_method_expiry_year')->nullable();
            $table->string('billing_name')->nullable();
            $table->string('billing_email')->nullable();
            $table->bigInteger('customer_balance_minor')->nullable();
            $table->string('balance_currency', 3)->nullable();
        });
        Schema::table('billing_invoices', function (Blueprint $table): void {
            $table->timestamp('provider_state_at')->nullable();
            $table->unsignedBigInteger('amount_remaining_minor')->nullable();
            $table->timestamp('last_payment_failed_at')->nullable();
            $table->timestamp('next_payment_attempt_at')->nullable();
            $table->index(['business_subscription_id', 'issued_at', 'id'], 'billing_invoice_history_idx');
        });
        DB::table('billing_invoices')->update([
            'last_payment_failed_at' => DB::raw("(SELECT MAX(attempted_at) FROM billing_payments WHERE billing_payments.billing_invoice_id = billing_invoices.id AND billing_payments.status = 'failed')"),
        ]);
    }

    public function down(): void
    {
        Schema::table('business_subscriptions', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('scheduled_billing_plan_price_id');
            $table->dropColumn(['scheduled_change_at', 'scheduled_provider_state_at', 'billing_checked_at', 'account_checked_at', 'payment_method_expiry_month', 'payment_method_expiry_year', 'billing_name', 'billing_email', 'customer_balance_minor', 'balance_currency']);
        });
        Schema::table('billing_invoices', function (Blueprint $table): void {
            $table->dropIndex('billing_invoice_history_idx');
            $table->dropColumn(['provider_state_at', 'amount_remaining_minor', 'last_payment_failed_at', 'next_payment_attempt_at']);
        });
    }
};
