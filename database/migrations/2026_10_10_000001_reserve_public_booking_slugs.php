<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // MySQL DDL is not transactional. Allow a failed backfill to be resumed
        // after an operator resolves the reported legacy collision.
        if (! Schema::hasTable('booking_slug_reservations')) {
            Schema::create('booking_slug_reservations', function (Blueprint $table): void {
                $table->string('slug', 80)->primary();
                $table->foreignId('business_id')->nullable()->constrained()->nullOnDelete();
            });
        }
        // One unique namespace covers both current links and historical aliases.
        // Fail visibly on legacy cross-business collisions; never steal a link.
        foreach (['booking_slug_aliases' => 'slug', 'businesses' => 'booking_slug'] as $table => $column) {
            DB::table($table)->whereNotNull($column)->orderBy('id')->chunkById(200, function ($rows) use ($table, $column): void {
                foreach ($rows as $row) {
                    $owner = $table === 'businesses' ? $row->id : $row->business_id;
                    DB::table('booking_slug_reservations')->insertOrIgnore(['slug' => $row->{$column}, 'business_id' => $owner]);
                    if ((int) DB::table('booking_slug_reservations')->where('slug', $row->{$column})->value('business_id') !== (int) $owner) {
                        throw new RuntimeException('Resolve conflicting historical booking slugs before deployment.');
                    }
                }
            });
        }
        DB::table('public_booking_flows')->orderBy('id')->chunkById(200, function ($rows): void {
            foreach ($rows as $row) {
                $state = json_decode($row->state ?: '{}', true, flags: JSON_THROW_ON_ERROR);
                if (array_key_exists('secret', $state)) {
                    unset($state['secret'], $state['flow']);
                    DB::table('public_booking_flows')->where('id', $row->id)->update(['state' => json_encode($state, JSON_THROW_ON_ERROR)]);
                }
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_slug_reservations');
    }
};
