<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('billing_checkout_attempts', 'provider_checkout_url')) {
            Schema::table('billing_checkout_attempts', function (Blueprint $table): void {
                $table->string('provider_checkout_url', 2048)->nullable()->after('provider_subscription_id');
            });
        }

        if (Schema::hasTable('communication_messages') && ! Schema::hasColumn('communication_messages', 'entitlement_charged_at')) {
            Schema::table('communication_messages', function (Blueprint $table): void {
                $table->timestamp('entitlement_charged_at')->nullable()->after('next_attempt_at');
            });
        }

        $effectiveUntil = now();
        DB::table('billing_plan_prices')
            ->where('provider', 'paddle')
            ->where('is_active', true)
            ->update([
                'is_active' => false,
                'effective_until' => DB::raw('COALESCE(effective_until, CURRENT_TIMESTAMP)'),
                'updated_at' => $effectiveUntil,
            ]);

        // A trial has no external Paddle contract, so it can safely adopt the
        // selected provider. Paid Paddle records remain unchanged historical
        // evidence until an operator completes an explicit customer migration.
        DB::table('business_subscriptions')
            ->where('provider', 'paddle')
            ->where('status', 'trialing')
            ->whereNull('provider_customer_id')
            ->whereNull('provider_subscription_id')
            ->update(['provider' => 'stripe', 'updated_at' => $effectiveUntil]);
    }

    public function down(): void
    {
        // Provider history and catalog retirement are intentionally not
        // rewritten. Rolling application code back must not relabel billing
        // evidence or silently reactivate obsolete prices.
    }
};
