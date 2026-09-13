<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('walk_in_entries', function (Blueprint $table): void {
            $table->unsignedBigInteger('client_id')->nullable()->after('appointment_id');
            $table->string('client_email')->nullable()->after('client_mobile');
            $table->foreign(['client_id', 'business_id'], 'walk_ins_client_fk')
                ->references(['id', 'business_id'])->on('clients')->restrictOnDelete();
            $table->index(['business_id', 'client_id', 'arrived_at'], 'walk_in_client_history');
        });
    }

    public function down(): void
    {
        Schema::table('walk_in_entries', function (Blueprint $table): void {
            $table->dropForeign('walk_ins_client_fk');
            $table->dropIndex('walk_in_client_history');
            $table->dropColumn(['client_id', 'client_email']);
        });
    }
};
