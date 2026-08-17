<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commerce_settings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->string('currency_code', 3);
            $table->boolean('tax_inclusive')->default(true);
            $table->unsignedInteger('default_tax_rate_bps')->default(0);
            $table->string('default_deposit_type', 16)->default('none');
            $table->unsignedBigInteger('default_deposit_value')->default(0);
            $table->boolean('deposit_new_clients_only')->default(false);
            $table->unsignedBigInteger('deposit_threshold_minor')->default(0);
            $table->unsignedSmallInteger('deposit_prior_no_show_count')->default(0);
            $table->unsignedInteger('cancellation_cutoff_minutes')->default(1440);
            $table->boolean('deposit_refundable_before_cutoff')->default(true);
            $table->unsignedBigInteger('cancellation_fee_minor')->default(0);
            $table->unsignedBigInteger('no_show_fee_minor')->default(0);
            $table->unsignedInteger('discount_manager_limit_bps')->default(2000);
            $table->timestamps();
            $table->unique('business_id');
        });

        Schema::create('payment_intents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('appointment_id')->nullable();
            $table->unsignedBigInteger('capacity_hold_id')->nullable();
            $table->unsignedBigInteger('client_id')->nullable();
            $table->ulid('public_id')->unique();
            $table->string('purpose', 24);
            $table->string('provider', 32);
            $table->string('provider_intent_id', 191)->nullable();
            $table->string('idempotency_key', 128);
            $table->string('request_hash', 64);
            $table->string('status', 24)->default('pending');
            $table->unsignedBigInteger('amount_minor');
            $table->string('currency_code', 3);
            $table->timestamp('provider_state_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->json('source_snapshot');
            $table->text('pending_booking_payload')->nullable();
            $table->json('provider_evidence')->nullable();
            $table->timestamps();
            $table->unique(['business_id', 'idempotency_key'], 'payment_intent_idempotency_unique');
            $table->unique(['provider', 'provider_intent_id'], 'payment_intent_provider_unique');
            $table->foreign(['appointment_id', 'business_id'], 'payment_intent_appointment_fk')->references(['id', 'business_id'])->on('appointments')->restrictOnDelete();
            $table->foreign(['capacity_hold_id', 'business_id'], 'payment_intent_hold_fk')->references(['id', 'business_id'])->on('capacity_holds')->restrictOnDelete();
            $table->index(['business_id', 'status', 'expires_at'], 'payment_intent_recovery_lookup');
        });

        Schema::create('payment_provider_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->nullable()->constrained()->nullOnDelete();
            $table->string('provider', 32);
            $table->string('provider_event_id', 191);
            $table->string('payload_hash', 64);
            $table->boolean('signature_verified');
            $table->timestamp('provider_created_at')->nullable();
            $table->string('event_type', 96);
            $table->string('processing_status', 24)->default('pending');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->json('payload');
            $table->text('error')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
            $table->unique(['provider', 'provider_event_id'], 'payment_provider_event_unique');
        });

        Schema::create('sales', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('location_id');
            $table->unsignedBigInteger('appointment_id')->nullable();
            $table->unsignedBigInteger('client_id')->nullable();
            $table->ulid('public_id')->unique();
            $table->string('status', 24)->default('open');
            $table->string('currency_code', 3);
            $table->unsignedBigInteger('subtotal_minor')->default(0);
            $table->unsignedBigInteger('discount_minor')->default(0);
            $table->unsignedBigInteger('tax_minor')->default(0);
            $table->unsignedBigInteger('tip_minor')->default(0);
            $table->unsignedBigInteger('total_minor')->default(0);
            $table->unsignedBigInteger('deposit_applied_minor')->default(0);
            $table->unsignedBigInteger('paid_minor')->default(0);
            $table->unsignedBigInteger('refunded_minor')->default(0);
            $table->unsignedBigInteger('balance_minor')->default(0);
            $table->json('calculation_snapshot');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->unique(['id', 'business_id']);
            $table->unique(['appointment_id', 'business_id'], 'sale_appointment_unique');
            $table->foreign(['location_id', 'business_id'], 'sales_location_fk')->references(['id', 'business_id'])->on('locations')->restrictOnDelete();
            $table->foreign(['appointment_id', 'business_id'], 'sales_appointment_fk')->references(['id', 'business_id'])->on('appointments')->restrictOnDelete();
            $table->index(['business_id', 'location_id', 'status', 'completed_at'], 'sales_close_lookup');
        });

        Schema::create('sale_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('sale_id');
            $table->string('kind', 16);
            $table->string('source_type', 96)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->unsignedBigInteger('staff_profile_id')->nullable();
            $table->unsignedSmallInteger('sequence');
            $table->string('description');
            $table->unsignedInteger('quantity')->default(1);
            $table->unsignedBigInteger('unit_price_minor');
            $table->unsignedInteger('tax_rate_bps')->default(0);
            $table->unsignedBigInteger('discount_minor')->default(0);
            $table->json('source_snapshot');
            $table->timestamps();
            $table->unique(['sale_id', 'sequence']);
            $table->foreign(['sale_id', 'business_id'], 'sale_lines_sale_fk')->references(['id', 'business_id'])->on('sales')->cascadeOnDelete();
        });

        Schema::create('payment_transactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('sale_id')->nullable();
            $table->unsignedBigInteger('appointment_id')->nullable();
            $table->unsignedBigInteger('payment_intent_id')->nullable();
            $table->unsignedBigInteger('parent_transaction_id')->nullable();
            $table->ulid('public_id')->unique();
            $table->string('kind', 24);
            $table->string('status', 24)->default('succeeded');
            $table->string('method', 32);
            $table->string('provider', 32)->nullable();
            $table->string('provider_reference', 191)->nullable();
            $table->string('idempotency_key', 128);
            $table->unsignedBigInteger('amount_minor');
            $table->string('currency_code', 3);
            $table->json('evidence')->nullable();
            $table->text('reason')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();
            $table->unique(['business_id', 'idempotency_key'], 'payment_transaction_idempotency_unique');
            $table->unique(['provider', 'provider_reference'], 'payment_transaction_provider_unique');
            $table->foreign(['sale_id', 'business_id'], 'payment_transaction_sale_fk')->references(['id', 'business_id'])->on('sales')->restrictOnDelete();
            $table->foreign(['appointment_id', 'business_id'], 'payment_transaction_appointment_fk')->references(['id', 'business_id'])->on('appointments')->restrictOnDelete();
            $table->foreign('payment_intent_id')->references('id')->on('payment_intents')->restrictOnDelete();
            $table->foreign('parent_transaction_id')->references('id')->on('payment_transactions')->restrictOnDelete();
            $table->index(['business_id', 'method', 'occurred_at'], 'payment_method_summary_lookup');
        });

        Schema::create('deposits', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('appointment_id');
            $table->unsignedBigInteger('client_id')->nullable();
            $table->unsignedBigInteger('payment_transaction_id');
            $table->unsignedBigInteger('original_amount_minor');
            $table->unsignedBigInteger('applied_minor')->default(0);
            $table->unsignedBigInteger('refunded_minor')->default(0);
            $table->unsignedBigInteger('forfeited_minor')->default(0);
            $table->unsignedBigInteger('credited_minor')->default(0);
            $table->string('currency_code', 3);
            $table->string('status', 24)->default('bound');
            $table->json('policy_snapshot');
            $table->timestamps();
            $table->unique('payment_transaction_id');
            $table->foreign(['appointment_id', 'business_id'], 'deposits_appointment_fk')->references(['id', 'business_id'])->on('appointments')->restrictOnDelete();
            $table->foreign('payment_transaction_id')->references('id')->on('payment_transactions')->restrictOnDelete();
        });

        Schema::create('deposit_allocations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('deposit_id');
            $table->unsignedBigInteger('appointment_id')->nullable();
            $table->unsignedBigInteger('sale_id')->nullable();
            $table->unsignedBigInteger('payment_transaction_id')->nullable();
            $table->string('action', 24);
            $table->unsignedBigInteger('amount_minor');
            $table->string('idempotency_key', 128);
            $table->text('reason')->nullable();
            $table->json('evidence')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();
            $table->unique(['business_id', 'idempotency_key'], 'deposit_allocation_idempotency_unique');
            $table->foreign('deposit_id')->references('id')->on('deposits')->restrictOnDelete();
            $table->foreign(['sale_id', 'business_id'], 'deposit_allocation_sale_fk')->references(['id', 'business_id'])->on('sales')->restrictOnDelete();
        });

        Schema::create('sale_tip_allocations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('sale_id');
            $table->unsignedBigInteger('staff_profile_id')->nullable();
            $table->unsignedBigInteger('amount_minor');
            $table->timestamps();
            $table->foreign(['sale_id', 'business_id'], 'sale_tip_sale_fk')->references(['id', 'business_id'])->on('sales')->cascadeOnDelete();
        });

        Schema::create('sale_receipts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('sale_id');
            $table->string('receipt_number', 64);
            $table->string('content_hash', 64);
            $table->json('snapshot');
            $table->timestamp('issued_at');
            $table->timestamps();
            $table->unique(['business_id', 'receipt_number']);
            $table->unique('sale_id');
            $table->foreign(['sale_id', 'business_id'], 'sale_receipt_sale_fk')->references(['id', 'business_id'])->on('sales')->restrictOnDelete();
        });

        Schema::create('cash_closes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('location_id');
            $table->date('business_date');
            $table->string('currency_code', 3);
            $table->unsignedBigInteger('opening_cash_minor');
            $table->unsignedBigInteger('expected_cash_minor');
            $table->unsignedBigInteger('actual_cash_minor');
            $table->bigInteger('variance_minor');
            $table->text('variance_reason')->nullable();
            $table->json('method_summary');
            $table->unsignedBigInteger('outstanding_balance_minor')->default(0);
            $table->unsignedBigInteger('closed_by_membership_id')->nullable();
            $table->timestamp('closed_at');
            $table->timestamps();
            $table->unique(['business_id', 'location_id', 'business_date']);
            $table->foreign(['location_id', 'business_id'], 'cash_close_location_fk')->references(['id', 'business_id'])->on('locations')->restrictOnDelete();
        });

        Schema::create('cash_close_adjustments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('cash_close_id');
            $table->bigInteger('amount_minor');
            $table->text('reason');
            $table->unsignedBigInteger('approved_by_membership_id');
            $table->timestamp('occurred_at');
            $table->timestamps();
            $table->foreign('cash_close_id')->references('id')->on('cash_closes')->restrictOnDelete();
        });

        Schema::create('payment_reconciliation_tasks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('payment_intent_id')->nullable();
            $table->string('kind', 48);
            $table->string('status', 24)->default('open');
            $table->json('evidence');
            $table->text('resolution')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            $table->unique(['payment_intent_id', 'kind'], 'payment_reconciliation_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_reconciliation_tasks');
        Schema::dropIfExists('cash_close_adjustments');
        Schema::dropIfExists('cash_closes');
        Schema::dropIfExists('sale_receipts');
        Schema::dropIfExists('sale_tip_allocations');
        Schema::dropIfExists('deposit_allocations');
        Schema::dropIfExists('deposits');
        Schema::dropIfExists('payment_transactions');
        Schema::dropIfExists('sale_lines');
        Schema::dropIfExists('sales');
        Schema::dropIfExists('payment_provider_events');
        Schema::dropIfExists('payment_intents');
        Schema::dropIfExists('commerce_settings');
    }
};
