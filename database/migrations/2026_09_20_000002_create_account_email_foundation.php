<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_login_activities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->char('fingerprint', 64);
            $table->string('device_label', 160);
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 1024)->nullable();
            $table->timestamp('first_seen_at');
            $table->timestamp('last_seen_at');
            $table->timestamp('last_notified_at')->nullable();
            $table->unsignedInteger('sign_in_count')->default(1);
            $table->timestamps();

            $table->unique(['user_id', 'fingerprint']);
            $table->index(['user_id', 'last_seen_at']);
        });

        Schema::create('email_notification_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->uuid('notification_id')->unique();
            $table->foreignId('business_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('notification_type', 255);
            $table->string('stream', 32)->default('account');
            $table->string('mailer', 64);
            $table->char('recipient_hash', 64)->nullable();
            $table->string('recipient_masked', 320)->nullable();
            $table->string('status', 32);
            $table->string('provider_message_id', 255)->nullable()->index();
            $table->unsignedSmallInteger('attempts')->default(1);
            $table->timestamp('sending_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->string('last_error_code', 120)->nullable();
            $table->string('last_error', 1000)->nullable();
            $table->timestamps();

            $table->index(['business_id', 'status', 'created_at'], 'email_deliveries_business_status_index');
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_notification_deliveries');
        Schema::dropIfExists('account_login_activities');
    }
};
