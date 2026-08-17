<?php

use App\Domain\ClientRecords\Services\ClientFormService;
use App\Domain\PlatformAccess\Models\Business;
use App\Domain\PlatformAccess\Services\BusinessAccessBootstrapper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clients', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->ulid('public_id')->unique();
            $table->string('name');
            $table->string('normalized_name')->index();
            $table->string('email')->nullable();
            $table->string('normalized_email')->nullable();
            $table->string('mobile', 32)->nullable();
            $table->string('normalized_mobile', 32)->nullable();
            $table->date('date_of_birth')->nullable();
            $table->unsignedBigInteger('preferred_staff_profile_id')->nullable();
            $table->text('preferences')->nullable();
            $table->text('communication_preferences')->nullable();
            $table->string('referral_source')->nullable();
            $table->string('marketing_status', 24)->default('unknown');
            $table->string('status', 24)->default('active');
            $table->unsignedBigInteger('merged_into_client_id')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamp('anonymized_at')->nullable();
            $table->timestamps();
            $table->unique(['id', 'business_id']);
            $table->foreign(['preferred_staff_profile_id', 'business_id'], 'clients_preferred_staff_fk')->references(['id', 'business_id'])->on('staff_profiles')->restrictOnDelete();
            $table->foreign(['merged_into_client_id', 'business_id'], 'clients_merge_survivor_fk')->references(['id', 'business_id'])->on('clients')->restrictOnDelete();
            $table->index(['business_id', 'status', 'normalized_name'], 'client_name_search');
            $table->index(['business_id', 'status', 'normalized_email'], 'client_email_match');
            $table->index(['business_id', 'status', 'normalized_mobile'], 'client_mobile_match');
        });

        Schema::table('appointments', function (Blueprint $table): void {
            $table->unsignedBigInteger('client_id')->nullable()->after('source');
            $table->foreign(['client_id', 'business_id'], 'appointments_client_fk')->references(['id', 'business_id'])->on('clients')->restrictOnDelete();
            $table->index(['business_id', 'client_id', 'starts_at_utc'], 'appointment_client_history');
        });

        Schema::create('client_tags', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            $table->string('slug', 100);
            $table->timestamps();
            $table->unique(['id', 'business_id']);
            $table->unique(['business_id', 'slug']);
        });

        Schema::create('client_tag_assignments', function (Blueprint $table): void {
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('client_id');
            $table->unsignedBigInteger('client_tag_id');
            $table->timestamps();
            $table->primary(['business_id', 'client_id', 'client_tag_id'], 'client_tag_assignment_pk');
            $table->foreign(['client_id', 'business_id'], 'client_tag_client_fk')->references(['id', 'business_id'])->on('clients')->cascadeOnDelete();
            $table->foreign(['client_tag_id', 'business_id'], 'client_tag_tag_fk')->references(['id', 'business_id'])->on('client_tags')->cascadeOnDelete();
        });

        Schema::create('client_consents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('client_id');
            $table->unsignedBigInteger('appointment_id')->nullable();
            $table->unsignedBigInteger('authored_by_staff_profile_id')->nullable();
            $table->string('type', 40);
            $table->string('status', 24);
            $table->string('source', 32);
            $table->string('policy_version', 64)->nullable();
            $table->text('wording')->nullable();
            $table->json('evidence')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();
            $table->foreign(['client_id', 'business_id'], 'client_consents_client_fk')->references(['id', 'business_id'])->on('clients')->restrictOnDelete();
            $table->foreign(['appointment_id', 'business_id'], 'client_consents_appointment_fk')->references(['id', 'business_id'])->on('appointments')->restrictOnDelete();
            $table->foreign(['authored_by_staff_profile_id', 'business_id'], 'client_consents_author_fk')->references(['id', 'business_id'])->on('staff_profiles')->restrictOnDelete();
            $table->index(['business_id', 'client_id', 'type', 'occurred_at'], 'client_consent_history');
        });

        Schema::create('client_notes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('client_id');
            $table->unsignedBigInteger('appointment_id')->nullable();
            $table->unsignedBigInteger('authored_by_staff_profile_id')->nullable();
            $table->string('kind', 32);
            $table->string('visibility', 16)->default('standard');
            $table->text('content');
            $table->boolean('is_important')->default(false);
            $table->timestamps();
            $table->unique(['id', 'business_id']);
            $table->foreign(['client_id', 'business_id'], 'client_notes_client_fk')->references(['id', 'business_id'])->on('clients')->restrictOnDelete();
            $table->foreign(['appointment_id', 'business_id'], 'client_notes_appointment_fk')->references(['id', 'business_id'])->on('appointments')->restrictOnDelete();
            $table->foreign(['authored_by_staff_profile_id', 'business_id'], 'client_notes_author_fk')->references(['id', 'business_id'])->on('staff_profiles')->restrictOnDelete();
            $table->index(['business_id', 'client_id', 'created_at'], 'client_note_history');
        });

        Schema::create('client_duplicate_candidates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('first_client_id');
            $table->unsignedBigInteger('second_client_id');
            $table->unsignedBigInteger('surviving_client_id')->nullable();
            $table->unsignedBigInteger('reviewed_by_membership_id')->nullable();
            $table->string('status', 24)->default('pending');
            $table->unsignedTinyInteger('confidence');
            $table->json('reasons');
            $table->json('preview_snapshot')->nullable();
            $table->timestamp('detected_at');
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->unique(['business_id', 'first_client_id', 'second_client_id'], 'client_duplicate_pair_unique');
            $table->foreign(['first_client_id', 'business_id'], 'client_duplicate_first_fk')->references(['id', 'business_id'])->on('clients')->restrictOnDelete();
            $table->foreign(['second_client_id', 'business_id'], 'client_duplicate_second_fk')->references(['id', 'business_id'])->on('clients')->restrictOnDelete();
            $table->foreign(['surviving_client_id', 'business_id'], 'client_duplicate_survivor_fk')->references(['id', 'business_id'])->on('clients')->restrictOnDelete();
            $table->foreign(['reviewed_by_membership_id', 'business_id'], 'client_duplicate_reviewer_fk')->references(['id', 'business_id'])->on('memberships')->restrictOnDelete();
            $table->index(['business_id', 'status', 'confidence'], 'client_duplicate_queue');
        });

        Schema::create('client_form_templates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->ulid('public_id')->unique();
            $table->string('name');
            $table->string('purpose', 48);
            $table->string('status', 16)->default('draft');
            $table->unsignedInteger('current_version')->default(0);
            $table->boolean('request_before_appointment')->default(true);
            $table->timestamps();
            $table->unique(['id', 'business_id']);
            $table->index(['business_id', 'status']);
        });

        Schema::create('client_form_template_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('client_form_template_id');
            $table->unsignedBigInteger('created_by_staff_profile_id')->nullable();
            $table->unsignedInteger('version');
            $table->string('title');
            $table->text('introduction')->nullable();
            $table->json('fields');
            $table->timestamp('published_at');
            $table->timestamps();
            $table->unique(['id', 'business_id']);
            $table->unique(['client_form_template_id', 'version'], 'client_form_version_unique');
            $table->foreign(['client_form_template_id', 'business_id'], 'client_form_version_template_fk')->references(['id', 'business_id'])->on('client_form_templates')->restrictOnDelete();
            $table->foreign(['created_by_staff_profile_id', 'business_id'], 'client_form_version_author_fk')->references(['id', 'business_id'])->on('staff_profiles')->restrictOnDelete();
        });

        Schema::create('client_form_service', function (Blueprint $table): void {
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('client_form_template_id');
            $table->unsignedBigInteger('service_id');
            $table->timestamps();
            $table->primary(['business_id', 'client_form_template_id', 'service_id'], 'client_form_service_pk');
            $table->foreign(['client_form_template_id', 'business_id'], 'client_form_service_template_fk')->references(['id', 'business_id'])->on('client_form_templates')->cascadeOnDelete();
            $table->foreign(['service_id', 'business_id'], 'client_form_service_service_fk')->references(['id', 'business_id'])->on('services')->restrictOnDelete();
        });

        Schema::create('client_form_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->ulid('public_id')->unique();
            $table->unsignedBigInteger('client_id');
            $table->unsignedBigInteger('appointment_id')->nullable();
            $table->unsignedBigInteger('client_form_template_version_id');
            $table->string('status', 24)->default('requested');
            $table->timestamp('requested_at');
            $table->timestamp('due_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->unique(['id', 'business_id']);
            $table->foreign(['client_id', 'business_id'], 'client_form_requests_client_fk')->references(['id', 'business_id'])->on('clients')->restrictOnDelete();
            $table->foreign(['appointment_id', 'business_id'], 'client_form_requests_appointment_fk')->references(['id', 'business_id'])->on('appointments')->restrictOnDelete();
            $table->foreign(['client_form_template_version_id', 'business_id'], 'client_form_requests_version_fk')->references(['id', 'business_id'])->on('client_form_template_versions')->restrictOnDelete();
            $table->index(['business_id', 'client_id', 'status'], 'client_form_request_queue');
        });

        Schema::create('client_form_submissions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->ulid('public_id')->unique();
            $table->unsignedBigInteger('client_id');
            $table->unsignedBigInteger('appointment_id')->nullable();
            $table->unsignedBigInteger('client_form_request_id');
            $table->unsignedBigInteger('client_form_template_version_id');
            $table->json('wording_snapshot');
            $table->text('answers');
            $table->text('signature')->nullable();
            $table->string('signature_hash', 64)->nullable();
            $table->text('submitted_identity_snapshot');
            $table->timestamp('submitted_at');
            $table->timestamps();
            $table->unique(['id', 'business_id']);
            $table->unique('client_form_request_id');
            $table->foreign(['client_id', 'business_id'], 'client_form_submissions_client_fk')->references(['id', 'business_id'])->on('clients')->restrictOnDelete();
            $table->foreign(['appointment_id', 'business_id'], 'client_form_submissions_appointment_fk')->references(['id', 'business_id'])->on('appointments')->restrictOnDelete();
            $table->foreign(['client_form_request_id', 'business_id'], 'client_form_submissions_request_fk')->references(['id', 'business_id'])->on('client_form_requests')->restrictOnDelete();
            $table->foreign(['client_form_template_version_id', 'business_id'], 'client_form_submissions_version_fk')->references(['id', 'business_id'])->on('client_form_template_versions')->restrictOnDelete();
            $table->index(['business_id', 'client_id', 'submitted_at'], 'client_form_submission_history');
        });

        Schema::create('client_form_request_links', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('client_form_request_id');
            $table->string('token_hash', 64)->unique();
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
            $table->foreign(['client_form_request_id', 'business_id'], 'client_form_request_links_request_fk')->references(['id', 'business_id'])->on('client_form_requests')->cascadeOnDelete();
            $table->index(['business_id', 'expires_at']);
        });

        Schema::create('client_attachments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->ulid('public_id')->unique();
            $table->unsignedBigInteger('client_id');
            $table->unsignedBigInteger('appointment_id')->nullable();
            $table->unsignedBigInteger('client_note_id')->nullable();
            $table->unsignedBigInteger('uploaded_by_staff_profile_id')->nullable();
            $table->string('kind', 32);
            $table->string('visibility', 16)->default('standard');
            $table->string('disk', 24)->default('private');
            $table->string('object_key', 700);
            $table->string('original_name');
            $table->string('mime_type', 120);
            $table->unsignedBigInteger('size_bytes');
            $table->string('sha256', 64);
            $table->string('scan_status', 20)->default('clean');
            $table->string('retention_class', 48)->default('client_context');
            $table->timestamp('retention_until')->nullable();
            $table->timestamps();
            $table->unique(['id', 'business_id']);
            $table->foreign(['client_id', 'business_id'], 'client_attachments_client_fk')->references(['id', 'business_id'])->on('clients')->restrictOnDelete();
            $table->foreign(['appointment_id', 'business_id'], 'client_attachments_appointment_fk')->references(['id', 'business_id'])->on('appointments')->restrictOnDelete();
            $table->foreign(['client_note_id', 'business_id'], 'client_attachments_note_fk')->references(['id', 'business_id'])->on('client_notes')->restrictOnDelete();
            $table->foreign(['uploaded_by_staff_profile_id', 'business_id'], 'client_attachments_uploader_fk')->references(['id', 'business_id'])->on('staff_profiles')->restrictOnDelete();
            $table->index(['business_id', 'client_id', 'created_at'], 'client_attachment_history');
        });

        Schema::create('client_attachment_access_links', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('client_attachment_id');
            $table->string('token_hash', 64)->unique();
            $table->string('purpose', 24)->default('download');
            $table->timestamp('expires_at');
            $table->timestamp('revoked_at')->nullable();
            $table->timestamp('last_accessed_at')->nullable();
            $table->unsignedInteger('access_count')->default(0);
            $table->timestamps();
            $table->foreign(['client_attachment_id', 'business_id'], 'client_attachment_links_attachment_fk')->references(['id', 'business_id'])->on('client_attachments')->cascadeOnDelete();
            $table->index(['business_id', 'expires_at']);
        });

        Schema::create('client_privacy_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->ulid('public_id')->unique();
            $table->unsignedBigInteger('client_id');
            $table->unsignedBigInteger('reviewed_by_membership_id')->nullable();
            $table->unsignedBigInteger('export_attachment_id')->nullable();
            $table->string('type', 32);
            $table->string('status', 24)->default('submitted');
            $table->text('request_details')->nullable();
            $table->text('decision_reason')->nullable();
            $table->json('result_summary')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamp('requested_at');
            $table->timestamp('due_at');
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->unique(['id', 'business_id']);
            $table->foreign(['client_id', 'business_id'], 'client_privacy_requests_client_fk')->references(['id', 'business_id'])->on('clients')->restrictOnDelete();
            $table->foreign(['reviewed_by_membership_id', 'business_id'], 'client_privacy_requests_reviewer_fk')->references(['id', 'business_id'])->on('memberships')->restrictOnDelete();
            $table->foreign(['export_attachment_id', 'business_id'], 'client_privacy_requests_export_fk')->references(['id', 'business_id'])->on('client_attachments')->restrictOnDelete();
            $table->index(['business_id', 'status', 'due_at'], 'client_privacy_work_queue');
        });

        $this->backfillAppointments();
        Business::query()->eachById(function (Business $business): void {
            app(BusinessAccessBootstrapper::class)->bootstrap($business);
            app(ClientFormService::class)->seedStarterTemplates($business->id);
        });
    }

    private function backfillAppointments(): void
    {
        $identity = [];
        DB::table('appointments')->whereNull('client_id')->orderBy('id')->chunkById(250, function ($appointments) use (&$identity): void {
            foreach ($appointments as $appointment) {
                if (! $appointment->client_name && ! $appointment->client_mobile && ! $appointment->client_email) {
                    continue;
                }
                $name = trim((string) ($appointment->client_name ?: 'Client'));
                $mobile = preg_replace('/\D+/', '', (string) $appointment->client_mobile) ?: null;
                $email = Str::lower(trim((string) $appointment->client_email)) ?: null;
                $nameKey = Str::lower(preg_replace('/[^\pL\pN]+/u', '', $name) ?: $name);
                $key = $appointment->business_id.'|'.$nameKey.'|'.($mobile ?: $email ?: 'appointment-'.$appointment->id);
                if (! isset($identity[$key])) {
                    $identity[$key] = DB::table('clients')->insertGetId([
                        'business_id' => $appointment->business_id,
                        'public_id' => (string) Str::ulid(),
                        'name' => $name,
                        'normalized_name' => $nameKey,
                        'email' => $appointment->client_email,
                        'normalized_email' => $email,
                        'mobile' => $appointment->client_mobile,
                        'normalized_mobile' => $mobile,
                        'date_of_birth' => $appointment->client_date_of_birth,
                        'communication_preferences' => Crypt::encryptString(json_encode(
                            json_decode((string) ($appointment->communication_preferences ?: '[]'), true) ?: [],
                            JSON_THROW_ON_ERROR
                        )),
                        'referral_source' => $appointment->referral_source,
                        'marketing_status' => $appointment->marketing_opt_in ? 'subscribed' : 'unknown',
                        'status' => 'active',
                        'version' => 1,
                        'created_at' => $appointment->created_at,
                        'updated_at' => $appointment->updated_at,
                    ]);
                }
                DB::table('appointments')->where('id', $appointment->id)->update(['client_id' => $identity[$key]]);
                if ($appointment->marketing_opt_in !== null) {
                    $policy = json_decode((string) ($appointment->public_policy_snapshot ?: '{}'), true) ?: [];
                    DB::table('client_consents')->insert([
                        'business_id' => $appointment->business_id,
                        'client_id' => $identity[$key],
                        'appointment_id' => $appointment->id,
                        'type' => 'marketing',
                        'status' => $appointment->marketing_opt_in ? 'granted' : 'declined',
                        'source' => $appointment->source,
                        'policy_version' => isset($policy['version']) ? (string) $policy['version'] : null,
                        'wording' => $policy['marketing_wording'] ?? null,
                        'evidence' => json_encode(['booking_reference' => $appointment->booking_reference], JSON_THROW_ON_ERROR),
                        'occurred_at' => $appointment->confirmed_at ?? $appointment->created_at,
                        'created_at' => $appointment->created_at,
                        'updated_at' => $appointment->updated_at,
                    ]);
                }
            }
        }, 'id');
    }

    public function down(): void
    {
        Schema::dropIfExists('client_privacy_requests');
        Schema::dropIfExists('client_attachment_access_links');
        Schema::dropIfExists('client_attachments');
        Schema::dropIfExists('client_form_request_links');
        Schema::dropIfExists('client_form_submissions');
        Schema::dropIfExists('client_form_requests');
        Schema::dropIfExists('client_form_service');
        Schema::dropIfExists('client_form_template_versions');
        Schema::dropIfExists('client_form_templates');
        Schema::dropIfExists('client_duplicate_candidates');
        Schema::dropIfExists('client_notes');
        Schema::dropIfExists('client_consents');
        Schema::dropIfExists('client_tag_assignments');
        Schema::dropIfExists('client_tags');
        Schema::table('appointments', function (Blueprint $table): void {
            $table->dropForeign('appointments_client_fk');
            $table->dropIndex('appointment_client_history');
            $table->dropColumn('client_id');
        });
        Schema::dropIfExists('clients');
    }
};
