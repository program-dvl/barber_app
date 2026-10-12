<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('owner_registration_intents', function (Blueprint $table) {
            $table->unsignedSmallInteger('selected_locations')->nullable();
            $table->unsignedSmallInteger('selected_staff')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('owner_registration_intents', fn (Blueprint $table) => $table->dropColumn(['selected_locations', 'selected_staff']));
    }
};
