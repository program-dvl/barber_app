<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('owner_registration_intents', function (Blueprint $table): void {
            $table->string('selected_plan_code', 32)->nullable()->after('business_name');
            $table->string('selected_billing_interval', 16)->nullable()->after('selected_plan_code');
        });
    }

    public function down(): void
    {
        Schema::table('owner_registration_intents', function (Blueprint $table): void {
            $table->dropColumn(['selected_plan_code', 'selected_billing_interval']);
        });
    }
};
