<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // MySQL DDL is not transactional. A resumed initial table creation may
        // have stopped before Laravel's later unique/index statements ran.
        $indexes = collect(Schema::getIndexes('marketplace_bookings'));
        if (! $indexes->contains(fn ($index) => $index['unique'] && $index['columns'] === ['appointment_id'])) {
            Schema::table('marketplace_bookings', fn (Blueprint $table) => $table->unique('appointment_id', 'marketplace_bookings_appointment_id_unique'));
        }
        if (! $indexes->contains(fn ($index) => $index['columns'] === ['customer_id', 'business_id'])) {
            Schema::table('marketplace_bookings', fn (Blueprint $table) => $table->index(['customer_id', 'business_id'], 'marketplace_bookings_customer_id_business_id_index'));
        }
    }

    public function down(): void
    {
        // These are mandatory ownership safeguards, not disposable feature data.
        // The original table migration owns their eventual removal.
    }
};
