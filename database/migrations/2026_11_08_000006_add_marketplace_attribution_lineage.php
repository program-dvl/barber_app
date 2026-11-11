<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $t) {
            $t->unsignedBigInteger('marketplace_attribution_root_id')->nullable();
            $t->foreign(['marketplace_attribution_root_id', 'business_id'], 'mp_appointment_root_tenant_fk')->references(['id', 'business_id'])->on('appointments');
            $t->index(['business_id', 'marketplace_attribution_root_id'], 'mp_appointment_root_lookup');
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $t) {
            $t->dropForeign('mp_appointment_root_tenant_fk');
            $t->dropIndex('mp_appointment_root_lookup');
            $t->dropColumn('marketplace_attribution_root_id');
        });
    }
};
