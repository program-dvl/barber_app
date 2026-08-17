<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        $plans = [
            'starter' => [
                'name' => 'Starter',
                'description' => 'For solo and very small shops: one location and up to two active staff members.',
                'prices' => [
                    ['monthly', 'pri_01m01trgvhqc0mn20q9cv9agpv', 5000],
                    ['annual', 'pri_01m01twkb2j0b1kchq9xpygsn6', 50000],
                ],
                'entitlements' => [
                    'locations.max' => 1,
                    'staff.max' => 2,
                    'messaging.monthly_allowance' => 0,
                    'deposits.enabled' => false,
                    'inventory.enabled' => false,
                    'reporting.advanced' => false,
                    'branding.custom' => false,
                    'support.priority' => false,
                    'exports.enabled' => true,
                    'billing.manage' => true,
                ],
            ],
            'pro' => [
                'name' => 'Pro',
                'description' => 'For growing salons: higher capacity and the currently implemented paid operational capabilities.',
                'prices' => [
                    ['monthly', 'pri_01m01tsht5zh8hqfbwgphna6a9', 10000],
                    ['annual', 'pri_01m01twz5q1qvq0w8rmjwnaakq', 100000],
                ],
                'entitlements' => [
                    'locations.max' => 3,
                    'staff.max' => 20,
                    'messaging.monthly_allowance' => 1000,
                    'deposits.enabled' => true,
                    'inventory.enabled' => true,
                    'reporting.advanced' => true,
                    'branding.custom' => true,
                    'support.priority' => true,
                    'exports.enabled' => true,
                    'billing.manage' => true,
                ],
            ],
        ];

        foreach ($plans as $code => $plan) {
            $planId = DB::table('billing_plans')->where('code', $code)->value('id');

            if (! $planId) {
                $planId = DB::table('billing_plans')->insertGetId([
                    'public_id' => (string) Str::ulid(),
                    'code' => $code,
                    'name' => $plan['name'],
                    'description' => $plan['description'],
                    'is_active' => true,
                    'is_trial_default' => false,
                    'available_from' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            } else {
                DB::table('billing_plans')->where('id', $planId)->update([
                    'name' => $plan['name'],
                    'description' => $plan['description'],
                    'is_active' => true,
                    'updated_at' => $now,
                ]);
            }

            foreach ($plan['prices'] as [$interval, $providerPriceId, $amountMinor]) {
                DB::table('billing_plan_prices')->updateOrInsert(
                    ['provider_price_id' => $providerPriceId],
                    [
                        'billing_plan_id' => $planId,
                        'billing_interval' => $interval,
                        'currency' => 'USD',
                        'amount_minor' => $amountMinor,
                        'provider' => 'paddle',
                        'is_active' => true,
                        'effective_from' => $now,
                        'effective_until' => null,
                        'updated_at' => $now,
                        'created_at' => $now,
                    ]
                );
            }

            foreach ($plan['entitlements'] as $key => $value) {
                $definitionId = DB::table('entitlement_definitions')->where('key', $key)->value('id');
                abort_unless($definitionId, 500, "Missing entitlement definition: {$key}");

                $alreadyConfigured = DB::table('billing_plan_entitlements')
                    ->where('billing_plan_id', $planId)
                    ->where('entitlement_definition_id', $definitionId)
                    ->whereNull('effective_until')
                    ->exists();

                if (! $alreadyConfigured) {
                    DB::table('billing_plan_entitlements')->insert([
                        'billing_plan_id' => $planId,
                        'entitlement_definition_id' => $definitionId,
                        'value' => json_encode($value, JSON_THROW_ON_ERROR),
                        'effective_from' => $now,
                        'change_reason' => 'Initial Paddle Starter and Pro catalog approved by Product.',
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        // Catalog and entitlement history may have been used by subscriptions;
        // never delete or rewrite it during rollback.
    }
};
