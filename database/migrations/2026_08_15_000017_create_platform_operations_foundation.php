<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('billing_provider_events', function (Blueprint $table): void {
            $table->foreignId('business_id')->nullable()->after('public_id')->constrained()->nullOnDelete();
            $table->index(['business_id', 'status', 'created_at'], 'billing_provider_event_support');
        });

        Schema::create('support_access_grants', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('business_id')->constrained()->restrictOnDelete();
            $table->foreignId('operator_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by_user_id')->constrained('users')->restrictOnDelete();
            $table->string('ticket_reference', 96);
            $table->text('reason');
            $table->json('scopes');
            $table->timestamp('expires_at')->index();
            $table->timestamp('revoked_at')->nullable()->index();
            $table->foreignId('revoked_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('revocation_reason')->nullable();
            $table->timestamps();
            $table->unique(['id', 'business_id']);
            $table->index(['business_id', 'revoked_at', 'expires_at'], 'support_grant_tenant_active');
            $table->index(['operator_user_id', 'revoked_at', 'expires_at'], 'support_grant_operator_active');
        });

        Schema::create('support_access_sessions', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('support_access_grant_id')->constrained()->restrictOnDelete();
            $table->foreignId('business_id')->constrained()->restrictOnDelete();
            $table->foreignId('operator_user_id')->constrained('users')->restrictOnDelete();
            $table->string('session_fingerprint', 64);
            $table->timestamp('started_at');
            $table->timestamp('last_used_at');
            $table->timestamp('ended_at')->nullable()->index();
            $table->string('ended_reason', 64)->nullable();
            $table->timestamps();
            $table->unique(['id', 'business_id']);
            $table->index(['business_id', 'ended_at', 'last_used_at'], 'support_session_tenant_active');
            $table->index(['operator_user_id', 'ended_at'], 'support_session_operator_active');
        });

        Schema::create('platform_account_notes', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('business_id')->constrained()->restrictOnDelete();
            $table->foreignId('author_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('body');
            $table->string('visibility', 32)->default('platform_internal');
            $table->timestamp('retain_until')->nullable();
            $table->timestamps();
            $table->index(['business_id', 'created_at']);
        });

        Schema::create('platform_feature_flags', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('key', 96);
            $table->string('scope_type', 16)->default('global');
            $table->unsignedBigInteger('scope_id')->default(0);
            $table->boolean('enabled')->default(false);
            $table->text('description');
            $table->text('reason');
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['key', 'scope_type', 'scope_id'], 'platform_flag_scope_unique');
        });

        Schema::create('platform_notices', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('title', 160);
            $table->text('message');
            $table->string('severity', 16)->default('info');
            $table->string('audience', 24)->default('all_businesses');
            $table->foreignId('business_id')->nullable()->constrained()->restrictOnDelete();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->foreignId('published_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['published_at', 'starts_at', 'ends_at'], 'platform_notice_active');
        });

        Schema::create('platform_export_requests', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('business_id')->constrained()->restrictOnDelete();
            $table->foreignId('requested_by_user_id')->constrained('users')->restrictOnDelete();
            $table->string('export_type', 48);
            $table->text('reason');
            $table->string('status', 24)->default('queued');
            $table->json('scope_snapshot');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['business_id', 'status', 'created_at'], 'platform_export_tenant_queue');
        });

        Schema::create('platform_coupon_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->restrictOnDelete();
            $table->foreignId('billing_coupon_id')->constrained()->restrictOnDelete();
            $table->foreignId('assigned_by_user_id')->constrained('users')->restrictOnDelete();
            $table->text('reason');
            $table->string('status', 32)->default('pending_provider_confirmation');
            $table->timestamp('assigned_at');
            $table->timestamps();
            $table->unique(['business_id', 'billing_coupon_id'], 'platform_coupon_business_unique');
        });

        Schema::create('platform_replay_attempts', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('business_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('operator_user_id')->constrained('users')->restrictOnDelete();
            $table->string('target_type', 48);
            $table->unsignedBigInteger('target_id');
            $table->string('idempotency_key', 191)->unique();
            $table->text('reason');
            $table->string('status', 24);
            $table->string('result_code', 80)->nullable();
            $table->timestamps();
            $table->index(['target_type', 'target_id']);
        });

        Schema::create('platform_alerts', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('operator_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('business_id')->nullable()->constrained()->nullOnDelete();
            $table->string('kind', 64);
            $table->string('severity', 16);
            $table->text('summary');
            $table->json('evidence');
            $table->timestamp('detected_at')->index();
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['kind', 'severity', 'resolved_at'], 'platform_alert_open');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_alerts');
        Schema::dropIfExists('platform_replay_attempts');
        Schema::dropIfExists('platform_coupon_assignments');
        Schema::dropIfExists('platform_export_requests');
        Schema::dropIfExists('platform_notices');
        Schema::dropIfExists('platform_feature_flags');
        Schema::dropIfExists('platform_account_notes');
        Schema::dropIfExists('support_access_sessions');
        Schema::dropIfExists('support_access_grants');
        Schema::table('billing_provider_events', function (Blueprint $table): void {
            $table->dropIndex('billing_provider_event_support');
            $table->dropConstrainedForeignId('business_id');
        });
    }
};
