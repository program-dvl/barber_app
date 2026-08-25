<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('billing_plans')) {
            return;
        }

        DB::table('billing_plans')
            ->where('name', 'Good Hours trial')
            ->update(['name' => 'ClipperDesk trial', 'updated_at' => now()]);

        DB::table('billing_plans')
            ->where('name', 'Good Hours Starter')
            ->update(['name' => 'ClipperDesk Starter', 'updated_at' => now()]);

        DB::table('billing_plans')
            ->where('name', 'Good Hours Pro')
            ->update(['name' => 'ClipperDesk Pro', 'updated_at' => now()]);
    }

    public function down(): void
    {
        // Product-facing display names are intentionally not rolled back.
        // Historical provider and subscription identifiers remain unchanged.
    }
};
