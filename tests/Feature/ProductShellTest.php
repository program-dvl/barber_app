<?php

use App\Domain\PlatformAccess\Enums\PlatformRole;
use App\Domain\PlatformAccess\Enums\StarterRole;
use App\Domain\PlatformAccess\Models\Business;
use App\Domain\PlatformAccess\Models\Location;
use App\Domain\PlatformAccess\Models\PlatformRoleAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('only references registered named routes from Salon Setup', function () {
    $source = File::get(resource_path('js/Pages/Configuration/Onboarding.vue'));
    preg_match_all('/\\broute\\(\\s*[\'\"]([^\'\"]+)[\'\"]/', $source, $matches);
    $missing = collect($matches[1] ?? [])
        ->unique()
        ->reject(fn (string $name): bool => Route::has($name))
        ->values();

    $this->assertSame([], $missing->all(), 'Salon Setup references missing routes: '.implode(', ', $missing->all()));
});

it('renders the public booking and secure self-service shells', function () {
    $this->get(route('booking.welcome'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Booking/Welcome'));

    $this->get(route('booking.manage'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Booking/Manage'));
});

it('keeps shop shell routes behind authentication', function () {
    $business = Business::factory()->create();

    $this->get(route('dashboard'))->assertRedirect(route('login'));
    $this->get(route('shop.module', [$business, 'calendar']))->assertRedirect(route('login'));
    $this->get(route('design-system.patterns'))->assertRedirect(route('login'));
});

it('renders the reusable interface pattern reference', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('design-system.patterns'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('DesignSystem/Patterns'));
});

it('renders the dashboard with tenant-scoped operational data', function () {
    [$user, $business] = createTenantMembership(StarterRole::Owner);
    $location = Location::factory()->create([
        'business_id' => $business->id,
        'name' => 'Main studio',
        'time_zone' => 'Asia/Kolkata',
        'is_active' => true,
    ]);

    $this->actingAs($user)
        ->get(route('business.dashboard', [$business, 'location' => $location->public_id, 'date' => '2026-08-17']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('location.public_id', $location->public_id)
            ->where('location.name', 'Main studio')
            ->where('date', '2026-08-17')
            ->has('locations', 1)
            ->where('calendar.counts.appointments', 0)
        );
});

it('renders every authenticated shop shell destination without domain data', function (string $module, string $label) {
    [$user, $business] = createTenantMembership(StarterRole::Owner);
    activateTestSubscription($business);

    $this->actingAs($user)
        ->get(route('shop.module', [$business, $module]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Shop/ModulePlaceholder')
            ->where('module.label', $label)
            ->where('module.nonGoal', fn (string $value) => str_contains($value, 'No '))
        );
})->with([
    ['settings', 'Settings'],
    ['subscription-billing', 'Subscription & Billing'],
]);

it('renders focused location team and service activation workspaces instead of placeholders', function () {
    [$user, $business] = createTenantMembership(StarterRole::Owner);
    activateTestSubscription($business);

    $this->actingAs($user)->get(route('business.locations.index', $business))
        ->assertOk()->assertInertia(fn (Assert $page) => $page->component('Activation/Location'));
    $this->actingAs($user)->get(route('business.team.index', $business))
        ->assertOk()->assertInertia(fn (Assert $page) => $page->component('Team/Index'));
    $this->actingAs($user)->get(route('business.services.index', $business))
        ->assertOk()->assertInertia(fn (Assert $page) => $page->component('Services/Index'));
});

it('renders the implemented inventory and reporting workspaces', function () {
    [$user, $business] = createTenantMembership(StarterRole::Owner);
    activateTestSubscription($business);
    Location::factory()->create(['business_id' => $business->id, 'time_zone' => 'Asia/Kolkata']);

    $this->actingAs($user)
        ->get(route('business.inventory.index', $business))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Shop/Inventory')->has('products.data'));

    $this->actingAs($user)
        ->get(route('business.reports.index', $business))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Shop/Reports')->where('result.report_key', 'sales')->has('catalog'));
});

it('renders the appointment-first checkout workspace', function () {
    [$user, $business] = createTenantMembership(StarterRole::Owner);
    activateTestSubscription($business);
    Location::factory()->create(['business_id' => $business->id]);

    $this->actingAs($user)
        ->get(route('business.checkout.index', $business))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Shop/Checkout')->has('appointments')->has('sales'));
});

it('denies the platform shell to an ordinary authenticated user', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('platform.overview'))
        ->assertForbidden();
});

it('renders the distinct platform shell for the existing platform administrator gate', function () {
    $administrator = User::factory()->create(['two_factor_confirmed_at' => now()]);
    PlatformRoleAssignment::query()->create([
        'user_id' => $administrator->getKey(),
        'role' => PlatformRole::Administrator,
        'reason' => 'Product shell test administrator.',
    ]);

    $this->actingAs($administrator)
        ->get(route('platform.overview'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Platform/Overview'));
});

it('does not expose unknown shell modules', function () {
    [$user, $business] = createTenantMembership(StarterRole::Owner);

    $this->actingAs($user)
        ->get('/businesses/'.$business->public_id.'/app/not-a-module')
        ->assertNotFound();
});

it('defines semantic focus and reduced-motion foundations', function () {
    $css = file_get_contents(resource_path('css/app.css'));

    expect($css)
        ->toContain('--focus-ring:')
        ->toContain(':focus-visible')
        ->toContain('@media (prefers-reduced-motion: reduce)')
        ->toContain('animation-duration: 0.01ms !important')
        ->toContain('--status-danger:')
        ->toContain('--status-success:');
});

it('uses the accepted ClipperDesk product identity', function () {
    $css = file_get_contents(resource_path('css/app.css'));
    $markComponent = file_get_contents(resource_path('js/Components/Product/ProductMark.vue'));
    $clientEntry = file_get_contents(resource_path('js/app.js'));
    $appView = file_get_contents(resource_path('views/app.blade.php'));
    $authLayout = file_get_contents(resource_path('js/Layouts/AuthLayout.vue'));

    expect($css)
        ->toContain("font-family: 'Manrope'")
        ->toContain('--brand-primary: #172554')
        ->toContain('--brand-secondary: #4f46e5')
        ->toContain('--surface-canvas: var(--background-primary)')
        ->toContain('--action-primary: #4338ca');

    expect($markComponent)
        ->toContain('ClipperDesk')
        ->toContain('Run the day. Grow the business.')
        ->toContain('clipperdesk-mark.svg');

    expect($clientEntry)->toContain("'ClipperDesk'");
    expect($appView)->toContain('data-theme="clipperdesk"');
    expect($authLayout)->not->toContain("route('home')");
    expect(file_exists(resource_path('images/brand/clipperdesk-mark.svg')))->toBeTrue();
    expect(file_exists(public_path('fonts/clipperdesk/manrope-600.ttf')))->toBeTrue();
    expect(file_exists(public_path('images/brand/clipperdesk-logo.svg')))->toBeTrue();
});
