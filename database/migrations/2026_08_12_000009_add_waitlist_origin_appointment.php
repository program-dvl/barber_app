<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('waitlist_requests', function (Blueprint $table): void {
            $table->unsignedBigInteger('origin_appointment_id')->nullable()->after('preferred_staff_profile_id');
            $table->foreign(['origin_appointment_id', 'business_id'], 'waitlist_origin_appointment_fk')
                ->references(['id', 'business_id'])->on('appointments')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('waitlist_requests', function (Blueprint $table): void {
            $table->dropForeign('waitlist_origin_appointment_fk');
            $table->dropColumn('origin_appointment_id');
        });
    }
};
