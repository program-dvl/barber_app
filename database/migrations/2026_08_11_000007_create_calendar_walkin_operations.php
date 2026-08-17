<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table): void {
            $table->unsignedBigInteger('rescheduled_from_appointment_id')->nullable()->after('location_id');
            $table->unsignedBigInteger('rescheduled_to_appointment_id')->nullable()->after('rescheduled_from_appointment_id');
            $table->string('client_name')->nullable()->after('source');
            $table->string('client_mobile', 32)->nullable()->after('client_name');
            $table->text('internal_notes')->nullable()->after('client_mobile');
            $table->unsignedInteger('version')->default(1)->after('confirmed_at');
            $table->timestamp('arrived_at')->nullable()->after('version');
            $table->timestamp('checked_in_at')->nullable()->after('arrived_at');
            $table->timestamp('service_started_at')->nullable()->after('checked_in_at');
            $table->timestamp('completed_at')->nullable()->after('service_started_at');
            $table->timestamp('cancelled_at')->nullable()->after('completed_at');
            $table->foreign(['rescheduled_from_appointment_id', 'business_id'], 'appointments_rescheduled_from_fk')
                ->references(['id', 'business_id'])->on('appointments')->restrictOnDelete();
            $table->foreign(['rescheduled_to_appointment_id', 'business_id'], 'appointments_rescheduled_to_fk')
                ->references(['id', 'business_id'])->on('appointments')->restrictOnDelete();
        });

        Schema::create('appointment_changes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('appointment_id');
            $table->string('kind', 32);
            $table->string('source', 24);
            $table->string('actor_type', 64)->nullable();
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->text('reason')->nullable();
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();
            $table->foreign(['appointment_id', 'business_id'], 'appointment_changes_appointment_fk')
                ->references(['id', 'business_id'])->on('appointments')->cascadeOnDelete();
            $table->index(['business_id', 'appointment_id', 'occurred_at'], 'appointment_change_lookup');
        });

        Schema::create('schedule_blocks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('location_id');
            $table->unsignedBigInteger('staff_profile_id');
            $table->ulid('public_id')->unique();
            $table->string('kind', 24);
            $table->string('label');
            $table->text('private_reason')->nullable();
            $table->timestamp('starts_at_utc');
            $table->timestamp('ends_at_utc');
            $table->string('time_zone', 64);
            $table->string('local_starts_at', 32);
            $table->string('local_ends_at', 32);
            $table->unsignedInteger('version')->default(1);
            $table->string('actor_type', 64)->nullable();
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->timestamps();
            $table->unique(['id', 'business_id']);
            $table->foreign(['location_id', 'business_id'], 'schedule_blocks_location_fk')
                ->references(['id', 'business_id'])->on('locations')->restrictOnDelete();
            $table->foreign(['staff_profile_id', 'business_id'], 'schedule_blocks_staff_fk')
                ->references(['id', 'business_id'])->on('staff_profiles')->restrictOnDelete();
            $table->index(['business_id', 'location_id', 'starts_at_utc', 'ends_at_utc'], 'schedule_block_calendar_lookup');
            $table->index(['business_id', 'staff_profile_id', 'starts_at_utc', 'ends_at_utc'], 'schedule_block_staff_lookup');
        });

        Schema::create('walk_in_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('location_id');
            $table->unsignedBigInteger('service_id');
            $table->unsignedBigInteger('preferred_staff_profile_id')->nullable();
            $table->unsignedBigInteger('assigned_staff_profile_id')->nullable();
            $table->unsignedBigInteger('appointment_id')->nullable();
            $table->ulid('public_id')->unique();
            $table->string('client_name');
            $table->string('client_mobile', 32);
            $table->text('notes')->nullable();
            $table->string('status', 24)->default('waiting');
            $table->unsignedInteger('queue_position');
            $table->timestamp('arrived_at');
            $table->timestamp('estimated_service_at')->nullable();
            $table->unsignedInteger('estimated_wait_minutes')->nullable();
            $table->json('estimate_evidence')->nullable();
            $table->timestamp('notified_at')->nullable();
            $table->timestamp('service_started_at')->nullable();
            $table->timestamp('abandoned_at')->nullable();
            $table->unsignedInteger('actual_wait_minutes')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
            $table->unique(['id', 'business_id']);
            $table->foreign(['location_id', 'business_id'], 'walk_ins_location_fk')
                ->references(['id', 'business_id'])->on('locations')->restrictOnDelete();
            $table->foreign(['service_id', 'business_id'], 'walk_ins_service_fk')
                ->references(['id', 'business_id'])->on('services')->restrictOnDelete();
            $table->foreign(['preferred_staff_profile_id', 'business_id'], 'walk_ins_preferred_staff_fk')
                ->references(['id', 'business_id'])->on('staff_profiles')->restrictOnDelete();
            $table->foreign(['assigned_staff_profile_id', 'business_id'], 'walk_ins_assigned_staff_fk')
                ->references(['id', 'business_id'])->on('staff_profiles')->restrictOnDelete();
            $table->foreign(['appointment_id', 'business_id'], 'walk_ins_appointment_fk')
                ->references(['id', 'business_id'])->on('appointments')->restrictOnDelete();
            $table->index(['business_id', 'location_id', 'status', 'queue_position'], 'walk_in_queue_lookup');
        });

        Schema::create('walk_in_history', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('walk_in_entry_id');
            $table->string('action', 32);
            $table->string('previous_status', 24)->nullable();
            $table->string('status', 24);
            $table->string('source', 24);
            $table->string('actor_type', 64)->nullable();
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->text('reason')->nullable();
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();
            $table->foreign(['walk_in_entry_id', 'business_id'], 'walk_in_history_entry_fk')
                ->references(['id', 'business_id'])->on('walk_in_entries')->cascadeOnDelete();
            $table->index(['business_id', 'walk_in_entry_id', 'occurred_at'], 'walk_in_history_lookup');
        });

        Schema::create('operational_exceptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('location_id');
            $table->unsignedBigInteger('appointment_id')->nullable();
            $table->string('kind', 32);
            $table->string('status', 24)->default('open');
            $table->text('reason');
            $table->json('impact')->nullable();
            $table->json('resolution')->nullable();
            $table->string('actor_type', 64)->nullable();
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            $table->foreign(['location_id', 'business_id'], 'operational_exceptions_location_fk')
                ->references(['id', 'business_id'])->on('locations')->restrictOnDelete();
            $table->foreign(['appointment_id', 'business_id'], 'operational_exceptions_appointment_fk')
                ->references(['id', 'business_id'])->on('appointments')->restrictOnDelete();
            $table->index(['business_id', 'location_id', 'status', 'occurred_at'], 'operational_exception_lookup');
        });

        Schema::create('operational_notification_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->string('event_type', 64);
            $table->string('subject_type', 64);
            $table->unsignedBigInteger('subject_id');
            $table->json('payload');
            $table->string('status', 24)->default('pending');
            $table->string('idempotency_key', 128);
            $table->timestamp('occurred_at');
            $table->timestamps();
            $table->unique(['business_id', 'idempotency_key'], 'operational_notification_unique');
            $table->index(['business_id', 'status', 'occurred_at'], 'operational_notification_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operational_notification_events');
        Schema::dropIfExists('operational_exceptions');
        Schema::dropIfExists('walk_in_history');
        Schema::dropIfExists('walk_in_entries');
        Schema::dropIfExists('schedule_blocks');
        Schema::dropIfExists('appointment_changes');

        Schema::table('appointments', function (Blueprint $table): void {
            $table->dropForeign('appointments_rescheduled_from_fk');
            $table->dropForeign('appointments_rescheduled_to_fk');
            $table->dropColumn([
                'rescheduled_from_appointment_id', 'rescheduled_to_appointment_id', 'client_name',
                'client_mobile', 'internal_notes', 'version', 'arrived_at', 'checked_in_at',
                'service_started_at', 'completed_at', 'cancelled_at',
            ]);
        });
    }
};
