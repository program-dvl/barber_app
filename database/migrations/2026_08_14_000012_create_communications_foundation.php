<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('communication_settings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->string('email_provider', 32)->default('resend');
            $table->string('mobile_channel', 24)->default('whatsapp');
            $table->string('mobile_provider', 32)->default('twilio');
            $table->string('default_locale', 16)->default('en-IN');
            $table->json('reminder_offsets_minutes');
            $table->time('quiet_hours_start')->default('21:00');
            $table->time('quiet_hours_end')->default('08:00');
            $table->boolean('marketing_enabled')->default(false);
            $table->timestamps();
            $table->unique('business_id');
        });

        Schema::create('communication_templates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->ulid('public_id')->unique();
            $table->string('intent_type', 48);
            $table->string('channel', 24);
            $table->string('locale', 16);
            $table->unsignedInteger('version')->default(1);
            $table->string('status', 16)->default('draft');
            $table->string('subject')->nullable();
            $table->text('body');
            $table->json('variables');
            $table->json('fallbacks')->nullable();
            $table->string('provider_template_id')->nullable();
            $table->string('provider_template_status', 24)->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->unique(['id', 'business_id']);
            $table->unique(['business_id', 'intent_type', 'channel', 'locale', 'version'], 'comm_template_version_unique');
            $table->index(['business_id', 'status', 'intent_type', 'channel', 'locale'], 'comm_template_lookup');
        });

        Schema::create('communication_intents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('client_id')->nullable();
            $table->string('source_type')->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('event_type', 64);
            $table->string('event_key', 191);
            $table->string('intent_type', 48);
            $table->string('category', 24);
            $table->string('legal_basis', 64);
            $table->string('locale', 16);
            $table->string('time_zone', 64);
            $table->timestamp('scheduled_for_utc');
            $table->string('local_scheduled_for', 48);
            $table->string('status', 24)->default('queued');
            $table->uuid('correlation_id');
            $table->timestamps();
            $table->unique(['id', 'business_id']);
            $table->unique(['business_id', 'event_key', 'intent_type'], 'comm_intent_event_unique');
            $table->foreign(['client_id', 'business_id'], 'comm_intent_client_fk')->references(['id', 'business_id'])->on('clients')->restrictOnDelete();
            $table->index(['business_id', 'status', 'scheduled_for_utc'], 'comm_intent_due');
            $table->index(['business_id', 'source_type', 'source_id'], 'comm_intent_source');
        });

        Schema::create('communication_action_links', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('client_id')->nullable();
            $table->ulid('public_id')->unique();
            $table->string('purpose', 48);
            $table->string('target_type')->nullable();
            $table->unsignedBigInteger('target_id')->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
            $table->unique(['id', 'business_id']);
            $table->foreign(['client_id', 'business_id'], 'comm_link_client_fk')->references(['id', 'business_id'])->on('clients')->restrictOnDelete();
            $table->index(['business_id', 'target_type', 'target_id', 'purpose'], 'comm_link_target');
        });

        Schema::create('communication_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('communication_intent_id');
            $table->unsignedBigInteger('client_id')->nullable();
            $table->unsignedBigInteger('communication_template_id');
            $table->unsignedBigInteger('communication_action_link_id')->nullable();
            $table->string('channel', 24);
            $table->string('recipient_hash', 64);
            $table->text('recipient');
            $table->string('idempotency_key', 64);
            $table->string('category', 24);
            $table->string('legal_basis', 64);
            $table->string('locale', 16);
            $table->string('time_zone', 64);
            $table->text('template_variables');
            $table->string('subject_hash', 64)->nullable();
            $table->string('body_hash', 64)->nullable();
            $table->string('status', 24)->default('queued');
            $table->unsignedTinyInteger('attempt_count')->default(0);
            $table->unsignedTinyInteger('max_attempts')->default(4);
            $table->string('provider', 32)->nullable();
            $table->string('provider_message_id')->nullable();
            $table->timestamp('provider_state_at')->nullable();
            $table->string('last_error_code', 80)->nullable();
            $table->string('last_error_class', 24)->nullable();
            $table->string('suppression_reason', 80)->nullable();
            $table->timestamp('queued_at');
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamp('next_attempt_at')->nullable();
            $table->timestamps();
            $table->unique(['id', 'business_id']);
            $table->unique('idempotency_key');
            $table->unique(['communication_intent_id', 'channel', 'recipient_hash'], 'comm_message_once_per_channel');
            $table->foreign(['communication_intent_id', 'business_id'], 'comm_message_intent_fk')->references(['id', 'business_id'])->on('communication_intents')->restrictOnDelete();
            $table->foreign(['client_id', 'business_id'], 'comm_message_client_fk')->references(['id', 'business_id'])->on('clients')->restrictOnDelete();
            $table->foreign(['communication_template_id', 'business_id'], 'comm_message_template_fk')->references(['id', 'business_id'])->on('communication_templates')->restrictOnDelete();
            $table->foreign(['communication_action_link_id', 'business_id'], 'comm_message_link_fk')->references(['id', 'business_id'])->on('communication_action_links')->restrictOnDelete();
            $table->index(['business_id', 'status', 'next_attempt_at'], 'comm_message_work');
            $table->index(['provider', 'provider_message_id'], 'comm_message_provider');
            $table->index(['business_id', 'client_id', 'created_at'], 'comm_message_client_history');
        });

        Schema::create('communication_delivery_attempts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('communication_message_id');
            $table->unsignedTinyInteger('attempt_number');
            $table->string('idempotency_key', 64);
            $table->string('status', 24);
            $table->string('provider', 32);
            $table->string('provider_request_id')->nullable();
            $table->string('provider_message_id')->nullable();
            $table->string('error_code', 80)->nullable();
            $table->string('error_class', 24)->nullable();
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
            $table->unique(['communication_message_id', 'attempt_number'], 'comm_attempt_number_unique');
            $table->foreign(['communication_message_id', 'business_id'], 'comm_attempt_message_fk')->references(['id', 'business_id'])->on('communication_messages')->restrictOnDelete();
            $table->index(['business_id', 'status', 'started_at'], 'comm_attempt_support');
        });

        Schema::create('communication_suppressions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('client_id')->nullable();
            $table->string('channel', 24);
            $table->string('recipient_hash', 64);
            $table->string('scope', 24);
            $table->string('reason', 80);
            $table->string('source', 40);
            $table->timestamp('suppressed_at');
            $table->timestamp('released_at')->nullable();
            $table->timestamps();
            $table->foreign(['client_id', 'business_id'], 'comm_suppression_client_fk')->references(['id', 'business_id'])->on('clients')->restrictOnDelete();
            $table->index(['business_id', 'channel', 'recipient_hash', 'released_at'], 'comm_suppression_lookup');
        });

        Schema::create('communication_provider_events', function (Blueprint $table): void {
            $table->id();
            $table->string('provider', 32);
            $table->string('provider_event_id', 191);
            $table->unsignedBigInteger('business_id')->nullable();
            $table->unsignedBigInteger('communication_message_id')->nullable();
            $table->string('provider_message_id')->nullable();
            $table->string('event_type', 64);
            $table->string('payload_hash', 64);
            $table->boolean('signature_verified');
            $table->string('status', 24)->default('pending');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('provider_occurred_at');
            $table->timestamp('processed_at')->nullable();
            $table->string('last_error_code', 80)->nullable();
            $table->timestamps();
            $table->unique(['provider', 'provider_event_id'], 'comm_provider_event_unique');
            $table->foreign('business_id')->references('id')->on('businesses')->nullOnDelete();
            $table->foreign('communication_message_id')->references('id')->on('communication_messages')->nullOnDelete();
            $table->index(['provider', 'provider_message_id', 'provider_occurred_at'], 'comm_provider_message_events');
            $table->index(['status', 'created_at'], 'comm_provider_event_work');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('communication_provider_events');
        Schema::dropIfExists('communication_suppressions');
        Schema::dropIfExists('communication_delivery_attempts');
        Schema::dropIfExists('communication_messages');
        Schema::dropIfExists('communication_action_links');
        Schema::dropIfExists('communication_intents');
        Schema::dropIfExists('communication_templates');
        Schema::dropIfExists('communication_settings');
    }
};
