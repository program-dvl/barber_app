<?php

namespace App\Domain\Marketplace\LocalGrowth\Services;

use App\Domain\Marketplace\Services\MarketplaceListings;
use App\Domain\PlatformAccess\Models\Business;
use App\Domain\PublicBooking\Services\PublicBookingService;
use App\Support\BookingPageData;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class PublicSupplyProjection
{
    public function rebuild(int $businessId): void
    {
        DB::transaction(function () use ($businessId) {
            $business = Business::query()->lockForUpdate()->find($businessId);
            if (! $business) {
                return;
            }
            DB::table('marketplace_projection_work')->insertOrIgnore(['business_id' => $businessId, 'generation' => 1, 'projected_generation' => 0, 'created_at' => now(), 'updated_at' => now()]);
            $work = DB::table('marketplace_projection_work')->where('business_id', $businessId)->lockForUpdate()->first();
            $old = DB::table('marketplace_public_offers')->where('business_id', $businessId)->get()->keyBy(fn ($o) => $o->location_id.':'.$o->service_id);
            $seen = [];
            if (app(MarketplaceListings::class)->query()->whereKey($businessId)->exists() && ! $business->closed_at && ! $business->suspended_at) {
                $catalog = app(PublicBookingService::class)->catalog($business);
                $public = app(BookingPageData::class)->business($business);
                $locations = $business->locations()->get()->keyBy('public_id');
                $services = $business->services()->get()->keyBy('public_id');
                $mappings = DB::table('marketplace_service_concepts')->where('business_id', $businessId)->get()->groupBy('service_id');
                $geographies = DB::table('marketplace_location_geographies')->where('business_id', $businessId)->get()->keyBy('location_id');
                $validUntil = now()->addMinutes(config('marketplace-growth.projection_minutes'));
                foreach (['services', 'staff_service_assignments'] as $table) {
                    foreach (['effective_from', 'effective_until'] as $column) {
                        $next = DB::table($table)->where('business_id', $businessId)->where($column, '>', now())->min($column);
                        if ($next && CarbonImmutable::parse($next)->lt($validUntil)) {
                            $validUntil = CarbonImmutable::parse($next);
                        }
                    }
                }
                foreach ($catalog['locations'] as $location) {
                    $sourceLocation = $locations[$location['public_id']];
                    $binding = $geographies[$sourceLocation->id] ?? null;
                    $geo = $binding && hash_equals($binding->address_hash, hash('sha256', (string) $sourceLocation->address)) ? $binding->geography_id : null;
                    foreach ($catalog['services'] as $service) {
                        if ($service['kind'] !== 'service' || ! in_array($location['public_id'], $service['location_ids'], true)) {
                            continue;
                        }
                        $qualified = collect($catalog['staff'])->filter(fn ($p) => in_array($location['public_id'], $p['location_ids'], true) && in_array($service['public_id'], $p['service_ids'], true));
                        if ($qualified->isEmpty()) {
                            continue;
                        }
                        $sourceService = $services[$service['public_id']];
                        $branch = collect($service['location_prices'])->firstWhere('location', $location['public_id']);
                        $price = $qualified->map(fn ($p) => collect($p['service_variants'])->firstWhere('service', $service['public_id'])['price_minor'] ?? $branch['price_minor'] ?? $service['price_minor'])->min();
                        $concepts = app(DiscoveryTaxonomy::class)->concepts($service['name'], $business->business_type, ($mappings[$sourceService->id] ?? collect())->pluck('concept')->all(), $business->locale);
                        $terms = app(DiscoveryTaxonomy::class)->terms($concepts, $business->business_type);
                        $facts = [
                            'business_public_id' => $business->public_id, 'location_public_id' => $location['public_id'], 'service_public_id' => $service['public_id'],
                            'location_name' => $sourceLocation->name, 'business_type' => $business->business_type, 'locale' => $business->locale, 'time_zone' => $location['time_zone'],
                            'description' => $business->description, 'service_description' => $service['description'], 'category' => $service['category'],
                            'duration_minutes' => $service['duration_minutes'], 'price_type' => $service['price_type'],
                            'client_eligibility' => $service['client_eligibility'], 'new_client_rule' => $business->online_new_client_rule,
                            'image' => ($image = $public['gallery'][0]['url'] ?? $public['cover_url']) ? (parse_url($image, PHP_URL_PATH) ?: null) : null,
                            'opening_hours' => collect($location['opening_hours'])->all(),
                            'reviews' => collect($public['reviews'])->only(['count', 'average', 'items'])->all(),
                            'provenance' => ['catalog' => 'canonical-public-booking', 'taxonomy' => config('marketplace-taxonomy.version'), 'mapping' => isset($mappings[$sourceService->id]) ? 'owner-reviewed' : ($concepts ? 'reviewed-exact-alias' : 'unmapped'), 'geography' => $geo ? 'owner-reviewed-address' : 'unassigned'],
                            'terms' => $terms,
                        ];
                        $values = [
                            'business_id' => $businessId, 'location_id' => $sourceLocation->id, 'service_id' => $sourceService->id,
                            'geography_id' => $geo, 'slug' => $business->booking_slug, 'business_name' => $business->name, 'service_name' => $service['name'],
                            'address' => $location['address'], 'currency' => $service['currency_code'], 'price_minor' => $price,
                            'latitude' => $location['entrance_latitude'] ?? null, 'longitude' => $location['entrance_longitude'] ?? null,
                            'review_count' => $public['reviews']['count'], 'review_average' => $public['reviews']['average'], 'facts' => json_encode($facts, JSON_THROW_ON_ERROR),
                        ];
                        $hash = hash('sha256', json_encode($values, JSON_THROW_ON_ERROR));
                        $previous = $old[$sourceLocation->id.':'.$sourceService->id] ?? null;
                        $values += ['content_hash' => $hash, 'materially_changed_at' => $previous && hash_equals($previous->content_hash, $hash) ? $previous->materially_changed_at : now(), 'first_listed_at' => $work->first_listed_at ?? $previous?->first_listed_at ?? now(), 'valid_until' => $validUntil, 'generation' => $work->generation, 'updated_at' => now(), 'created_at' => $previous?->created_at ?? now()];
                        if ($previous) {
                            $id = $previous->id;
                            DB::table('marketplace_public_offers')->where('id', $id)->update($values);
                            DB::table('marketplace_offer_terms')->where('offer_id', $id)->delete();
                        } else {
                            $id = DB::table('marketplace_public_offers')->insertGetId($values);
                        }
                        $seen[] = $id;
                        foreach ($terms as $kind => $keys) {
                            foreach ($keys as $key) {
                                DB::table('marketplace_offer_terms')->insert(['offer_id' => $id, 'kind' => $kind, 'term' => $key]);
                            }
                        }
                    }
                }
            }
            DB::table('marketplace_public_offers')->where('business_id', $businessId)->whereNotIn('id', $seen)->delete();
            DB::table('marketplace_projection_work')->where('business_id', $businessId)->update(['projected_generation' => $work->generation, 'first_listed_at' => $work->first_listed_at ?? ($seen ? now() : null), 'attempts' => 0, 'retry_at' => null, 'failure_reference' => null, 'updated_at' => now()]);
            app(SeoPageRegistry::class)->syncProfile($business);
            app(SeoPageRegistry::class)->refreshMaterialChanges($businessId, array_values(array_unique([...$old->pluck('geography_id')->filter()->all(), ...DB::table('marketplace_public_offers')->where('business_id', $businessId)->whereNotNull('geography_id')->pluck('geography_id')->all()])));
        }, 3);
    }
}
