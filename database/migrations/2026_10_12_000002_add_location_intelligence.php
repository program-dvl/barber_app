<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('locations', function (Blueprint $table): void {
            $table->string('google_place_id')->nullable();
            $table->string('address_line_1')->nullable();
            $table->string('address_line_2')->nullable();
            $table->string('city', 150)->nullable();
            $table->string('state', 150)->nullable();
            $table->string('postal_code', 32)->nullable();
            $table->char('country_code', 2)->nullable();
            $table->decimal('entrance_latitude', 10, 7)->nullable();
            $table->decimal('entrance_longitude', 10, 7)->nullable();
            $table->text('entrance_instructions')->nullable();
            $table->text('parking_instructions')->nullable();
            $table->boolean('is_primary')->default(false);
        });
        Schema::table('appointments', fn (Blueprint $table) => $table->json('location_snapshot')->nullable());
        // Freeze the best known legacy address; no historical address is invented.
        DB::table('appointments')->whereNull('location_snapshot')->orderBy('id')->chunkById(500, function ($appointments): void {
            $locations = DB::table('locations')->whereIn('id', $appointments->pluck('location_id'))->get()->keyBy('id');
            foreach ($appointments as $appointment) {
                $location = $locations->get($appointment->location_id);
                if ($location && (int) $location->business_id === (int) $appointment->business_id) {
                    DB::table('appointments')->where('id', $appointment->id)->update(['location_snapshot' => json_encode([
                        'public_id' => $location->public_id, 'name' => $location->name,
                        'address' => $location->address, 'time_zone' => $appointment->time_zone,
                    ], JSON_THROW_ON_ERROR)]);
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('appointments', fn (Blueprint $table) => $table->dropColumn('location_snapshot'));
        Schema::table('locations', fn (Blueprint $table) => $table->dropColumn(['google_place_id', 'address_line_1', 'address_line_2', 'city', 'state', 'postal_code', 'country_code', 'entrance_latitude', 'entrance_longitude', 'entrance_instructions', 'parking_instructions', 'is_primary']));
    }
};
