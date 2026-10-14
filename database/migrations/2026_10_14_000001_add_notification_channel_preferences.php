<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('communication_settings', function (Blueprint $table): void {
            $table->boolean('email_enabled')->default(true);
            $table->boolean('sms_enabled')->default(true);
        });
    }

    public function down(): void
    {
        Schema::table('communication_settings', fn (Blueprint $table) => $table->dropColumn(['email_enabled', 'sms_enabled']));
    }
};
