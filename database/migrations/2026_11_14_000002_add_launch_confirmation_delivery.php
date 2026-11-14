<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marketplace_launch_subscriptions', function (Blueprint $t) {
            $t->text('confirmation_token')->nullable(); // Encrypted; resends keep an unexpired link valid.
            $t->timestamp('confirmation_sent_at')->nullable(); // Transport acceptance, not inbox delivery.
            $t->string('confirmation_message_id', 255)->nullable();
            $t->ulid('confirmation_failure_reference')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('marketplace_launch_subscriptions', fn (Blueprint $t) => $t->dropColumn(['confirmation_token', 'confirmation_sent_at', 'confirmation_message_id', 'confirmation_failure_reference']));
    }
};
