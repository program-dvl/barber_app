<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('communication_settings', 'sender_mode')) {
            Schema::table('communication_settings', fn (Blueprint $table) => $table->string('sender_mode', 24)->default('platform')->after('mobile_provider'));
        }
        if (! Schema::hasColumn('communication_settings', 'fallback_mobile_channel')) {
            Schema::table('communication_settings', fn (Blueprint $table) => $table->string('fallback_mobile_channel', 24)->nullable()->after('mobile_channel'));
        }
        if (! Schema::hasColumn('communication_settings', 'smart_fallback_enabled')) {
            Schema::table('communication_settings', fn (Blueprint $table) => $table->boolean('smart_fallback_enabled')->default(true)->after('fallback_mobile_channel'));
        }
        if (! Schema::hasColumn('communication_settings', 'two_way_enabled')) {
            Schema::table('communication_settings', fn (Blueprint $table) => $table->boolean('two_way_enabled')->default(false)->after('smart_fallback_enabled'));
        }
        DB::table('communication_settings')->whereNull('fallback_mobile_channel')->update(['fallback_mobile_channel' => 'sms']);

        if (! Schema::hasTable('communication_sender_profiles')) {
            Schema::create('communication_sender_profiles', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained()->cascadeOnDelete();
                $table->ulid('public_id')->unique();
                $table->string('channel', 24);
                $table->string('mode', 24)->default('platform');
                $table->string('provider', 32)->default('twilio');
                $table->string('status', 24)->default('draft');
                $table->string('country_code', 2)->nullable();
                $table->string('display_name')->nullable();
                $table->text('sender_identifier')->nullable();
                $table->string('sender_identifier_hash', 64)->nullable()->index();
                $table->text('provider_account_sid')->nullable();
                $table->string('provider_account_sid_hash', 64)->nullable()->index();
                $table->text('provider_api_key_sid')->nullable();
                $table->text('provider_api_key_secret')->nullable();
                $table->text('webhook_auth_token')->nullable();
                $table->json('capabilities')->nullable();
                $table->text('metadata')->nullable();
                $table->timestamp('verified_at')->nullable();
                $table->timestamps();
                $table->unique(['id', 'business_id']);
                $table->unique(['business_id', 'channel', 'mode'], 'comm_sender_business_channel_mode_unique');
                $table->index(['business_id', 'status', 'channel'], 'comm_sender_active_lookup');
            });
        }

        if (! Schema::hasColumn('communication_messages', 'communication_sender_profile_id')) {
            Schema::table('communication_messages', fn (Blueprint $table) => $table->unsignedBigInteger('communication_sender_profile_id')->nullable()->after('communication_action_link_id'));
        }
        if (! Schema::hasColumn('communication_messages', 'fallback_of_message_id')) {
            Schema::table('communication_messages', fn (Blueprint $table) => $table->unsignedBigInteger('fallback_of_message_id')->nullable()->after('communication_sender_profile_id'));
        }
        if (! Schema::hasForeignKey('communication_messages', 'comm_message_sender_fk')) {
            Schema::table('communication_messages', function (Blueprint $table): void {
                $table->foreign(['communication_sender_profile_id', 'business_id'], 'comm_message_sender_fk')
                    ->references(['id', 'business_id'])->on('communication_sender_profiles')->restrictOnDelete();
            });
        }
        if (! Schema::hasForeignKey('communication_messages', 'comm_message_fallback_fk')) {
            Schema::table('communication_messages', function (Blueprint $table): void {
                $table->foreign('fallback_of_message_id', 'comm_message_fallback_fk')
                    ->references('id')->on('communication_messages')->nullOnDelete();
            });
        }
        if (! Schema::hasIndex('communication_messages', 'comm_message_fallback_lookup')) {
            Schema::table('communication_messages', function (Blueprint $table): void {
                $table->index(['fallback_of_message_id', 'status'], 'comm_message_fallback_lookup');
            });
        }

        if (! Schema::hasTable('communication_conversations')) {
            Schema::create('communication_conversations', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained()->cascadeOnDelete();
                $table->unsignedBigInteger('client_id')->nullable();
                $table->unsignedBigInteger('communication_sender_profile_id')->nullable();
                $table->ulid('public_id')->unique();
                $table->string('channel', 24);
                $table->string('participant_hash', 64);
                $table->text('participant');
                $table->string('status', 24)->default('open');
                $table->timestamp('last_message_at')->nullable();
                $table->timestamp('assigned_at')->nullable();
                $table->unsignedBigInteger('assigned_staff_profile_id')->nullable();
                $table->timestamps();
                $table->unique(['id', 'business_id']);
                $table->unique(['business_id', 'channel', 'participant_hash'], 'comm_conversation_participant_unique');
                $table->foreign(['client_id', 'business_id'], 'comm_conversation_client_fk')->references(['id', 'business_id'])->on('clients')->restrictOnDelete();
                $table->foreign(['communication_sender_profile_id', 'business_id'], 'comm_conversation_sender_fk')->references(['id', 'business_id'])->on('communication_sender_profiles')->restrictOnDelete();
                $table->foreign(['assigned_staff_profile_id', 'business_id'], 'comm_conversation_assignee_fk')->references(['id', 'business_id'])->on('staff_profiles')->restrictOnDelete();
                $table->index(['business_id', 'status', 'last_message_at'], 'comm_conversation_inbox');
            });
        }

        if (! Schema::hasTable('communication_inbound_messages')) {
            Schema::create('communication_inbound_messages', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained()->cascadeOnDelete();
                $table->unsignedBigInteger('communication_conversation_id');
                $table->string('provider', 32);
                $table->string('provider_message_id', 191);
                $table->string('channel', 24);
                $table->string('sender_hash', 64);
                $table->text('sender');
                $table->text('body')->nullable();
                $table->json('media')->nullable();
                $table->timestamp('received_at');
                $table->timestamps();
                $table->unique(['provider', 'provider_message_id'], 'comm_inbound_provider_message_unique');
                $table->foreign(['communication_conversation_id', 'business_id'], 'comm_inbound_conversation_fk')->references(['id', 'business_id'])->on('communication_conversations')->restrictOnDelete();
                $table->index(['business_id', 'received_at'], 'comm_inbound_business_received');
            });
        }

        $this->seedMessagingEntitlements();
    }

    public function down(): void
    {
        Schema::dropIfExists('communication_inbound_messages');
        Schema::dropIfExists('communication_conversations');

        Schema::table('communication_messages', function (Blueprint $table): void {
            $table->dropForeign('comm_message_sender_fk');
            $table->dropForeign('comm_message_fallback_fk');
            $table->dropIndex('comm_message_fallback_lookup');
            $table->dropColumn(['communication_sender_profile_id', 'fallback_of_message_id']);
        });

        Schema::dropIfExists('communication_sender_profiles');

        Schema::table('communication_settings', function (Blueprint $table): void {
            $table->dropColumn(['sender_mode', 'fallback_mobile_channel', 'smart_fallback_enabled', 'two_way_enabled']);
        });
    }

    private function seedMessagingEntitlements(): void
    {
        foreach ([
            'messaging.branded_sender' => ['Branded client messaging', 'Allows a business-owned WhatsApp or SMS sender identity.'],
            'messaging.two_way' => ['Two-way client messaging', 'Allows inbound customer replies and the business conversation inbox.'],
        ] as $key => [$name, $description]) {
            DB::table('entitlement_definitions')->updateOrInsert(['key' => $key], [
                'value_type' => 'feature', 'unit' => null, 'name' => $name, 'description' => $description,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        foreach (['starter' => false, 'pro' => true] as $planCode => $enabled) {
            $planId = DB::table('billing_plans')->where('code', $planCode)->value('id');
            if (! $planId) {
                continue;
            }
            foreach (['messaging.branded_sender', 'messaging.two_way'] as $key) {
                $definitionId = DB::table('entitlement_definitions')->where('key', $key)->value('id');
                $exists = DB::table('billing_plan_entitlements')->where('billing_plan_id', $planId)
                    ->where('entitlement_definition_id', $definitionId)->whereNull('effective_until')->exists();
                if (! $exists) {
                    DB::table('billing_plan_entitlements')->insert([
                        'billing_plan_id' => $planId, 'entitlement_definition_id' => $definitionId,
                        'value' => json_encode($enabled, JSON_THROW_ON_ERROR), 'effective_from' => now(),
                        'effective_until' => null, 'changed_by_user_id' => null,
                        'change_reason' => 'Add messaging sender and conversation capabilities.',
                        'created_at' => now(), 'updated_at' => now(),
                    ]);
                }
            }
        }

        $allowanceDefinition = DB::table('entitlement_definitions')->where('key', 'messaging.monthly_allowance')->value('id');
        $starterId = DB::table('billing_plans')->where('code', 'starter')->value('id');
        if ($allowanceDefinition && $starterId) {
            DB::table('billing_plan_entitlements')->where('billing_plan_id', $starterId)
                ->where('entitlement_definition_id', $allowanceDefinition)->whereNull('effective_until')
                ->update(['value' => json_encode(100, JSON_THROW_ON_ERROR), 'change_reason' => 'Include a starter mobile-message allowance.', 'updated_at' => now()]);
        }
    }
};
