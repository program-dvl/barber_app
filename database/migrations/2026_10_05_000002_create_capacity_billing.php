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
        Schema::create('billing_rate_cards', function (Blueprint $table): void {
            $table->id();
            $table->string('fingerprint', 64)->unique();
            $table->string('market', 2);
            $table->string('currency', 3);
            $table->string('revision');
            $table->string('billing_interval');
            $table->foreignId('billing_plan_price_id')->constrained()->restrictOnDelete();
            $table->json('terms');
            $table->timestamp('verified_at');
            $table->timestamps();
        });
        Schema::table('business_subscriptions', function (Blueprint $table): void {
            $table->foreignId('billing_rate_card_id')->nullable()->constrained()->restrictOnDelete();
            $table->json('capacity_snapshot')->nullable();
        });
        Schema::table('billing_checkout_attempts', fn (Blueprint $table) => $table->json('capacity_quote')->nullable());
        Schema::create('billing_capacity_changes', function (Blueprint $table): void {
            $table->id();
            $table->string('public_id', 26)->unique();
            $table->foreignId('business_id')->constrained()->restrictOnDelete();
            $table->foreignId('business_subscription_id')->constrained()->restrictOnDelete();
            $table->foreignId('actor_user_id')->constrained('users')->restrictOnDelete();
            $table->unsignedInteger('subscription_version');
            $table->string('kind');
            $table->string('status')->default('quoted');
            $table->json('quote');
            $table->timestamp('expires_at');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('effective_at')->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamps();
            $table->index(['business_subscription_id', 'status']);
        });
        Schema::create('sms_credit_purchases', function (Blueprint $table): void {
            $table->id();
            $table->string('public_id', 26)->unique();
            $table->foreignId('business_id')->constrained()->restrictOnDelete();
            $table->foreignId('actor_user_id')->constrained('users')->restrictOnDelete();
            $table->json('quote');
            $table->string('provider_session_id')->nullable()->unique();
            $table->text('checkout_url')->nullable();
            $table->string('status')->default('preparing');
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('expires_at');
            $table->timestamps();
        });
        Schema::create('sms_credit_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->restrictOnDelete();
            $table->string('source_key')->unique();
            $table->bigInteger('quantity');
            $table->string('kind');
            $table->timestamp('created_at');
            $table->index(['business_id', 'id']);
        });
        Schema::create('sms_usage_reservations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->restrictOnDelete();
            $table->foreignId('communication_message_id')->unique()->constrained()->restrictOnDelete();
            $table->unsignedInteger('generation')->default(1);
            $table->unsignedInteger('quantity');
            $table->unsignedInteger('included_quantity');
            $table->unsignedInteger('purchased_quantity');
            $table->string('status');
            $table->timestamp('charged_at');
            $table->timestamps();
        });
        $planId = DB::table('billing_plans')->insertGetId([
            'public_id' => (string) Str::ulid(), 'code' => 'capacity', 'name' => 'ClipperDesk',
            'description' => 'Your locations and bookable staff, with core features included.',
            'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        foreach (config('capacity-billing.features') as $key => $value) {
            $definition = DB::table('entitlement_definitions')->where('key', $key)->value('id');
            if ($definition) {
                DB::table('billing_plan_entitlements')->insert([
                    'billing_plan_id' => $planId, 'entitlement_definition_id' => $definition,
                    'value' => json_encode($value), 'effective_from' => now(), 'change_reason' => 'Approved locations and bookable staff packaging; existing subscriptions retained.',
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        // Financial ledgers and effective rate cards require an explicit recovery
        // procedure; ordinary rollback must not erase paid subscription evidence.
        throw new LogicException('Capacity billing contains financial history. Restore through the documented recovery procedure.');
    }
};
