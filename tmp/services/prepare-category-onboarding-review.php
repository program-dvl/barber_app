<?php
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (! app()->environment('testing') || config('database.default') !== 'sqlite' || ! str_contains(config('database.connections.sqlite.database'), 'service-catalog-review')) {
    throw new RuntimeException('Use the isolated synthetic catalogue review database.');
}
$owner = App\Models\User::query()->where('email', 'owner@pine-palm.example.test')->firstOrFail();
$business = App\Domain\PlatformAccess\Models\Business::query()->where('slug', 'starter-category-review')->first();
if (! $business) {
    $business = App\Domain\PlatformAccess\Models\Business::factory()->create(['name' => 'Cedar Studio · Starter category review', 'slug' => 'starter-category-review']);
    $membership = App\Domain\PlatformAccess\Models\Membership::factory()->create(['business_id' => $business->id, 'user_id' => $owner->id]);
    app(App\Domain\PlatformAccess\Services\MembershipAccessManager::class)->assignStarterRole($membership, App\Domain\PlatformAccess\Enums\StarterRole::Owner, $owner, 'Isolated synthetic onboarding review.');
    $plan = App\Domain\Billing\Models\BillingPlan::query()->where('is_trial_default', true)->firstOrFail();
    App\Domain\Billing\Models\BusinessSubscription::query()->create(['business_id' => $business->id, 'billing_plan_id' => $plan->id, 'status' => 'trialing', 'restriction_level' => 'none', 'trial_started_at' => now(), 'trial_ends_at' => now()->addDays(14)]);
    $session = app(App\Domain\BusinessConfiguration\Services\OnboardingManager::class)->resume($business);
    $session->update(['current_step' => 'starter_services', 'answers' => ['business_type' => 'hair_salon', 'operation_model' => 'at_location', 'team_size' => '1', 'location_scale' => 'single', 'owner_bookable' => false, 'accepts_online_bookings' => false, 'country_code' => 'IN', 'time_zone' => 'Asia/Kolkata', 'address' => '18 Example Lane', 'phone' => '+919876543210', 'schedule_preset' => 'weekdays', 'service_keys' => ['cut_finish']]]);
}
echo 'Review URL: http://127.0.0.1:8136/businesses/'.$business->public_id.'/configuration'.PHP_EOL;
