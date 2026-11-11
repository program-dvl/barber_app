<?php

// Isolated local visual evidence only. Never run against an application database.
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$path = config('database.connections.sqlite.database');
if (! $app->environment('testing') || config('database.default') !== 'sqlite' || ! is_string($path) || ! preg_match('#^/tmp/clipperdesk-marketplace-growth-[a-z0-9-]+\.sqlite$#D', $path) || file_exists($path)) {
    fwrite(STDERR, "Requires APP_ENV=testing and a NEW /tmp/clipperdesk-marketplace-growth-*.sqlite database.\n");
    exit(1);
}
touch($path);
Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
config(['marketplace-growth.enabled' => false, 'marketplace-growth.indexation_enabled' => false, 'queue.default' => 'sync', 'communications.dispatch_operational_events_immediately' => false, 'mail.default' => 'array']);
function activateTestSubscription($business) {
    return App\Domain\Billing\Models\BusinessSubscription::query()->firstOrCreate(['business_id' => $business->id], ['billing_plan_id' => App\Domain\Billing\Models\BillingPlan::query()->where('code', 'pro')->firstOrFail()->id, 'provider' => 'stripe', 'provider_customer_id' => 'cus_qa_'.$business->id, 'provider_subscription_id' => 'sub_qa_'.$business->id, 'status' => 'active', 'restriction_level' => 'none', 'current_period_started_at' => now()->subDay(), 'current_period_ends_at' => now()->addMonth()]);
}
require __DIR__.'/../tests/Support/MarketplaceGrowthFixture.php';
$examples = [
    ['barber_shop', 'Skin fade'], ['hair_salon', 'Cut & finish'], ['independent_professional', 'Gel manicure'], ['spa', 'Sauna session'],
    ['nail_studio', 'Gel manicure'], ['skin_aesthetics', 'Aesthetics consultation'], ['massage', 'Deep tissue massage'], ['wellness', 'Personal training'],
    ['other', 'Physical therapy appointment'], ['other', 'Practice appointment'], ['other', 'Piercing appointment'], ['other', 'Dog grooming'], ['other', 'Tanning session'], ['other', 'Dog grooming'], ['other', 'Dog grooming'],
];
$first = null;
foreach ($examples as $i => [$type, $name]) {
    $f = growthSupply($name, $type);
    config(['marketplace-growth.enabled' => false]);
    $f['business']->update(['name' => 'QA '.($i + 1).' — '.$name, 'time_zone' => 'America/Toronto', 'description' => 'Isolated local test business for reviewing published appointments, public locations and starting prices. This is synthetic inventory and must never be deployed or indexed.']);
    $f['business']->forceFill(['marketplace_version' => 1])->save();
    App\Domain\BusinessConfiguration\Models\OnboardingSession::query()->updateOrCreate(['business_id' => $f['business']->id], ['schema_version' => 2, 'current_step' => 'complete', 'completed_steps' => ['profile','hours','services','staff','booking','preview','published'], 'answers' => [], 'generated_data' => ['source' => 'local_growth_fixture'], 'started_at' => now(), 'personalized_at' => now(), 'guided_completed_at' => now(), 'last_saved_at' => now(), 'previewed_at' => now(), 'published_at' => now()]);
    config(['marketplace-growth.enabled' => true]);
    app(App\Domain\Marketplace\LocalGrowth\Services\PublicSupplyProjection::class)->rebuild($f['business']->id);
    growthApproveProfile($f);
    $first ??= $f;
}
growthApproveHub($first);
$first['user']->update(['email' => 'owner-growth-qa@example.test', 'name' => 'Local QA Owner']);
echo json_encode(['database' => $path, 'origin' => config('app.url'), 'profile' => '/marketplace/salons/'.$first['business']->booking_slug, 'local' => '/marketplace/ca/on/test-city/services/dog-grooming', 'owner' => '/businesses/'.$first['business']->public_id.'/app/marketplace', 'report' => '/businesses/'.$first['business']->public_id.'/app/marketplace/outcomes', 'email' => $first['user']->email, 'indexation' => false], JSON_PRETTY_PRINT)."\n";
