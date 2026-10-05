<?php

namespace Database\Seeders;

use App\Domain\BusinessConfiguration\Models\Service;
use App\Domain\BusinessConfiguration\Models\ServiceCategory;
use App\Domain\BusinessConfiguration\Models\StaffServiceAssignment;
use App\Domain\PlatformAccess\Models\Business;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/** Synthetic catalogue in an isolated SQLite review database only. */
class ServiceCatalogReviewSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('testing') || config('database.default') !== 'sqlite' || ! str_contains(config('database.connections.sqlite.database'), 'service-catalog-review')) {
            throw new \RuntimeException('Use an isolated service-catalog-review SQLite database.');
        }
        $this->call(GoodHoursDemoSeeder::class);
        $this->call(TeamWorkspaceReviewSeeder::class);
        $owner = User::query()->where('email', 'owner@pine-palm.example.test')->firstOrFail();
        $owner->update(['password' => Hash::make('ServiceReview-LocalOnly-2026')]);
        $business = Business::query()->where('slug', 'team-workspace-review')->firstOrFail();
        $business->update(['name' => 'Atelier Studio · Services review', 'online_booking_enabled' => true, 'configuration_published_at' => now(), 'booking_slug' => 'service-catalog-review']);
        $staff = $business->staffProfiles()->with('locations')->where('status', 'active')->get();
        $locations = $business->locations()->get();
        $categories = [];
        foreach (['Hair', 'Colour', 'Beard', 'Treatments', 'Nails', 'Skin'] as $i => $name) {
            $categories[$name] = ServiceCategory::query()->firstOrCreate(['business_id' => $business->id, 'name' => $name], ['display_order' => $i + 1]);
        }
        $rows = [
            ['Signature haircut', 'Hair', 150000, 45, 0, 5], ['Cut & finish', 'Hair', 180000, 60, 0, 5], ['Blow-dry & styling', 'Hair', 90000, 30, 0, 5], ['Restyle & consultation', 'Hair', 220000, 60, 0, 10],
            ['Full colour & gloss', 'Colour', 450000, 60, 30, 10], ['Root refresh', 'Colour', 250000, 45, 20, 5], ['Balayage & finishing treatment', 'Colour', 650000, 90, 45, 15],
            ['Beard sculpt & finish', 'Beard', 65000, 30, 0, 5], ['Hot towel shave', 'Beard', 85000, 40, 0, 5], ['Deep conditioning ritual', 'Treatments', 120000, 30, 15, 5],
            ['Scalp renewal', 'Treatments', 150000, 45, 0, 5], ['Classic manicure', 'Nails', 100000, 45, 0, 5], ['Gel polish & finish', 'Nails', 180000, 60, 0, 5], ['Skin consultation & personalised facial treatment with gentle resurfacing', 'Skin', 320000, 75, 15, 10],
        ];
        foreach ($rows as $i => [$name,$category,$price,$duration,$processing,$cleanup]) {
            $service = Service::query()->firstOrCreate(['business_id' => $business->id, 'name' => $name], ['service_category_id' => $categories[$category]->id, 'kind' => 'service', 'description' => 'A consultation-led service with a personalised finish.', 'price_type' => $i === 6 ? 'from' : 'fixed', 'price_minor' => $price, 'currency_code' => 'INR', 'duration_minutes' => $duration, 'processing_minutes' => $processing, 'cleanup_minutes' => $cleanup, 'is_active' => $i !== 10, 'online_visible' => $i !== 8, 'deposit_type' => $i === 6 ? 'percentage' : 'none', 'deposit_value' => $i === 6 ? 2500 : 0]);
            foreach ($locations->take($i % 3 === 0 ? 1 : 2) as $l) {
                $service->locations()->syncWithoutDetaching([$l->id => ['business_id' => $business->id, 'is_eligible' => true, 'price_minor' => $i === 1 ? $price + 20000 : null]]);
            }
            if ($i !== 11) {
                foreach ($staff->take($i % 3 + 1) as $j => $p) {
                    StaffServiceAssignment::query()->firstOrCreate(['business_id' => $business->id, 'service_id' => $service->id, 'staff_profile_id' => $p->id], ['is_active' => true, 'is_qualified' => true, 'online_visible' => true, 'price_minor' => $i === 0 && $j === 0 ? $price + 25000 : null, 'duration_minutes' => $i === 0 && $j === 0 ? 40 : null]);
                }
            }
        }
        foreach ([['Conditioning boost', 35000, 15], ['Beard tidy', 30000, 10]] as [$name,$price,$duration]) {
            $addon = Service::query()->firstOrCreate(['business_id' => $business->id, 'name' => $name], ['service_category_id' => $categories['Treatments']->id, 'kind' => 'addon', 'price_minor' => $price, 'currency_code' => 'INR', 'duration_minutes' => $duration]);
            foreach ($locations as $l) {
                $addon->locations()->syncWithoutDetaching([$l->id => ['business_id' => $business->id, 'is_eligible' => true]]);
            }
            foreach ($staff->take(3) as $p) {
                StaffServiceAssignment::query()->firstOrCreate(['business_id' => $business->id, 'service_id' => $addon->id, 'staff_profile_id' => $p->id], ['is_active' => true, 'is_qualified' => true, 'online_visible' => true]);
            }
            $business->services()->where('name', 'Signature haircut')->firstOrFail()->addons()->syncWithoutDetaching([$addon->id => ['business_id' => $business->id]]);
        }
        // Volume and pagination have realistic names, without creating imaginary product categories.
        for ($i = 1; $i <= 30; $i++) {
            Service::query()->firstOrCreate(['business_id' => $business->id, 'name' => 'Styling consultation '.str_pad((string) $i, 2, '0', STR_PAD_LEFT)], ['service_category_id' => $categories['Hair']->id, 'kind' => 'service', 'price_minor' => 50000, 'currency_code' => 'INR', 'duration_minutes' => 30, 'is_active' => false, 'online_visible' => false]);
        }
        $this->command?->info('Services review business: '.$business->public_id);
    }
}
