<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_transactions', fn (Blueprint $table) => $table->index(['business_id', 'status', 'occurred_at', 'sale_id'], 'report_payment_activity_lookup'));
        Schema::table('walk_in_entries', fn (Blueprint $table) => $table->index(['business_id', 'location_id', 'arrived_at'], 'report_walk_in_arrival_lookup'));
        Schema::table('sale_line_refunds', fn (Blueprint $table) => $table->index(['business_id', 'sale_line_id'], 'report_line_return_lookup'));
    }

    public function down(): void
    {
        Schema::table('payment_transactions', fn (Blueprint $table) => $table->dropIndex('report_payment_activity_lookup'));
        Schema::table('walk_in_entries', fn (Blueprint $table) => $table->dropIndex('report_walk_in_arrival_lookup'));
        Schema::table('sale_line_refunds', fn (Blueprint $table) => $table->dropIndex('report_line_return_lookup'));
    }
};
