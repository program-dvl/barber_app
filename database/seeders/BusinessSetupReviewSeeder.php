<?php

namespace Database\Seeders;

use App\Domain\Billing\Enums\RestrictionLevel;
use App\Domain\Billing\Enums\SubscriptionStatus;
use App\Domain\Billing\Models\BillingPlan;
use App\Domain\Billing\Models\BusinessSubscription;
use App\Domain\BusinessConfiguration\Services\OnboardingManager;
use App\Domain\BusinessConfiguration\Services\StarterWorkspaceProvisioner;
use App\Domain\ClientRecords\Services\ClientFormService;
use App\Domain\PlatformAccess\Enums\StarterRole;
use App\Domain\PlatformAccess\Models\Business;
use App\Domain\PlatformAccess\Models\Membership;
use App\Domain\PlatformAccess\Services\DefaultLocationProvisioner;
use App\Domain\PlatformAccess\Services\MembershipAccessManager;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/** Synthetic owners and configuration only, in an isolated local review database. */
class BusinessSetupReviewSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('testing') || config('database.default') !== 'sqlite' || config('cache.default') !== 'array'
            || ! str_contains(config('database.connections.sqlite.database'), 'business-setup-review')) {
            throw new \RuntimeException('Use an isolated business-setup-review SQLite database and array cache.');
        }
        $owner = User::query()->firstOrCreate(['email' => 'owner@setup.example.test'], [
            'name' => 'Maya Shah', 'password' => Hash::make('SetupReview-LocalOnly-2026'), 'email_verified_at' => now(),
        ]);
        foreach (['introduction' => 'Juniper House', 'ready' => 'Juniper House · Hair, Skin & Wellness Studio', 'team' => 'Juniper House · Team setup'] as $state => $name) {
            $business = Business::query()->where('slug', 'business-setup-review-'.$state)->first()
                ?? Business::factory()->create(['slug' => 'business-setup-review-'.$state, 'name' => $name]);
            $membership = Membership::query()->firstOrCreate(['business_id' => $business->id, 'user_id' => $owner->id], ['status' => 'active', 'joined_at' => now()]);
            app(MembershipAccessManager::class)->assignStarterRole($membership, StarterRole::Owner, $owner, 'Synthetic setup review');
            app(DefaultLocationProvisioner::class)->provision($business, $membership, $owner);
            app(ClientFormService::class)->seedStarterTemplates($business->id);
            BusinessSubscription::query()->firstOrCreate(['business_id' => $business->id], [
                'billing_plan_id' => BillingPlan::query()->where('code', 'pro')->firstOrFail()->id,
                'provider' => 'stripe', 'status' => SubscriptionStatus::Active, 'restriction_level' => RestrictionLevel::None,
                'current_period_started_at' => now()->subDay(), 'current_period_ends_at' => now()->addMonth(),
            ]);
            app(OnboardingManager::class)->resume($business);
            if ($state !== 'introduction') {
                app(StarterWorkspaceProvisioner::class)->provision($business->fresh(), $membership, $owner, [
                    'business_type' => 'hair_salon', 'operation_model' => 'at_location', 'team_size' => '1', 'location_scale' => 'single',
                    'owner_bookable' => $state === 'ready', 'accepts_online_bookings' => false,
                    'country_code' => 'IN', 'time_zone' => 'Asia/Kolkata', 'address' => '18 Example Lane', 'phone' => '+919876543210',
                    'schedule_preset' => 'weekdays', 'service_keys' => ['cut_finish', 'blow_dry', 'hair_treatment'],
                ]);
            }
            $this->command?->info($state.': '.$business->public_id);
        }
    }
}
