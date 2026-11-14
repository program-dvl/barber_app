<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketplace_launch_subscriptions', function (Blueprint $t) {
            $t->ulid('id')->primary();
            $t->char('email_hash', 64);
            $t->text('email'); // Encrypted, never returned to the browser or command output.
            $t->string('area', 150);
            $t->char('area_key', 64);
            $t->char('confirm_hash', 64)->nullable()->unique();
            $t->timestamp('confirm_expires_at')->nullable();
            $t->char('unsubscribe_hash', 64)->unique();
            $t->string('consent_version', 30);
            $t->timestamp('consented_at');
            $t->timestamp('confirmed_at')->nullable();
            $t->timestamp('unsubscribed_at')->nullable();
            $t->timestamp('attempted_at')->nullable();
            $t->timestamp('notified_at')->nullable();
            $t->timestamps();
            $t->unique(['email_hash', 'area_key'], 'mp_launch_email_area_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_launch_subscriptions');
    }
};
