<?php

use App\Domain\PlatformAccess\Models\Membership;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            $roles = config('permission.table_names.roles');
            $assignments = config('permission.table_names.model_has_roles');
            $businessKey = config('permission.column_names.team_foreign_key');
            $modelKey = config('permission.column_names.model_morph_key');

            DB::table('businesses')
                ->where('status', 'active')
                ->orderBy('id')
                ->get()
                ->each(function (object $business) use ($roles, $assignments, $businessKey, $modelKey): void {
                    $location = DB::table('locations')
                        ->where('business_id', $business->id)
                        ->where('is_active', true)
                        ->oldest('id')
                        ->first();

                    if (! $location) {
                        $location = DB::table('locations')
                            ->where('business_id', $business->id)
                            ->oldest('id')
                            ->first();

                        if ($location) {
                            DB::table('locations')->where('id', $location->id)->update([
                                'status' => 'active',
                                'is_active' => true,
                                'time_zone' => $location->time_zone ?: ($business->time_zone ?: config('app.timezone')),
                                'updated_at' => now(),
                            ]);
                        } else {
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
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]);
                            $location = (object) ['id' => $locationId];
                        }
                    }

                    $ownerMembershipIds = DB::table('memberships')
                        ->join($assignments, function ($join) use ($assignments, $businessKey, $modelKey): void {
                            $join->on("{$assignments}.{$modelKey}", '=', 'memberships.id')
                                ->where("{$assignments}.model_type", Membership::class)
                                ->on("{$assignments}.{$businessKey}", '=', 'memberships.business_id');
                        })
                        ->join($roles, function ($join) use ($roles, $assignments): void {
                            $join->on("{$roles}.id", '=', "{$assignments}.role_id")
                                ->on("{$roles}.business_id", '=', 'memberships.business_id');
                        })
                        ->where('memberships.business_id', $business->id)
                        ->where('memberships.status', 'active')
                        ->whereNull('memberships.revoked_at')
                        ->where("{$roles}.name", 'owner')
                        ->pluck('memberships.id');

                    foreach ($ownerMembershipIds as $membershipId) {
                        DB::table('location_membership')->insertOrIgnore([
                            'business_id' => $business->id,
                            'location_id' => $location->id,
                            'membership_id' => $membershipId,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                });
        }, 3);
    }

    public function down(): void
    {
        // Corrective tenant data is intentionally preserved on rollback.
    }
};
