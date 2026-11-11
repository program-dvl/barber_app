<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('marketplace_customers')) {
            Schema::create('marketplace_customers', function (Blueprint $table) {
                $table->id();
                $table->ulid('public_id')->unique();
                $table->string('email')->unique();
                $table->string('name')->nullable();
                $table->timestamp('email_verified_at');
                $table->rememberToken();
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('marketplace_login_links')) {
            Schema::create('marketplace_login_links', function (Blueprint $table) {
                $table->id();
                $table->string('email');
                $table->char('token_hash', 64)->unique();
                $table->timestamp('expires_at');
                $table->timestamp('used_at')->nullable();
                $table->timestamps();
                $table->index(['email', 'created_at']);
            });
        }
        if (! Schema::hasColumn('businesses', 'marketplace_listed')) {
            Schema::table('businesses', function (Blueprint $table) {
                $table->boolean('marketplace_listed')->default(false);
                $table->timestamp('marketplace_consented_at')->nullable();
                $table->unsignedInteger('marketplace_version')->default(1);
            });
        }
        if (! Schema::hasColumn('public_booking_flows', 'marketplace_customer_id')) {
            Schema::table('public_booking_flows', function (Blueprint $table) {
                $table->foreignId('marketplace_customer_id')->nullable()->constrained('marketplace_customers')->restrictOnDelete();
                $table->string('discovery_source', 20)->nullable();
            });
        }
        if (! Schema::hasTable('marketplace_bookings')) {
            Schema::create('marketplace_bookings', function (Blueprint $table) {
                $table->id();
                $table->foreignId('customer_id')->constrained('marketplace_customers')->restrictOnDelete();
                $table->foreignId('business_id')->constrained()->restrictOnDelete();
                $table->unsignedBigInteger('appointment_id')->unique();

                $table->boolean('messages_paused')->default(false);
                $table->timestamps();
                $table->index(['customer_id', 'business_id']);
            });
        }
        if (! Schema::hasTable('marketplace_favourites')) {
            Schema::create('marketplace_favourites', function (Blueprint $table) {
                $table->id();
                $table->foreignId('customer_id')->constrained('marketplace_customers')->cascadeOnDelete();
                $table->foreignId('business_id')->constrained()->cascadeOnDelete();
                $table->timestamps();
                $table->unique(['customer_id', 'business_id']);
            });
        }
        if (! Schema::hasTable('marketplace_messages')) {
            Schema::create('marketplace_messages', function (Blueprint $table) {
                $table->id();
                $table->ulid('public_id')->unique();
                $table->foreignId('booking_id')->constrained('marketplace_bookings')->restrictOnDelete();
                $table->string('sender', 12);
                $table->foreignId('staff_user_id')->nullable()->constrained('users')->restrictOnDelete();
                $table->text('body');
                $table->uuid('idempotency_key');
                $table->timestamp('read_at')->nullable();
                $table->timestamps();
                $table->unique(['booking_id', 'sender', 'idempotency_key'], 'marketplace_message_replay');
            });
        }
        if (! Schema::hasTable('marketplace_reports')) {
            Schema::create('marketplace_reports', function (Blueprint $table) {
                $table->id();
                $table->ulid('public_id')->unique();
                $table->foreignId('customer_id')->constrained('marketplace_customers')->restrictOnDelete();
                $table->foreignId('business_id')->constrained()->restrictOnDelete();
                $table->foreignId('booking_id')->nullable()->constrained('marketplace_bookings')->restrictOnDelete();
                $table->string('reason', 30);
                $table->text('details');
                $table->string('status', 20)->default('open');
                $table->timestamps();
            });
        }
        $foreignKeys = collect(Schema::getForeignKeys('marketplace_bookings'))->pluck('name');
        if (! $foreignKeys->contains('marketplace_booking_appointment_tenant_fk')) {
            Schema::table('marketplace_bookings', function (Blueprint $table) {
                $table->foreign(['appointment_id', 'business_id'], 'marketplace_booking_appointment_tenant_fk')->references(['id', 'business_id'])->on('appointments')->restrictOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_reports');
        Schema::dropIfExists('marketplace_messages');
        Schema::dropIfExists('marketplace_favourites');
        Schema::dropIfExists('marketplace_bookings');
        Schema::table('public_booking_flows', function (Blueprint $table) {
            $table->dropConstrainedForeignId('marketplace_customer_id');
            $table->dropColumn('discovery_source');
        });
        Schema::table('businesses', fn (Blueprint $table) => $table->dropColumn(['marketplace_listed', 'marketplace_consented_at', 'marketplace_version']));
        Schema::dropIfExists('marketplace_login_links');
        Schema::dropIfExists('marketplace_customers');
    }
};
