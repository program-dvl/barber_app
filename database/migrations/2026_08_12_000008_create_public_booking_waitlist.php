<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('businesses', function (Blueprint $table): void {
            $table->boolean('online_booking_enabled')->default(true)->after('configuration_published_at');
            $table->string('online_staff_preference', 24)->default('any_or_preferred');
            $table->string('online_price_display', 16)->default('service_setting');
            $table->string('online_new_client_rule', 24)->default('allow');
            $table->boolean('staff_gender_request_enabled')->default(false);
            $table->unsignedInteger('cancellation_cutoff_minutes')->default(1440);
            $table->unsignedSmallInteger('waitlist_offer_batch_size')->default(1);
            $table->unsignedInteger('public_link_ttl_minutes')->default(10080);
            $table->unsignedInteger('public_booking_policy_version')->default(1);
        });

        Schema::table('appointments', function (Blueprint $table): void {
            $table->string('booking_reference', 24)->nullable()->unique()->after('public_id');
            $table->string('client_email')->nullable()->after('client_mobile');
            $table->date('client_date_of_birth')->nullable()->after('client_email');
            $table->string('referral_source')->nullable()->after('client_date_of_birth');
            $table->json('communication_preferences')->nullable()->after('referral_source');
            $table->boolean('marketing_opt_in')->default(false)->after('communication_preferences');
            $table->text('special_request')->nullable()->after('marketing_opt_in');
            $table->json('public_policy_snapshot')->nullable()->after('special_request');
        });

        Schema::create('public_booking_flows', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('capacity_hold_id')->nullable();
            $table->unsignedBigInteger('appointment_id')->nullable();
            $table->ulid('public_id')->unique();
            $table->string('secret_hash', 64)->unique();
            $table->string('status', 24)->default('started');
            $table->unsignedTinyInteger('last_step')->default(1);
            $table->unsignedInteger('policy_version');
            $table->json('state')->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();
            $table->unique(['id', 'business_id']);
            $table->foreign(['capacity_hold_id', 'business_id'], 'public_flows_hold_fk')->references(['id', 'business_id'])->on('capacity_holds')->restrictOnDelete();
            $table->foreign(['appointment_id', 'business_id'], 'public_flows_appointment_fk')->references(['id', 'business_id'])->on('appointments')->restrictOnDelete();
            $table->index(['business_id', 'status', 'expires_at'], 'public_booking_flow_lookup');
        });

        Schema::create('public_appointment_links', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('appointment_id');
            $table->string('purpose', 32);
            $table->string('token_hash', 64)->unique();
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
            $table->foreign(['appointment_id', 'business_id'], 'public_links_appointment_fk')->references(['id', 'business_id'])->on('appointments')->cascadeOnDelete();
            $table->index(['business_id', 'appointment_id', 'purpose', 'expires_at'], 'public_appointment_link_lookup');
        });

        Schema::create('waitlist_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('location_id');
            $table->unsignedBigInteger('service_id');
            $table->unsignedBigInteger('preferred_staff_profile_id')->nullable();
            $table->ulid('public_id')->unique();
            $table->string('client_name');
            $table->string('client_mobile', 32);
            $table->string('client_email')->nullable();
            $table->date('acceptable_from');
            $table->date('acceptable_until');
            $table->time('time_from');
            $table->time('time_until');
            $table->string('notification_method', 16);
            $table->text('notes')->nullable();
            $table->string('status', 24)->default('active');
            $table->string('active_dedupe_key', 64)->nullable();
            $table->timestamp('expires_at');
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
            $table->unique(['id', 'business_id']);
            $table->unique(['business_id', 'active_dedupe_key'], 'waitlist_active_dedupe_unique');
            $table->foreign(['location_id', 'business_id'], 'waitlist_location_fk')->references(['id', 'business_id'])->on('locations')->restrictOnDelete();
            $table->foreign(['service_id', 'business_id'], 'waitlist_service_fk')->references(['id', 'business_id'])->on('services')->restrictOnDelete();
            $table->foreign(['preferred_staff_profile_id', 'business_id'], 'waitlist_staff_fk')->references(['id', 'business_id'])->on('staff_profiles')->restrictOnDelete();
            $table->index(['business_id', 'location_id', 'service_id', 'status', 'acceptable_from'], 'waitlist_match_lookup');
        });

        Schema::create('waitlist_matches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('waitlist_request_id');
            $table->unsignedBigInteger('appointment_id')->nullable();
            $table->unsignedBigInteger('staff_profile_id')->nullable();
            $table->ulid('public_id')->unique();
            $table->ulid('batch_id');
            $table->string('claim_token_hash', 64)->unique();
            $table->string('status', 24)->default('offered');
            $table->timestamp('slot_starts_at_utc');
            $table->timestamp('slot_ends_at_utc');
            $table->timestamp('offered_at');
            $table->timestamp('expires_at');
            $table->timestamp('claimed_at')->nullable();
            $table->timestamps();
            $table->unique(['id', 'business_id']);
            $table->foreign(['waitlist_request_id', 'business_id'], 'waitlist_matches_request_fk')->references(['id', 'business_id'])->on('waitlist_requests')->cascadeOnDelete();
            $table->foreign(['appointment_id', 'business_id'], 'waitlist_matches_appointment_fk')->references(['id', 'business_id'])->on('appointments')->restrictOnDelete();
            $table->foreign(['staff_profile_id', 'business_id'], 'waitlist_matches_staff_fk')->references(['id', 'business_id'])->on('staff_profiles')->restrictOnDelete();
            $table->index(['business_id', 'batch_id', 'status', 'expires_at'], 'waitlist_offer_lookup');
        });

        Schema::create('public_booking_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('public_booking_flow_id')->nullable();
            $table->unsignedBigInteger('appointment_id')->nullable();
            $table->string('event_name', 64);
            $table->string('session_hash', 64);
            $table->json('metadata')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();
            $table->foreign(['public_booking_flow_id', 'business_id'], 'public_events_flow_fk')->references(['id', 'business_id'])->on('public_booking_flows')->cascadeOnDelete();
            $table->foreign(['appointment_id', 'business_id'], 'public_events_appointment_fk')->references(['id', 'business_id'])->on('appointments')->restrictOnDelete();
            $table->index(['business_id', 'event_name', 'occurred_at'], 'public_booking_event_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('public_booking_events');
        Schema::dropIfExists('waitlist_matches');
        Schema::dropIfExists('waitlist_requests');
        Schema::dropIfExists('public_appointment_links');
        Schema::dropIfExists('public_booking_flows');

        Schema::table('appointments', function (Blueprint $table): void {
            $table->dropUnique(['booking_reference']);
            $table->dropColumn(['booking_reference', 'client_email', 'client_date_of_birth', 'referral_source', 'communication_preferences', 'marketing_opt_in', 'special_request', 'public_policy_snapshot']);
        });
        Schema::table('businesses', function (Blueprint $table): void {
            $table->dropColumn(['online_booking_enabled', 'online_staff_preference', 'online_price_display', 'online_new_client_rule', 'staff_gender_request_enabled', 'cancellation_cutoff_minutes', 'waitlist_offer_batch_size', 'public_link_ttl_minutes', 'public_booking_policy_version']);
        });
    }
};
