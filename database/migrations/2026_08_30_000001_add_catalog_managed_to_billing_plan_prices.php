<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('billing_plan_prices', 'catalog_managed')) {
            Schema::table('billing_plan_prices', function (Blueprint $table): void {
                $table->boolean('catalog_managed')->default(false)->after('provider_price_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('billing_plan_prices', 'catalog_managed')) {
            Schema::table('billing_plan_prices', function (Blueprint $table): void {
                $table->dropColumn('catalog_managed');
            });
        }
    }
};
