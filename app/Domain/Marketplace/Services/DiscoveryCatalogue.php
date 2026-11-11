<?php

namespace App\Domain\Marketplace\Services;

use App\Domain\PublicBooking\Services\PublicBookingService;
use Illuminate\Database\Eloquent\Builder;

class DiscoveryCatalogue
{
    public function suggestions(): array
    {
        $names = [];
        foreach (app(MarketplaceListings::class)->query()->lazy(50) as $business) {
            $catalog = app(PublicBookingService::class)->catalog($business);
            foreach ($catalog['services'] as $service) {
                if ($service['kind'] !== 'service' || ! collect($catalog['staff'])->contains(fn ($staff) => in_array($service['public_id'], $staff['service_ids']) && count(array_intersect($staff['location_ids'], $service['location_ids'], collect($catalog['locations'])->pluck('public_id')->all())))) {
                    continue;
                }
                $names[mb_strtolower($service['name'])] = ['name' => $service['name'], 'category' => $service['category']];
            }
        }
        ksort($names);

        return array_values($names);
    }

    public function category(Builder $query, string $category): void
    {
        $terms = match ($category) {
            'hair' => ['hair', 'colour', 'color', 'blow', 'balayage'],
            'barber' => ['barber', 'beard', 'shave', 'fade'],
            'nails' => ['nail', 'manicure', 'pedicure'],
        };
        $query->where(function ($query) use ($terms, $category) {
            $query->where('business_type', ['hair' => 'hair_salon', 'barber' => 'barber_shop', 'nails' => 'nail_studio'][$category])
                ->when($category === 'nails', fn ($q) => $q->orWhere('business_type', 'nail_salon'))
                ->orWhereHas('services', function ($services) use ($terms) {
                    $services->where('kind', 'service')->where('is_active', true)->where('online_visible', true)
                        ->where(fn ($q) => $q->whereNull('effective_from')->orWhere('effective_from', '<=', now()))
                        ->where(fn ($q) => $q->whereNull('effective_until')->orWhere('effective_until', '>', now()))
                        ->where(function ($q) use ($terms) {
                            foreach ($terms as $term) {
                                $q->orWhere('name', 'like', '%'.$term.'%')->orWhereHas('category', fn ($c) => $c->where('name', 'like', '%'.$term.'%'));
                            }
                        });
                });
        });
    }
}
