<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marketplace_messages', function (Blueprint $table) {
            $table->uuid('email_notification_id')->nullable()->unique();
        });
    }

    public function down(): void
    {
        Schema::table('marketplace_messages', function (Blueprint $table) {
            $table->dropUnique(['email_notification_id']);
            $table->dropColumn('email_notification_id');
        });
    }
};
