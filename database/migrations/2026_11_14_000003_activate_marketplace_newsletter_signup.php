<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marketplace_launch_subscriptions', function (Blueprint $t) {
            $t->timestamp('subscribed_at')->nullable();
            $t->string('subscription_method', 30)->nullable();
            $t->timestamp('welcome_sent_at')->nullable();
            $t->ulid('welcome_failure_reference')->nullable();
        });
        DB::table('marketplace_launch_subscriptions')->whereNotNull('confirmed_at')->update(['subscribed_at' => DB::raw('confirmed_at'), 'subscription_method' => 'email-confirmation']);
    }

    public function down(): void
    {
        Schema::table('marketplace_launch_subscriptions', fn (Blueprint $t) => $t->dropColumn(['subscribed_at', 'subscription_method', 'welcome_sent_at', 'welcome_failure_reference']));
    }
};
