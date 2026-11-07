<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('communication_settings', function (Blueprint $table) {
            $table->boolean('automatic_receipt_email')->default(true);
            $table->boolean('automatic_review_email')->default(false);
            $table->timestamp('review_email_enabled_at', 6)->nullable();
            $table->timestamp('receipt_email_enabled_at', 6)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('communication_settings', fn (Blueprint $table) => $table->dropColumn(['automatic_receipt_email', 'automatic_review_email', 'review_email_enabled_at', 'receipt_email_enabled_at']));
    }
};
