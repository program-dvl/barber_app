<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_command_keys', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->string('scope', 16);
            $table->string('idempotency_key', 128);
            $table->string('request_hash', 64);
            $table->string('result_type', 32)->nullable();
            $table->unsignedBigInteger('result_id')->nullable();
            $table->timestamps();
            $table->unique(['business_id', 'scope', 'idempotency_key'], 'booking_command_key_unique');
        });

        Schema::create('appointments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('location_id');
            $table->ulid('public_id')->unique();
            $table->string('idempotency_key', 128);
            $table->string('request_hash', 64);
            $table->string('status', 32)->default('confirmed');
            $table->string('source', 24);
            $table->timestamp('starts_at_utc');
            $table->timestamp('ends_at_utc');
            $table->string('time_zone', 64);
            $table->string('local_starts_at', 32);
            $table->string('local_ends_at', 32);
            $table->unsignedBigInteger('price_minor')->default(0);
            $table->string('currency_code', 3);
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();
            $table->unique(['id', 'business_id']);
            $table->unique(['business_id', 'idempotency_key'], 'appointment_idempotency_unique');
            $table->foreign(['location_id', 'business_id'], 'appointments_location_fk')->references(['id', 'business_id'])->on('locations')->restrictOnDelete();
            $table->index(['business_id', 'location_id', 'starts_at_utc', 'status'], 'appointment_calendar_lookup');
            $table->index(['business_id', 'ends_at_utc', 'status'], 'appointment_future_lookup');
        });

        Schema::create('appointment_service_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('appointment_id');
            $table->unsignedBigInteger('service_id');
            $table->unsignedBigInteger('primary_staff_profile_id')->nullable();
            $table->unsignedSmallInteger('sequence');
            $table->string('name');
            $table->unsignedBigInteger('price_minor');
            $table->string('currency_code', 3);
            $table->unsignedSmallInteger('bookable_minutes');
            $table->json('configuration_snapshot');
            $table->timestamps();
            $table->unique(['id', 'business_id']);
            $table->unique(['appointment_id', 'sequence']);
            $table->foreign(['appointment_id', 'business_id'], 'appointment_lines_appointment_fk')->references(['id', 'business_id'])->on('appointments')->cascadeOnDelete();
            $table->foreign(['service_id', 'business_id'], 'appointment_lines_service_fk')->references(['id', 'business_id'])->on('services')->restrictOnDelete();
            $table->foreign(['primary_staff_profile_id', 'business_id'], 'appointment_lines_staff_fk')->references(['id', 'business_id'])->on('staff_profiles')->restrictOnDelete();
            $table->index(['business_id', 'service_id', 'appointment_id'], 'appointment_line_service_lookup');
        });

        Schema::create('appointment_segments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('appointment_id');
            $table->unsignedBigInteger('appointment_service_line_id');
            $table->unsignedBigInteger('service_segment_id')->nullable();
            $table->unsignedBigInteger('staff_profile_id')->nullable();
            $table->string('kind', 16);
            $table->unsignedSmallInteger('sequence');
            $table->timestamp('starts_at_utc');
            $table->timestamp('ends_at_utc');
            $table->string('time_zone', 64);
            $table->string('local_starts_at', 32);
            $table->string('local_ends_at', 32);
            $table->boolean('occupies_staff')->default(true);
            $table->timestamps();
            $table->unique(['id', 'business_id']);
            $table->unique(['appointment_service_line_id', 'sequence'], 'appointment_segment_sequence_unique');
            $table->foreign(['appointment_id', 'business_id'], 'appointment_segments_appointment_fk')->references(['id', 'business_id'])->on('appointments')->cascadeOnDelete();
            $table->foreign(['appointment_service_line_id', 'business_id'], 'appointment_segments_line_fk')->references(['id', 'business_id'])->on('appointment_service_lines')->cascadeOnDelete();
            $table->foreign(['service_segment_id', 'business_id'], 'appointment_segments_service_segment_fk')->references(['id', 'business_id'])->on('service_segments')->restrictOnDelete();
            $table->foreign(['staff_profile_id', 'business_id'], 'appointment_segments_staff_fk')->references(['id', 'business_id'])->on('staff_profiles')->restrictOnDelete();
            $table->index(['business_id', 'staff_profile_id', 'starts_at_utc', 'ends_at_utc'], 'appointment_staff_conflict_lookup');
            $table->index(['business_id', 'appointment_id', 'starts_at_utc'], 'appointment_segment_visit_lookup');
        });

        Schema::create('appointment_resource_claims', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('appointment_id');
            $table->unsignedBigInteger('appointment_service_line_id');
            $table->unsignedBigInteger('appointment_segment_id')->nullable();
            $table->unsignedBigInteger('physical_resource_id');
            $table->unsignedSmallInteger('quantity');
            $table->timestamp('starts_at_utc');
            $table->timestamp('ends_at_utc');
            $table->timestamps();
            $table->foreign(['appointment_id', 'business_id'], 'appointment_resource_appointment_fk')->references(['id', 'business_id'])->on('appointments')->cascadeOnDelete();
            $table->foreign(['appointment_service_line_id', 'business_id'], 'appointment_resource_line_fk')->references(['id', 'business_id'])->on('appointment_service_lines')->cascadeOnDelete();
            $table->foreign(['appointment_segment_id', 'business_id'], 'appointment_resource_segment_fk')->references(['id', 'business_id'])->on('appointment_segments')->cascadeOnDelete();
            $table->foreign(['physical_resource_id', 'business_id'], 'appointment_resource_physical_fk')->references(['id', 'business_id'])->on('physical_resources')->restrictOnDelete();
            $table->index(['business_id', 'physical_resource_id', 'starts_at_utc', 'ends_at_utc'], 'appointment_resource_conflict_lookup');
        });

        Schema::create('appointment_status_history', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('appointment_id');
            $table->string('previous_status', 32)->nullable();
            $table->string('status', 32);
            $table->string('source', 24);
            $table->string('actor_type', 64)->nullable();
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->text('reason')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();
            $table->foreign(['appointment_id', 'business_id'], 'appointment_history_appointment_fk')->references(['id', 'business_id'])->on('appointments')->cascadeOnDelete();
            $table->index(['business_id', 'appointment_id', 'occurred_at'], 'appointment_history_lookup');
        });

        Schema::create('capacity_holds', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('location_id');
            $table->unsignedBigInteger('appointment_id')->nullable();
            $table->ulid('public_id')->unique();
            $table->string('idempotency_key', 128);
            $table->string('request_hash', 64);
            $table->string('status', 16)->default('active');
            $table->string('source', 24);
            $table->string('client_eligibility', 24)->default('existing');
            $table->string('owner_key', 128);
            $table->string('actor_type', 64)->nullable();
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->timestamp('starts_at_utc');
            $table->timestamp('ends_at_utc');
            $table->timestamp('expires_at');
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('expired_at')->nullable();
            $table->timestamps();
            $table->unique(['id', 'business_id']);
            $table->unique(['business_id', 'idempotency_key'], 'capacity_hold_idempotency_unique');
            $table->foreign(['location_id', 'business_id'], 'capacity_holds_location_fk')->references(['id', 'business_id'])->on('locations')->restrictOnDelete();
            $table->foreign(['appointment_id', 'business_id'], 'capacity_holds_appointment_fk')->references(['id', 'business_id'])->on('appointments')->restrictOnDelete();
            $table->index(['business_id', 'status', 'expires_at'], 'capacity_hold_expiry_lookup');
            $table->index(['business_id', 'location_id', 'starts_at_utc', 'ends_at_utc'], 'capacity_hold_window_lookup');
        });

        Schema::create('capacity_hold_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('capacity_hold_id');
            $table->unsignedBigInteger('service_id');
            $table->unsignedBigInteger('primary_staff_profile_id')->nullable();
            $table->unsignedSmallInteger('sequence');
            $table->json('configuration_snapshot');
            $table->timestamps();
            $table->unique(['id', 'business_id']);
            $table->unique(['capacity_hold_id', 'sequence']);
            $table->foreign(['capacity_hold_id', 'business_id'], 'capacity_hold_lines_hold_fk')->references(['id', 'business_id'])->on('capacity_holds')->cascadeOnDelete();
            $table->foreign(['service_id', 'business_id'], 'capacity_hold_lines_service_fk')->references(['id', 'business_id'])->on('services')->restrictOnDelete();
            $table->foreign(['primary_staff_profile_id', 'business_id'], 'capacity_hold_lines_staff_fk')->references(['id', 'business_id'])->on('staff_profiles')->restrictOnDelete();
        });

        Schema::create('capacity_hold_segments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('capacity_hold_id');
            $table->unsignedBigInteger('capacity_hold_line_id');
            $table->unsignedBigInteger('service_segment_id')->nullable();
            $table->unsignedBigInteger('staff_profile_id')->nullable();
            $table->string('kind', 16);
            $table->unsignedSmallInteger('sequence');
            $table->timestamp('starts_at_utc');
            $table->timestamp('ends_at_utc');
            $table->boolean('occupies_staff')->default(true);
            $table->timestamps();
            $table->unique(['id', 'business_id']);
            $table->unique(['capacity_hold_line_id', 'sequence'], 'capacity_hold_segment_sequence_unique');
            $table->foreign(['capacity_hold_id', 'business_id'], 'capacity_hold_segments_hold_fk')->references(['id', 'business_id'])->on('capacity_holds')->cascadeOnDelete();
            $table->foreign(['capacity_hold_line_id', 'business_id'], 'capacity_hold_segments_line_fk')->references(['id', 'business_id'])->on('capacity_hold_lines')->cascadeOnDelete();
            $table->foreign(['service_segment_id', 'business_id'], 'capacity_hold_segments_service_segment_fk')->references(['id', 'business_id'])->on('service_segments')->restrictOnDelete();
            $table->foreign(['staff_profile_id', 'business_id'], 'capacity_hold_segments_staff_fk')->references(['id', 'business_id'])->on('staff_profiles')->restrictOnDelete();
            $table->index(['business_id', 'staff_profile_id', 'starts_at_utc', 'ends_at_utc'], 'capacity_hold_staff_conflict_lookup');
        });

        Schema::create('capacity_hold_resource_claims', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('capacity_hold_id');
            $table->unsignedBigInteger('capacity_hold_line_id');
            $table->unsignedBigInteger('capacity_hold_segment_id')->nullable();
            $table->unsignedBigInteger('physical_resource_id');
            $table->unsignedSmallInteger('quantity');
            $table->timestamp('starts_at_utc');
            $table->timestamp('ends_at_utc');
            $table->timestamps();
            $table->foreign(['capacity_hold_id', 'business_id'], 'capacity_hold_resource_hold_fk')->references(['id', 'business_id'])->on('capacity_holds')->cascadeOnDelete();
            $table->foreign(['capacity_hold_line_id', 'business_id'], 'capacity_hold_resource_line_fk')->references(['id', 'business_id'])->on('capacity_hold_lines')->cascadeOnDelete();
            $table->foreign(['capacity_hold_segment_id', 'business_id'], 'capacity_hold_resource_segment_fk')->references(['id', 'business_id'])->on('capacity_hold_segments')->cascadeOnDelete();
            $table->foreign(['physical_resource_id', 'business_id'], 'capacity_hold_resource_physical_fk')->references(['id', 'business_id'])->on('physical_resources')->restrictOnDelete();
            $table->index(['business_id', 'physical_resource_id', 'starts_at_utc', 'ends_at_utc'], 'capacity_hold_resource_conflict_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('capacity_hold_resource_claims');
        Schema::dropIfExists('capacity_hold_segments');
        Schema::dropIfExists('capacity_hold_lines');
        Schema::dropIfExists('capacity_holds');
        Schema::dropIfExists('appointment_status_history');
        Schema::dropIfExists('appointment_resource_claims');
        Schema::dropIfExists('appointment_segments');
        Schema::dropIfExists('appointment_service_lines');
        Schema::dropIfExists('appointments');
        Schema::dropIfExists('booking_command_keys');
    }
};
