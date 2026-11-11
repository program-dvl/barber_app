<?php

namespace App\Domain\Marketplace\LocalGrowth\Services;

use App\Domain\PlatformAccess\Models\Business;
use App\Domain\PlatformAccess\Models\Location;
use App\Support\Audit\AuditWriter;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Called only inside canonical settings commands and their business transaction. */
class OwnerDiscoverySettings
{
    public function location(Business $business, Location $location, array $data): void
    {
        if (! config('marketplace-growth.enabled') || ! array_key_exists('discovery_address_public', $data)) {
            return;
        }
        abort_unless((int) $location->business_id === (int) $business->id, 404);
        $public = (bool) $data['discovery_address_public'];
        $geo = $public ? DB::table('marketplace_geographies')->where('public_id', $data['discovery_geography'] ?? '')->first() : null;
        if ($public && (! $geo || strtoupper($geo->country) !== strtoupper($location->country_code ?: $business->country_code))) {
            throw ValidationException::withMessages(['discovery_geography' => 'Choose a reviewed area in this location’s country. Your other location details are kept.']);
        }
        DB::table('marketplace_location_geographies')->where('business_id', $business->id)->where('location_id', $location->id)->delete();
        if ($public) {
            DB::table('marketplace_location_geographies')->insert([
                'business_id' => $business->id, 'location_id' => $location->id, 'geography_id' => $geo->id,
                'address_hash' => hash('sha256', (string) $location->address), 'reviewed_by' => auth('web')->id(),
                'address_public_at' => now(), 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        app(AuditWriter::class)->write('marketplace.geography.reviewed', $business, target: $location, after: ['public_address' => $public, 'geography' => $geo?->public_id], metadata: ['consent_version' => 'public-location-2026-11-08']);
        app(ProjectionWork::class)->mark($business->id);
    }

    public function locationChoices(Business $business): array
    {
        if (! config('marketplace-growth.enabled')) {
            return [];
        }

        return DB::table('marketplace_geographies')->where('country', strtolower($business->country_code))->orderBy('name')->limit(200)->get(['public_id', 'name', 'region', 'country'])->all();
    }
}
