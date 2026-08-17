<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('businesses', function (Blueprint $table): void {
            $table->string('booking_slug')->nullable()->unique()->after('slug');
            $table->string('business_type', 64)->nullable();
            $table->string('country_code', 2)->nullable();
            $table->string('locale', 16)->nullable();
            $table->string('currency_code', 3)->nullable();
            $table->string('time_zone', 64)->nullable();
            $table->unsignedTinyInteger('week_starts_on')->nullable();
            $table->unsignedSmallInteger('appointment_interval_minutes')->nullable();
            $table->string('tax_posture', 24)->nullable();
            $table->string('phone', 32)->nullable();
            $table->string('email')->nullable();
            $table->string('website_url')->nullable();
            $table->json('social_links')->nullable();
            $table->text('address')->nullable();
            $table->string('map_url')->nullable();
            $table->text('default_cancellation_policy')->nullable();
            $table->string('terms_url')->nullable();
            $table->string('privacy_url')->nullable();
            $table->string('logo_path')->nullable();
            $table->string('cover_image_path')->nullable();
            $table->timestamp('configuration_published_at')->nullable()->index();
        });

        Schema::table('locations', function (Blueprint $table): void {
            $table->string('status', 24)->default('active')->after('time_zone');
            $table->text('address')->nullable();
            $table->string('phone', 32)->nullable();
            $table->string('email')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
        });

        Schema::table('staff_profiles', function (Blueprint $table): void {
            $table->string('photo_path')->nullable();
        });

        Schema::create('booking_slug_aliases', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->string('slug')->unique();
            $table->timestamp('redirected_at')->useCurrent();
            $table->timestamps();
            $table->index(['business_id', 'redirected_at']);
        });

        Schema::create('onboarding_sessions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('current_step', 32)->default('business_details');
            $table->json('completed_steps')->nullable();
            $table->timestamp('started_at')->useCurrent();
            $table->timestamp('last_saved_at')->useCurrent();
            $table->timestamp('previewed_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });

        Schema::create('location_hours', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('location_id');
            $table->unsignedTinyInteger('day_of_week');
            $table->time('opens_at');
            $table->time('closes_at');
            $table->unsignedTinyInteger('sequence')->default(1);
            $table->date('effective_from')->nullable();
            $table->date('effective_until')->nullable();
            $table->timestamps();
            $table->foreign(['location_id', 'business_id'], 'location_hours_location_fk')->references(['id', 'business_id'])->on('locations')->cascadeOnDelete();
            $table->unique(['location_id', 'day_of_week', 'sequence', 'effective_from'], 'location_hours_unique_period');
            $table->index(['business_id', 'location_id', 'day_of_week']);
        });

        Schema::create('location_schedule_exceptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('location_id');
            $table->string('kind', 32);
            $table->date('starts_on');
            $table->date('ends_on');
            $table->time('opens_at')->nullable();
            $table->time('closes_at')->nullable();
            $table->string('name');
            $table->text('reason')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent();
            $table->foreign(['location_id', 'business_id'], 'location_exception_location_fk')->references(['id', 'business_id'])->on('locations')->cascadeOnDelete();
            $table->index(['business_id', 'location_id', 'starts_on', 'ends_on'], 'location_exception_lookup');
        });

        Schema::create('physical_resources', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('location_id');
            $table->ulid('public_id')->unique();
            $table->string('type', 64);
            $table->string('name');
            $table->unsignedSmallInteger('quantity')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['id', 'business_id']);
            $table->foreign(['location_id', 'business_id'], 'physical_resource_location_fk')->references(['id', 'business_id'])->on('locations')->restrictOnDelete();
            $table->index(['business_id', 'location_id', 'is_active']);
        });

        Schema::create('resource_hours', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('physical_resource_id');
            $table->unsignedTinyInteger('day_of_week');
            $table->time('opens_at');
            $table->time('closes_at');
            $table->unsignedTinyInteger('sequence')->default(1);
            $table->timestamps();
            $table->foreign(['physical_resource_id', 'business_id'], 'resource_hours_resource_fk')->references(['id', 'business_id'])->on('physical_resources')->cascadeOnDelete();
            $table->unique(['physical_resource_id', 'day_of_week', 'sequence']);
        });

        Schema::create('resource_maintenance_blocks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('physical_resource_id');
            $table->timestamp('starts_at_utc');
            $table->timestamp('ends_at_utc');
            $table->string('time_zone', 64);
            $table->string('local_starts_at', 32);
            $table->string('local_ends_at', 32);
            $table->string('reason');
            $table->timestamps();
            $table->foreign(['physical_resource_id', 'business_id'], 'resource_maintenance_resource_fk')->references(['id', 'business_id'])->on('physical_resources')->cascadeOnDelete();
            $table->index(['business_id', 'physical_resource_id', 'starts_at_utc'], 'resource_maintenance_lookup');
        });

        Schema::create('service_categories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->ulid('public_id')->unique();
            $table->string('name');
            $table->unsignedSmallInteger('display_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['id', 'business_id']);
            $table->unique(['business_id', 'name']);
        });

        Schema::create('services', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('service_category_id')->nullable();
            $table->ulid('public_id')->unique();
            $table->string('kind', 16)->default('service');
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('image_path')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('online_visible')->default(true);
            $table->boolean('consultation_required')->default(false);
            $table->string('client_eligibility', 24)->default('all');
            $table->string('price_type', 16)->default('fixed');
            $table->unsignedBigInteger('price_minor');
            $table->string('currency_code', 3);
            $table->string('tax_category', 64)->nullable();
            $table->boolean('tax_inclusive')->default(false);
            $table->unsignedSmallInteger('duration_minutes');
            $table->unsignedSmallInteger('processing_minutes')->default(0);
            $table->unsignedSmallInteger('cleanup_minutes')->default(0);
            $table->unsignedInteger('minimum_notice_minutes')->default(0);
            $table->unsignedSmallInteger('maximum_advance_days')->default(365);
            $table->string('deposit_type', 16)->default('none');
            $table->unsignedBigInteger('deposit_value')->default(0);
            $table->dateTime('effective_from')->nullable();
            $table->dateTime('effective_until')->nullable();
            $table->timestamps();
            $table->unique(['id', 'business_id']);
            $table->foreign(['service_category_id', 'business_id'], 'services_category_fk')->references(['id', 'business_id'])->on('service_categories')->restrictOnDelete();
            $table->index(['business_id', 'kind', 'is_active', 'online_visible'], 'service_booking_lookup');
        });

        Schema::create('service_segments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('service_id');
            $table->string('kind', 16);
            $table->unsignedSmallInteger('sequence');
            $table->unsignedSmallInteger('duration_minutes');
            $table->boolean('occupies_staff')->default(true);
            $table->timestamps();
            $table->unique(['id', 'business_id']);
            $table->foreign(['service_id', 'business_id'], 'service_segments_service_fk')->references(['id', 'business_id'])->on('services')->cascadeOnDelete();
            $table->unique(['service_id', 'sequence']);
        });

        Schema::create('location_service', function (Blueprint $table): void {
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('location_id');
            $table->unsignedBigInteger('service_id');
            $table->boolean('is_eligible')->default(true);
            $table->unsignedBigInteger('price_minor')->nullable();
            $table->timestamps();
            $table->primary(['business_id', 'location_id', 'service_id']);
            $table->foreign(['location_id', 'business_id'], 'location_service_location_fk')->references(['id', 'business_id'])->on('locations')->cascadeOnDelete();
            $table->foreign(['service_id', 'business_id'], 'location_service_service_fk')->references(['id', 'business_id'])->on('services')->cascadeOnDelete();
        });

        Schema::create('service_addons', function (Blueprint $table): void {
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('service_id');
            $table->unsignedBigInteger('addon_service_id');
            $table->timestamps();
            $table->primary(['business_id', 'service_id', 'addon_service_id']);
            $table->foreign(['service_id', 'business_id'], 'service_addons_service_fk')->references(['id', 'business_id'])->on('services')->cascadeOnDelete();
            $table->foreign(['addon_service_id', 'business_id'], 'service_addons_addon_fk')->references(['id', 'business_id'])->on('services')->cascadeOnDelete();
        });

        Schema::create('service_resource_requirements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('service_id');
            $table->unsignedBigInteger('service_segment_id')->nullable();
            $table->unsignedBigInteger('physical_resource_id');
            $table->unsignedSmallInteger('quantity')->default(1);
            $table->timestamps();
            $table->foreign(['service_id', 'business_id'], 'service_resource_service_fk')->references(['id', 'business_id'])->on('services')->cascadeOnDelete();
            $table->foreign(['service_segment_id', 'business_id'], 'service_resource_segment_fk')->references(['id', 'business_id'])->on('service_segments')->cascadeOnDelete();
            $table->foreign(['physical_resource_id', 'business_id'], 'service_resource_physical_fk')->references(['id', 'business_id'])->on('physical_resources')->restrictOnDelete();
            $table->unique(['service_id', 'service_segment_id', 'physical_resource_id'], 'service_segment_resource_unique');
        });

        Schema::create('staff_service_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('staff_profile_id');
            $table->unsignedBigInteger('service_id');
            $table->boolean('is_qualified')->default(true);
            $table->boolean('is_active')->default(true);
            $table->boolean('online_visible')->default(true);
            $table->unsignedBigInteger('price_minor')->nullable();
            $table->unsignedSmallInteger('duration_minutes')->nullable();
            $table->unsignedSmallInteger('processing_minutes')->nullable();
            $table->unsignedSmallInteger('cleanup_minutes')->nullable();
            $table->decimal('commission_rate', 7, 4)->nullable();
            $table->dateTime('effective_from')->nullable();
            $table->dateTime('effective_until')->nullable();
            $table->timestamps();
            $table->unique(['id', 'business_id']);
            $table->foreign(['staff_profile_id', 'business_id'], 'staff_service_staff_fk')->references(['id', 'business_id'])->on('staff_profiles')->cascadeOnDelete();
            $table->foreign(['service_id', 'business_id'], 'staff_service_service_fk')->references(['id', 'business_id'])->on('services')->cascadeOnDelete();
            $table->index(['business_id', 'staff_profile_id', 'service_id'], 'staff_service_effective_lookup');
        });

        Schema::create('staff_availability_rules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('staff_profile_id');
            $table->unsignedBigInteger('location_id')->nullable();
            $table->string('kind', 24);
            $table->unsignedTinyInteger('day_of_week')->nullable();
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->time('starts_at')->nullable();
            $table->time('ends_at')->nullable();
            $table->unsignedTinyInteger('sequence')->default(1);
            $table->string('reason')->nullable();
            $table->timestamps();
            $table->foreign(['staff_profile_id', 'business_id'], 'staff_availability_staff_fk')->references(['id', 'business_id'])->on('staff_profiles')->cascadeOnDelete();
            $table->foreign(['location_id', 'business_id'], 'staff_availability_location_fk')->references(['id', 'business_id'])->on('locations')->cascadeOnDelete();
            $table->index(['business_id', 'staff_profile_id', 'kind', 'day_of_week'], 'staff_availability_lookup');
        });

        Schema::create('configuration_change_previews', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->ulid('public_id')->unique();
            $table->string('change_type', 48);
            $table->string('subject_type', 96);
            $table->unsignedBigInteger('subject_id');
            $table->json('proposed_change');
            $table->json('affected_appointment_ids')->nullable();
            $table->unsignedInteger('affected_count')->default(0);
            $table->string('status', 24)->default('previewed');
            $table->text('resolution_note')->nullable();
            $table->timestamp('expires_at');
            $table->timestamps();
            $table->index(['business_id', 'subject_type', 'subject_id'], 'configuration_preview_subject_lookup');
        });

        Schema::create('configuration_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->string('snapshot_type', 48);
            $table->string('subject_type', 96);
            $table->unsignedBigInteger('subject_id');
            $table->json('values');
            $table->timestamp('effective_at');
            $table->timestamp('captured_at')->useCurrent();
            $table->index(['business_id', 'subject_type', 'subject_id', 'effective_at'], 'configuration_snapshot_lookup');
        });

        Schema::create('configuration_imports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->ulid('public_id')->unique();
            $table->string('entity_type', 24);
            $table->string('idempotency_key', 128);
            $table->string('source_name');
            $table->string('source_path')->nullable();
            $table->string('source_hash', 64);
            $table->json('mapping');
            $table->string('status', 24)->default('previewed');
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('created_rows')->default(0);
            $table->unsignedInteger('updated_rows')->default(0);
            $table->unsignedInteger('skipped_rows')->default(0);
            $table->unsignedInteger('failed_rows')->default(0);
            $table->unsignedInteger('duplicate_rows')->default(0);
            $table->string('error_export_path')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->unique(['business_id', 'idempotency_key']);
            $table->unique(['id', 'business_id']);
            $table->index(['business_id', 'entity_type', 'status']);
        });

        Schema::create('configuration_import_rows', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('configuration_import_id');
            $table->unsignedInteger('row_number');
            $table->string('row_key', 191);
            $table->string('fingerprint', 64);
            $table->json('normalized_data');
            $table->json('errors')->nullable();
            $table->string('status', 24)->default('valid');
            $table->string('result_action', 24)->nullable();
            $table->timestamps();
            $table->foreign(['configuration_import_id', 'business_id'], 'configuration_import_rows_import_fk')->references(['id', 'business_id'])->on('configuration_imports')->cascadeOnDelete();
            $table->unique(['configuration_import_id', 'row_number'], 'configuration_import_row_number_unique');
            $table->unique(['id', 'business_id']);
            $table->index(['business_id', 'row_key', 'fingerprint']);
        });

        Schema::create('import_duplicate_candidates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('configuration_import_row_id');
            $table->string('candidate_type', 96);
            $table->string('candidate_key', 191);
            $table->json('matched_fields');
            $table->string('resolution', 24)->default('review');
            $table->timestamps();
            $table->foreign(['configuration_import_row_id', 'business_id'], 'import_duplicate_row_fk')->references(['id', 'business_id'])->on('configuration_import_rows')->cascadeOnDelete();
            $table->index(['business_id', 'resolution']);
        });

        Schema::create('imported_configuration_records', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->string('entity_type', 24);
            $table->string('row_key', 191);
            $table->string('fingerprint', 64);
            $table->json('data');
            $table->timestamps();
            $table->unique(['business_id', 'entity_type', 'row_key'], 'imported_configuration_record_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('imported_configuration_records');
        Schema::dropIfExists('import_duplicate_candidates');
        Schema::dropIfExists('configuration_import_rows');
        Schema::dropIfExists('configuration_imports');
        Schema::dropIfExists('configuration_snapshots');
        Schema::dropIfExists('configuration_change_previews');
        Schema::dropIfExists('staff_availability_rules');
        Schema::dropIfExists('staff_service_assignments');
        Schema::dropIfExists('service_resource_requirements');
        Schema::dropIfExists('service_addons');
        Schema::dropIfExists('location_service');
        Schema::dropIfExists('service_segments');
        Schema::dropIfExists('services');
        Schema::dropIfExists('service_categories');
        Schema::dropIfExists('resource_maintenance_blocks');
        Schema::dropIfExists('resource_hours');
        Schema::dropIfExists('physical_resources');
        Schema::dropIfExists('location_schedule_exceptions');
        Schema::dropIfExists('location_hours');
        Schema::dropIfExists('onboarding_sessions');
        Schema::dropIfExists('booking_slug_aliases');

        Schema::table('staff_profiles', fn (Blueprint $table) => $table->dropColumn('photo_path'));
        Schema::table('locations', fn (Blueprint $table) => $table->dropColumn(['status', 'address', 'phone', 'email', 'latitude', 'longitude']));
        Schema::table('businesses', fn (Blueprint $table) => $table->dropColumn([
            'booking_slug', 'business_type', 'country_code', 'locale', 'currency_code', 'time_zone',
            'week_starts_on', 'appointment_interval_minutes', 'tax_posture', 'phone', 'email',
            'website_url', 'social_links', 'address', 'map_url', 'default_cancellation_policy',
            'terms_url', 'privacy_url', 'logo_path', 'cover_image_path', 'configuration_published_at',
        ]));
    }
};
