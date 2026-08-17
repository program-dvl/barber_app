<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('inventory_levels')) {
            return;
        }

        Schema::create('inventory_levels', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('location_id');
            $table->unsignedBigInteger('inventory_product_id');
            $table->bigInteger('current_stock')->default(0);
            $table->timestamps();
            $table->unique(['business_id', 'location_id', 'inventory_product_id'], 'inventory_level_unique');
            $table->foreign(['location_id', 'business_id'], 'inv_level_location_fk')
                ->references(['id', 'business_id'])->on('locations')->restrictOnDelete();
            $table->foreign(['inventory_product_id', 'business_id'], 'inv_level_product_fk')
                ->references(['id', 'business_id'])->on('inventory_products')->restrictOnDelete();
            $table->index(['business_id', 'location_id', 'current_stock'], 'inventory_level_stock_lookup');
        });
    }

    public function down(): void
    {
        // This migration repairs drift from an already-recorded migration.
        // Rolling it back must not remove inventory balances.
    }
};
