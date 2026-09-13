<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('sale_lines', 'service_id')) {
            return;
        }

        Schema::table('sale_lines', function (Blueprint $table): void {
            $table->unsignedBigInteger('service_id')->nullable()->after('source_id');
            $table->foreign(['service_id', 'business_id'], 'sale_line_service_fk')
                ->references(['id', 'business_id'])->on('services')->restrictOnDelete();
            $table->index(['business_id', 'service_id'], 'sale_line_service_lookup');
        });
    }

    public function down(): void
    {
        // The original commerce migration owns this shared column. This repair
        // migration intentionally does not remove it during an isolated rollback.
    }
};
