<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('communication_settings')) {
            return;
        }

        Schema::table('communication_settings', function (Blueprint $table): void {
            $table->string('mobile_channel', 24)->default('sms')->change();
            $table->boolean('smart_fallback_enabled')->default(false)->change();
        });

        DB::table('communication_settings')->update([
            'mobile_channel' => 'sms',
            'fallback_mobile_channel' => null,
            'smart_fallback_enabled' => false,
            'two_way_enabled' => false,
            'marketing_enabled' => false,
            'reminder_offsets_minutes' => json_encode([1440], JSON_THROW_ON_ERROR),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // This is an intentional product-policy migration. Previous per-business
        // notification choices cannot be reconstructed safely.
    }
};
