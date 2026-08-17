<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $this->createTableIfMissing('billing_plans', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('code', 64)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->boolean('is_trial_default')->default(false);
            $table->timestamp('available_from')->nullable();
            $table->timestamp('available_until')->nullable();
            $table->timestamps();
        });

        $this->createTableIfMissing('billing_plan_prices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('billing_plan_id')->constrained()->restrictOnDelete();
            $table->string('billing_interval', 16);
            $table->char('currency', 3);
            $table->unsignedBigInteger('amount_minor');
            $table->string('provider', 32)->default('stripe');
            $table->string('provider_price_id')->unique();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamp('effective_from');
            $table->timestamp('effective_until')->nullable();
            $table->timestamps();
            $table->unique(['billing_plan_id', 'billing_interval', 'currency', 'effective_from'], 'plan_price_effective_unique');
        });

        $this->createTableIfMissing('entitlement_definitions', function (Blueprint $table): void {
            $table->id();
            $table->string('key', 96)->unique();
            $table->string('value_type', 16);
            $table->string('unit', 32)->nullable();
            $table->string('name');
            $table->text('description');
            $table->timestamps();
        });

        $this->createTableIfMissing('billing_plan_entitlements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('billing_plan_id')->constrained()->restrictOnDelete();
            $table->foreignId('entitlement_definition_id')->constrained()->restrictOnDelete();
            $table->json('value');
            $table->timestamp('effective_from');
            $table->timestamp('effective_until')->nullable();
            $table->foreignId('changed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('change_reason');
            $table->timestamps();
            $table->index(['billing_plan_id', 'entitlement_definition_id', 'effective_from'], 'plan_entitlement_effective_lookup');
        });

        $this->createTableIfMissing('business_entitlement_overrides', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->restrictOnDelete();
            $table->foreignId('entitlement_definition_id')->constrained()->restrictOnDelete();
            $table->json('value');
            $table->timestamp('effective_from');
            $table->timestamp('effective_until')->nullable();
            $table->foreignId('changed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('change_reason');
            $table->timestamps();
            $table->index(['business_id', 'entitlement_definition_id', 'effective_from'], 'business_entitlement_effective_lookup');
        });

        $this->createTableIfMissing('business_subscriptions', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('business_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('billing_plan_id')->constrained()->restrictOnDelete();
            $table->foreignId('billing_plan_price_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('provider', 32)->default('stripe');
            $table->string('provider_customer_id')->nullable()->unique();
            $table->string('provider_subscription_id')->nullable()->unique();
            $table->string('status', 32)->index();
            $table->string('restriction_level', 24)->default('none')->index();
            $table->string('billing_interval', 16)->nullable();
            $table->timestamp('trial_started_at')->nullable();
            $table->timestamp('trial_ends_at')->nullable()->index();
            $table->timestamp('current_period_started_at')->nullable();
            $table->timestamp('current_period_ends_at')->nullable()->index();
            $table->timestamp('grace_ends_at')->nullable()->index();
            $table->timestamp('cancel_at')->nullable();
            $table->timestamp('canceled_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->timestamp('export_available_until')->nullable()->index();
            $table->string('payment_method_type', 32)->nullable();
            $table->string('payment_method_last_four', 4)->nullable();
            $table->timestamp('provider_state_at')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
            $table->index(['business_id', 'status', 'restriction_level'], 'business_subscription_access_lookup');
        });

        $this->createTableIfMissing('subscription_changes', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('business_id')->constrained()->restrictOnDelete();
            $table->foreignId('business_subscription_id')->constrained()->restrictOnDelete();
            $table->string('kind', 32);
            $table->foreignId('from_billing_plan_id')->nullable()->constrained('billing_plans')->restrictOnDelete();
            $table->foreignId('to_billing_plan_id')->nullable()->constrained('billing_plans')->restrictOnDelete();
            $table->timestamp('requested_at');
            $table->timestamp('effective_at');
            $table->timestamp('applied_at')->nullable();
            $table->timestamp('superseded_at')->nullable();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reason');
            $table->json('usage_snapshot')->nullable();
            $table->json('limit_snapshot')->nullable();
            $table->timestamps();
            $table->index(['business_subscription_id', 'effective_at', 'applied_at'], 'subscription_change_effective_lookup');
        });

        $this->createTableIfMissing('billing_invoices', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('business_id')->constrained()->restrictOnDelete();
            $table->foreignId('business_subscription_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('provider', 32)->default('stripe');
            $table->string('provider_invoice_id')->unique();
            $table->string('number')->nullable();
            $table->string('status', 24)->index();
            $table->char('currency', 3);
            $table->unsignedBigInteger('subtotal_minor')->default(0);
            $table->unsignedBigInteger('discount_minor')->default(0);
            $table->unsignedBigInteger('tax_minor')->default(0);
            $table->unsignedBigInteger('total_minor')->default(0);
            $table->unsignedBigInteger('amount_due_minor')->default(0);
            $table->unsignedBigInteger('amount_paid_minor')->default(0);
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('due_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->string('hosted_url', 2048)->nullable();
            $table->string('pdf_url', 2048)->nullable();
            $table->json('line_items')->nullable();
            $table->timestamps();
            $table->index(['business_id', 'issued_at']);
        });

        $this->createTableIfMissing('billing_payments', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('business_id')->constrained()->restrictOnDelete();
            $table->foreignId('billing_invoice_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('provider', 32)->default('stripe');
            $table->string('provider_payment_id')->unique();
            $table->string('status', 24)->index();
            $table->char('currency', 3);
            $table->unsignedBigInteger('amount_minor');
            $table->string('failure_code')->nullable();
            $table->text('failure_message')->nullable();
            $table->timestamp('attempted_at');
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
            $table->index(['business_id', 'attempted_at']);
        });

        $this->createTableIfMissing('billing_coupons', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('code', 64)->unique();
            $table->string('provider', 32)->default('stripe');
            $table->string('provider_coupon_id')->nullable()->unique();
            $table->string('discount_type', 16);
            $table->unsignedInteger('discount_value');
            $table->unsignedInteger('duration_months')->nullable();
            $table->unsignedInteger('maximum_redemptions')->nullable();
            $table->unsignedInteger('redemptions_count')->default(0);
            $table->timestamp('valid_from');
            $table->timestamp('valid_until')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        $this->createTableIfMissing('billing_coupon_redemptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('billing_coupon_id')->constrained()->restrictOnDelete();
            $table->foreignId('business_id')->constrained()->restrictOnDelete();
            $table->foreignId('business_subscription_id')->constrained()->restrictOnDelete();
            $table->foreignId('redeemed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('redeemed_at');
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();
        });
        $this->addCouponRedemptionUniqueIndex();

        $this->createTableIfMissing('billing_provider_events', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('provider', 32);
            $table->string('provider_event_id')->unique();
            $table->string('event_type', 96)->index();
            $table->string('status', 24)->index();
            $table->boolean('signature_verified');
            $table->timestamp('provider_created_at')->index();
            $table->timestamp('processed_at')->nullable();
            $table->unsignedInteger('attempts')->default(0);
            $table->char('payload_hash', 64);
            $table->json('payload');
            $table->text('last_error')->nullable();
            $table->timestamps();
            $table->index(['provider', 'status', 'provider_created_at'], 'provider_event_reconciliation_lookup');
        });

        $this->createTableIfMissing('billing_notices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->restrictOnDelete();
            $table->foreignId('business_subscription_id')->constrained()->restrictOnDelete();
            $table->string('type', 48);
            $table->string('channel', 24)->default('email');
            $table->timestamp('scheduled_for');
            $table->timestamp('sent_at')->nullable();
            $table->unsignedInteger('attempts')->default(0);
            $table->string('deduplication_key', 128)->unique();
            $table->text('last_error')->nullable();
            $table->timestamps();
        });

        $this->createTableIfMissing('entitlement_usage', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->restrictOnDelete();
            $table->foreignId('entitlement_definition_id')->constrained()->restrictOnDelete();
            $table->timestamp('period_started_at');
            $table->timestamp('period_ends_at');
            $table->unsignedBigInteger('quantity')->default(0);
            $table->timestamps();
            $table->unique(['business_id', 'entitlement_definition_id', 'period_started_at'], 'entitlement_usage_period_unique');
        });

        $this->createTableIfMissing('owner_registration_intents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('business_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $table->string('business_name');
            $table->string('status', 24)->default('pending')->index();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        $this->seedInitialCatalog();
    }

    public function down(): void
    {
        Schema::dropIfExists('owner_registration_intents');
        Schema::dropIfExists('entitlement_usage');
        Schema::dropIfExists('billing_notices');
        Schema::dropIfExists('billing_provider_events');
        Schema::dropIfExists('billing_coupon_redemptions');
        Schema::dropIfExists('billing_coupons');
        Schema::dropIfExists('billing_payments');
        Schema::dropIfExists('billing_invoices');
        Schema::dropIfExists('subscription_changes');
        Schema::dropIfExists('business_subscriptions');
        Schema::dropIfExists('business_entitlement_overrides');
        Schema::dropIfExists('billing_plan_entitlements');
        Schema::dropIfExists('entitlement_definitions');
        Schema::dropIfExists('billing_plan_prices');
        Schema::dropIfExists('billing_plans');
    }

    private function createTableIfMissing(string $table, callable $definition): void
    {
        if (! Schema::hasTable($table)) {
            Schema::create($table, $definition);
        }
    }

    private function addCouponRedemptionUniqueIndex(): void
    {
        if (! Schema::hasIndex('billing_coupon_redemptions', ['billing_coupon_id', 'business_subscription_id'])) {
            Schema::table('billing_coupon_redemptions', function (Blueprint $table): void {
                $table->unique(
                    ['billing_coupon_id', 'business_subscription_id'],
                    'billing_coupon_subscription_unique'
                );
            });
        }
    }

    private function seedInitialCatalog(): void
    {
        $now = now();
        $planId = DB::table('billing_plans')->where('code', 'trial')->value('id');

        if (! $planId) {
            $planId = DB::table('billing_plans')->insertGetId([
                'public_id' => (string) Str::ulid(),
                'code' => 'trial',
                'name' => 'Good Hours trial',
                'description' => 'Time-limited evaluation entitlement set; paid plan pricing is configured after launch-market approval.',
                'is_active' => true,
                'is_trial_default' => true,
                'available_from' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $definitions = [
            ['locations.max', 'numeric', 'locations', 'Location limit', 'Maximum active locations.'],
            ['staff.max', 'numeric', 'staff', 'Staff limit', 'Maximum active staff profiles.'],
            ['messaging.monthly_allowance', 'numeric', 'messages', 'Messaging allowance', 'Included mobile messages per billing period.'],
            ['deposits.enabled', 'feature', null, 'Deposits', 'Appointment deposit capability.'],
            ['inventory.enabled', 'feature', null, 'Inventory', 'Inventory operations capability.'],
            ['reporting.advanced', 'feature', null, 'Advanced reporting', 'Advanced operational and financial reporting.'],
            ['branding.custom', 'feature', null, 'Custom branding', 'Custom public-booking branding.'],
            ['support.priority', 'feature', null, 'Priority support', 'Priority support service level.'],
            ['exports.enabled', 'feature', null, 'Data exports', 'Business data export capability.'],
            ['billing.manage', 'feature', null, 'Billing management', 'Subscription and billing recovery access.'],
        ];

        foreach ($definitions as [$key, $type, $unit, $name, $description]) {
            $definitionId = DB::table('entitlement_definitions')->where('key', $key)->value('id');

            if (! $definitionId) {
                $definitionId = DB::table('entitlement_definitions')->insertGetId([
                    'key' => $key,
                    'value_type' => $type,
                    'unit' => $unit,
                    'name' => $name,
                    'description' => $description,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            $value = match ($key) {
                'locations.max' => 1,
                'staff.max' => 2,
                'messaging.monthly_allowance' => 0,
                'exports.enabled', 'billing.manage' => true,
                default => false,
            };

            $entitlementExists = DB::table('billing_plan_entitlements')
                ->where('billing_plan_id', $planId)
                ->where('entitlement_definition_id', $definitionId)
                ->exists();

            if ($entitlementExists) {
                continue;
            }

            DB::table('billing_plan_entitlements')->insert([
                'billing_plan_id' => $planId,
                'entitlement_definition_id' => $definitionId,
                'value' => json_encode($value, JSON_THROW_ON_ERROR),
                'effective_from' => $now,
                'change_reason' => 'Initial FR-01 trial entitlement catalog.',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
};
