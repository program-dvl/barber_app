<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            DB::table('businesses')
                ->whereNotExists(fn ($query) => $query
                    ->selectRaw('1')
                    ->from('locations')
                    ->whereColumn('locations.business_id', 'businesses.id'))
                ->orderBy('id')
                ->get()
                ->each(function (object $business): void {
                    $now = now();
                    $locationId = DB::table('locations')->insertGetId([
                        'business_id' => $business->id,
                        'public_id' => (string) Str::ulid(),
                        'name' => $business->name,
                        'time_zone' => $business->time_zone ?: config('app.timezone'),
                        'status' => 'active',
                        'address' => $business->address,
                        'phone' => $business->phone,
                        'email' => $business->email,
                        'is_active' => true,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);

                    DB::table('memberships')
                        ->where('business_id', $business->id)
                        ->where('status', 'active')
                        ->whereNull('revoked_at')
                        ->orderBy('id')
                        ->get()
                        ->each(function (object $membership) use ($business, $locationId, $now): void {
                            DB::table('location_membership')->insertOrIgnore([
                                'business_id' => $business->id,
                                'location_id' => $locationId,
                                'membership_id' => $membership->id,
                                'created_at' => $now,
                                'updated_at' => $now,
                            ]);
                        });
                });
        }, 3);
    }

    public function down(): void
    {
        // Corrective tenant data is intentionally preserved on rollback.
    }
};
