<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sale_lines', function (Blueprint $table): void {
            $table->unsignedBigInteger('service_id')->nullable()->after('source_id');
            $table->foreign(['service_id', 'business_id'], 'sale_line_service_fk')
                ->references(['id', 'business_id'])->on('services')->restrictOnDelete();
            $table->index(['business_id', 'service_id'], 'sale_line_service_lookup');
        });

        Schema::create('product_categories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->ulid('public_id')->unique();
            $table->string('name');
            $table->string('status', 16)->default('active');
            $table->timestamps();
            $table->unique(['id', 'business_id']);
            $table->unique(['business_id', 'name']);
        });

        Schema::create('inventory_products', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('product_category_id')->nullable();
            $table->ulid('public_id')->unique();
            $table->string('name');
            $table->string('sku', 96);
            $table->string('barcode', 128)->nullable();
            $table->unsignedBigInteger('sale_price_minor');
            $table->unsignedBigInteger('cost_minor')->default(0);
            $table->unsignedInteger('tax_rate_bps')->default(0);
            $table->string('currency_code', 3);
            $table->string('status', 16)->default('active');
            $table->bigInteger('current_stock')->default(0);
            $table->unsignedBigInteger('low_stock_threshold')->default(0);
            $table->timestamps();
            $table->unique(['id', 'business_id']);
            $table->unique(['business_id', 'sku']);
            $table->unique(['business_id', 'barcode']);
            $table->foreign(['product_category_id', 'business_id'], 'inv_product_category_fk')
                ->references(['id', 'business_id'])->on('product_categories')->restrictOnDelete();
            $table->index(['business_id', 'status', 'current_stock'], 'inv_product_stock_lookup');
        });

        Schema::create('inventory_levels', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('location_id');
            $table->unsignedBigInteger('inventory_product_id');
            $table->bigInteger('current_stock')->default(0);
            $table->timestamps();
            $table->unique(['business_id', 'location_id', 'inventory_product_id'], 'inventory_level_unique');
            $table->foreign(['location_id', 'business_id'], 'inv_level_location_fk')->references(['id', 'business_id'])->on('locations')->restrictOnDelete();
            $table->foreign(['inventory_product_id', 'business_id'], 'inv_level_product_fk')->references(['id', 'business_id'])->on('inventory_products')->restrictOnDelete();
            $table->index(['business_id', 'location_id', 'current_stock'], 'inventory_level_stock_lookup');
        });

        Schema::create('inventory_movements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('location_id');
            $table->unsignedBigInteger('inventory_product_id');
            $table->unsignedBigInteger('sale_line_id')->nullable();
            $table->unsignedBigInteger('payment_transaction_id')->nullable();
            $table->unsignedBigInteger('actor_membership_id')->nullable();
            $table->string('type', 24);
            $table->string('disposition', 24)->nullable();
            $table->bigInteger('quantity_delta');
            $table->bigInteger('quantity_before');
            $table->bigInteger('quantity_after');
            $table->string('idempotency_key', 160);
            $table->text('reason')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();
            $table->unique(['business_id', 'idempotency_key'], 'inv_movement_idempotency_unique');
            $table->foreign(['location_id', 'business_id'], 'inv_movement_location_fk')
                ->references(['id', 'business_id'])->on('locations')->restrictOnDelete();
            $table->foreign(['inventory_product_id', 'business_id'], 'inv_movement_product_fk')
                ->references(['id', 'business_id'])->on('inventory_products')->restrictOnDelete();
            $table->foreign('sale_line_id')->references('id')->on('sale_lines')->restrictOnDelete();
            $table->foreign('payment_transaction_id')->references('id')->on('payment_transactions')->restrictOnDelete();
            $table->foreign(['actor_membership_id', 'business_id'], 'inv_movement_actor_fk')->references(['id', 'business_id'])->on('memberships')->restrictOnDelete();
            $table->index(['business_id', 'inventory_product_id', 'occurred_at'], 'inv_movement_history_lookup');
        });

        Schema::create('commission_rules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('staff_profile_id')->nullable();
            $table->unsignedBigInteger('service_id')->nullable();
            $table->ulid('public_id')->unique();
            $table->string('kind', 32);
            $table->unsignedInteger('rate_bps')->nullable();
            $table->unsignedBigInteger('amount_minor')->nullable();
            $table->string('currency_code', 3);
            $table->dateTime('effective_from');
            $table->dateTime('effective_to')->nullable();
            $table->unsignedBigInteger('created_by_membership_id')->nullable();
            $table->text('reason')->nullable();
            $table->timestamps();
            $table->unique(['id', 'business_id']);
            $table->index(['business_id', 'kind', 'staff_profile_id', 'effective_from'], 'commission_rule_lookup');
            $table->foreign(['staff_profile_id', 'business_id'], 'commission_rule_staff_fk')->references(['id', 'business_id'])->on('staff_profiles')->restrictOnDelete();
            $table->foreign(['service_id', 'business_id'], 'commission_rule_service_fk')->references(['id', 'business_id'])->on('services')->restrictOnDelete();
        });

        Schema::create('commission_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('staff_profile_id');
            $table->unsignedBigInteger('sale_line_id')->nullable();
            $table->unsignedBigInteger('commission_rule_id')->nullable();
            $table->unsignedBigInteger('payment_transaction_id')->nullable();
            $table->unsignedBigInteger('approved_by_membership_id')->nullable();
            $table->string('type', 24);
            $table->bigInteger('base_minor')->default(0);
            $table->unsignedInteger('rate_bps')->nullable();
            $table->bigInteger('amount_minor');
            $table->string('currency_code', 3);
            $table->string('idempotency_key', 160);
            $table->text('reason')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();
            $table->unique(['business_id', 'idempotency_key'], 'commission_entry_idempotency_unique');
            $table->foreign('sale_line_id')->references('id')->on('sale_lines')->restrictOnDelete();
            $table->foreign('commission_rule_id')->references('id')->on('commission_rules')->restrictOnDelete();
            $table->foreign('payment_transaction_id')->references('id')->on('payment_transactions')->restrictOnDelete();
            $table->foreign(['staff_profile_id', 'business_id'], 'commission_entry_staff_fk')->references(['id', 'business_id'])->on('staff_profiles')->restrictOnDelete();
            $table->index(['business_id', 'staff_profile_id', 'occurred_at'], 'commission_statement_lookup');
        });

        Schema::create('tip_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('sale_id')->nullable();
            $table->unsignedBigInteger('staff_profile_id');
            $table->unsignedBigInteger('payment_transaction_id')->nullable();
            $table->unsignedBigInteger('approved_by_membership_id')->nullable();
            $table->string('type', 24);
            $table->bigInteger('amount_minor');
            $table->string('currency_code', 3);
            $table->string('idempotency_key', 160);
            $table->text('reason')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();
            $table->unique(['business_id', 'idempotency_key'], 'tip_entry_idempotency_unique');
            $table->foreign(['sale_id', 'business_id'], 'tip_entry_sale_fk')->references(['id', 'business_id'])->on('sales')->restrictOnDelete();
            $table->foreign('payment_transaction_id')->references('id')->on('payment_transactions')->restrictOnDelete();
            $table->foreign(['staff_profile_id', 'business_id'], 'tip_entry_staff_fk')->references(['id', 'business_id'])->on('staff_profiles')->restrictOnDelete();
            $table->index(['business_id', 'staff_profile_id', 'occurred_at'], 'tip_statement_lookup');
        });

        Schema::create('sale_line_refunds', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('sale_line_id');
            $table->unsignedBigInteger('payment_transaction_id');
            $table->unsignedBigInteger('amount_minor');
            $table->unsignedInteger('quantity')->default(0);
            $table->string('disposition', 24);
            $table->text('reason');
            $table->timestamp('occurred_at');
            $table->timestamps();
            $table->unique(['payment_transaction_id', 'sale_line_id'], 'sale_line_refund_unique');
            $table->foreign('sale_line_id')->references('id')->on('sale_lines')->restrictOnDelete();
            $table->foreign('payment_transaction_id')->references('id')->on('payment_transactions')->restrictOnDelete();
        });

        Schema::create('report_exports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('requested_by_membership_id');
            $table->ulid('public_id')->unique();
            $table->string('report_key', 48);
            $table->string('format', 16)->default('csv');
            $table->json('filters');
            $table->json('scope_snapshot');
            $table->string('status', 24)->default('queued');
            $table->string('storage_path')->nullable();
            $table->string('content_hash', 64)->nullable();
            $table->unsignedBigInteger('row_count')->default(0);
            $table->json('totals')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->unique(['id', 'business_id']);
            $table->index(['business_id', 'status', 'created_at'], 'report_export_queue_lookup');
            $table->foreign(['requested_by_membership_id', 'business_id'], 'report_export_requester_fk')->references(['id', 'business_id'])->on('memberships')->restrictOnDelete();
        });

        Schema::create('instrumentation_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event_name', 96);
            $table->string('metric_version', 24);
            $table->string('idempotency_key', 160);
            $table->string('subject_hash', 64)->nullable();
            $table->json('dimensions');
            $table->timestamp('occurred_at');
            $table->timestamps();
            $table->unique(['event_name', 'idempotency_key'], 'instrumentation_event_unique');
            $table->index(['business_id', 'event_name', 'occurred_at'], 'instrumentation_metric_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('instrumentation_events');
        Schema::dropIfExists('report_exports');
        Schema::dropIfExists('sale_line_refunds');
        Schema::dropIfExists('tip_entries');
        Schema::dropIfExists('commission_entries');
        Schema::dropIfExists('commission_rules');
        Schema::dropIfExists('inventory_movements');
        Schema::dropIfExists('inventory_levels');
        Schema::dropIfExists('inventory_products');
        Schema::dropIfExists('product_categories');

        Schema::table('sale_lines', function (Blueprint $table): void {
            $table->dropForeign('sale_line_service_fk');
            $table->dropIndex('sale_line_service_lookup');
            $table->dropColumn('service_id');
        });
    }
};
