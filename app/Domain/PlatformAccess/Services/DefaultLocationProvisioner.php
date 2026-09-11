<?php

namespace App\Domain\PlatformAccess\Services;

use App\Domain\PlatformAccess\Models\Business;
use App\Domain\PlatformAccess\Models\Location;
use App\Domain\PlatformAccess\Models\Membership;
use App\Models\User;
use App\Support\Audit\AuditWriter;
use Illuminate\Support\Facades\DB;

class DefaultLocationProvisioner
{
    public function __construct(private readonly AuditWriter $audit) {}

    public function provision(Business $business, Membership $membership, ?User $actor = null): Location
    {
        return DB::transaction(function () use ($business, $membership, $actor): Location {
            $locked = Business::query()->lockForUpdate()->findOrFail($business->getKey());
            $location = $locked->locations()->where('is_active', true)->oldest('id')->first();
            $created = false;
            $reactivated = false;

            if (! $location) {
                $location = $locked->locations()->where('name', $locked->name)->first();

                if ($location) {
                    $location->forceFill([
                        'status' => 'active',
                        'is_active' => true,
                        'time_zone' => $location->time_zone ?: ($locked->time_zone ?: config('app.timezone')),
                    ])->save();
                    $reactivated = true;
                } else {
                    $location = $locked->locations()->create([
                        'name' => $locked->name,
                        'time_zone' => $locked->time_zone ?: config('app.timezone'),
                        'status' => 'active',
                        'address' => $locked->address,
                        'phone' => $locked->phone,
                        'email' => $locked->email,
                        'is_active' => true,
                    ]);
                    $created = true;
                }
            }

            $membership->locations()->syncWithoutDetaching([
                $location->getKey() => ['business_id' => $locked->getKey()],
            ]);

            if ($created || $reactivated) {
                $this->audit->write(
                    action: $created ? 'location.default.provisioned' : 'location.default.reactivated',
                    business: $locked,
                    actor: $actor,
                    target: $location,
                    reason: $created
                        ? 'Required workspace foundation for a newly created Business.'
                        : 'Recovered the required workspace foundation from an inactive default Location.',
                    after: [
                        'location_public_id' => $location->public_id,
                        'membership_public_id' => $membership->public_id,
                    ],
                    source: 'onboarding',
                );
            }

            return $location;
        }, 3);
    }
}
