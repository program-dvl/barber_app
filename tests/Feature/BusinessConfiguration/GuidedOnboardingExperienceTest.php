<?php

use App\Domain\BusinessConfiguration\Models\Service;
use App\Domain\BusinessConfiguration\Models\ServiceCategory;
use App\Domain\BusinessConfiguration\Models\StaffServiceAssignment;
use App\Domain\BusinessConfiguration\Services\BusinessSetupProgress;
use App\Domain\BusinessConfiguration\Services\OnboardingManager;
use App\Domain\BusinessConfiguration\Services\ServiceCatalogManager;
use App\Domain\BusinessConfiguration\Services\StarterWorkspaceProvisioner;
use App\Domain\PlatformAccess\Enums\StarterRole;
use App\Domain\PlatformAccess\Models\AuditEvent;
use App\Support\Files\TenantFilePath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
});

it('turns four short owner decisions into an editable private starter workspace', function () {
    [$owner, $business, $membership] = createTenantMembership(StarterRole::Owner);
    activateTestSubscription($business);
    $business->update(['name' => 'Juniper House']);

    $this->actingAs($owner)->get(route('business.configuration.show', $business))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Configuration/Onboarding')
            ->where('onboarding.current_step', 'business_type')
            ->where('onboarding.guided_completed_at', null)
            ->has('onboardingCatalog.business_types.hair_salon.services', 4));

    $this->actingAs($owner)->patch(route('business.configuration.guided-onboarding.update', $business), [
        'step' => 'business_type', 'business_type' => 'hair_salon',
    ])->assertRedirect()->assertSessionHasNoErrors();
    $this->actingAs($owner)->patch(route('business.configuration.guided-onboarding.update', $business), [
        'step' => 'business_shape', 'operation_model' => 'at_location', 'team_size' => '2-5',
        'location_scale' => 'single', 'owner_bookable' => true, 'accepts_online_bookings' => false,
    ])->assertRedirect()->assertSessionHasNoErrors();
    $this->actingAs($owner)->patch(route('business.configuration.guided-onboarding.update', $business), [
        'step' => 'location', 'country_code' => 'IN', 'time_zone' => 'Asia/Kolkata',
        'address' => '18 Garden Lane, Ahmedabad 380009', 'phone' => '+919876543210',
        'schedule_preset' => 'tuesday_saturday',
    ])->assertRedirect()->assertSessionHasNoErrors();
    $this->actingAs($owner)->post(route('business.configuration.guided-onboarding.complete', $business), [
        'service_keys' => ['cut_finish', 'blow_dry', 'hair_treatment'],
    ])->assertRedirect()->assertSessionHasNoErrors();

    $business = $business->fresh();
    $session = $business->onboardingSession;
    $location = $business->locations()->firstOrFail();
    $staff = $business->staffProfiles()->firstOrFail();

    expect($business->business_type)->toBe('hair_salon')
        ->and($business->booking_slug)->toBe('juniper-house')
        ->and($business->configuration_published_at)->toBeNull()
        ->and($business->online_booking_enabled)->toBeFalse()
        ->and($session->previewed_at)->toBeNull()
        ->and($business->brand_color)->toBe('#6D4AFF')
        ->and($session->schema_version)->toBe(2)
        ->and($session->guided_completed_at)->not->toBeNull()
        ->and($session->generated_data['services'])->toHaveCount(3)
        ->and($session->generated_data['categories'])->toHaveCount(4)
        ->and($location->hours()->count())->toBe(5)
        ->and($location->address)->toBe('18 Garden Lane, Ahmedabad 380009')
        ->and($staff->membership_id)->toBe($membership->id)
        ->and($staff->user_id)->toBe($owner->id)
        ->and($staff->online_visible)->toBeTrue()
        ->and($staff->availabilityRules()->where('kind', 'working')->count())->toBe(5)
        ->and($business->services()->count())->toBe(3)
        ->and(StaffServiceAssignment::query()->where('business_id', $business->id)->count())->toBe(3);

    $categories = ServiceCategory::query()->where('business_id', $business->id)->orderBy('display_order')->get();
    expect($categories->pluck('name')->all())->toBe(['Hair', 'Styling', 'Colour', 'Treatments'])
        ->and($categories->firstWhere('name', 'Colour')->services()->count())->toBe(0);
    $colour = $categories->firstWhere('name', 'Colour');
    $this->post(route('business.services.categories.save', $business), [
        'category_id' => $colour->public_id, 'name' => 'Colour services', 'is_active' => false,
        'revision' => app(ServiceCatalogManager::class)->categoryRevision($colour),
    ])->assertRedirect()->assertSessionHasNoErrors();

    $this->get(route('booking.business', $business->booking_slug))->assertNotFound();
    $progress = app(BusinessSetupProgress::class)->for($business);
    expect($progress['required_complete'])->toBeTrue()->and($progress['online_state'])->toBe('Not published');
    $this->post(route('business.configuration.publish', $business))->assertSessionHasErrors('readiness');
    $this->post(route('business.configuration.preview', $business))->assertRedirect()->assertSessionHasNoErrors();
    $this->post(route('business.configuration.publish', $business))->assertRedirect()->assertSessionHasNoErrors();
    $this->post(route('business.configuration.publish', $business))->assertRedirect()->assertSessionHasNoErrors();
    expect(AuditEvent::query()->where('business_id', $business->id)->where('action', 'configuration.published')->count())->toBe(1);
    $business = $business->fresh();
    $this->get(route('booking.business', $business->booking_slug))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Booking/Welcome')
            ->where('business.name', 'Juniper House')
            ->where('business.cover_url', '/images/marketing/industries/home-salon-hero.webp')
            ->has('catalog.services', 3));

    $progress = app(BusinessSetupProgress::class)->for($business);
    expect($progress['required_complete'])->toBeTrue()
        ->and($progress['percent'])->toBe(100)
        ->and($progress['label'])->toBe('Ready to take bookings');

    // A browser retry must not duplicate generated records or publish events.
    $this->actingAs($owner)->post(route('business.configuration.guided-onboarding.complete', $business), [
        'service_keys' => ['cut_finish'],
    ])->assertRedirect()->assertSessionHasNoErrors();
    expect($business->locations()->count())->toBe(1)
        ->and($business->staffProfiles()->count())->toBe(1)
        ->and($business->services()->count())->toBe(3)
        ->and(ServiceCategory::query()->where('business_id', $business->id)->count())->toBe(4)
        ->and($colour->fresh()->name)->toBe('Colour services')
        ->and($colour->fresh()->is_active)->toBeFalse();
});

function starterCategoryAnswers(string $type, string $serviceKey): array
{
    return [
        'business_type' => $type, 'operation_model' => 'at_location', 'team_size' => '1',
        'location_scale' => 'single', 'owner_bookable' => false, 'accepts_online_bookings' => false,
        'country_code' => 'IN', 'time_zone' => 'Asia/Kolkata',
        'address' => '18 Example Lane', 'phone' => '+919876543210',
        'schedule_preset' => 'weekdays', 'service_keys' => [$serviceKey],
    ];
}

it('prepares type-specific categories but only selected services for each supported business type', function (string $typeKey) {
    [$owner, $business, $membership] = createTenantMembership(StarterRole::Owner);
    activateTestSubscription($business);
    app(OnboardingManager::class)->resume($business);
    $type = config('business-onboarding.business_types.'.$typeKey);
    expect(array_diff(array_unique(array_column($type['services'], 'category')), $type['categories']))->toBe([]);

    $business = app(StarterWorkspaceProvisioner::class)->provision($business, $membership, $owner, starterCategoryAnswers($typeKey, $type['services'][0]['key']));
    $categories = ServiceCategory::query()->where('business_id', $business->id)->orderBy('display_order')->get();
    $service = $business->services()->with('category')->sole();
    expect($categories->pluck('name')->all())->toBe($type['categories'])
        ->and($categories->pluck('display_order')->map(fn ($value) => (int) $value)->all())->toBe(range(1, count($type['categories'])))
        ->and($service->name)->toBe($type['services'][0]['name'])
        ->and($service->category->name)->toBe($type['services'][0]['category'])
        ->and($business->onboardingSession->generated_data['categories'])->toHaveCount(count($type['categories']));
})->with([
    'hair_salon', 'barber_shop', 'nail_studio', 'beauty_studio', 'spa', 'massage',
    'wellness', 'skin_aesthetics', 'makeup_bridal', 'independent_professional', 'multi_service', 'other',
]);

it('reuses tenant categories case-insensitively without restoring archives or resetting custom order', function () {
    [$owner, $business, $membership] = createTenantMembership(StarterRole::Owner);
    [, $other] = createTenantMembership(StarterRole::Owner);
    activateTestSubscription($business);
    app(OnboardingManager::class)->resume($business);
    $existing = ServiceCategory::query()->create(['business_id' => $business->id, 'name' => 'hAiR', 'display_order' => 7, 'is_active' => false]);
    $custom = ServiceCategory::query()->create(['business_id' => $business->id, 'name' => 'Our speciality', 'display_order' => 12, 'is_active' => true]);
    $foreign = ServiceCategory::query()->create(['business_id' => $other->id, 'name' => 'Styling', 'display_order' => 99, 'is_active' => false]);

    app(StarterWorkspaceProvisioner::class)->provision($business, $membership, $owner, starterCategoryAnswers('hair_salon', 'cut_finish'));
    $service = $business->services()->sole();
    expect($existing->fresh()->only(['name', 'display_order', 'is_active']))->toBe(['name' => 'hAiR', 'display_order' => 7, 'is_active' => false])
        ->and($custom->fresh()->name)->toBe('Our speciality')
        ->and($service->service_category_id)->toBe($existing->id)
        ->and($service->is_active)->toBeFalse()
        ->and($service->online_visible)->toBeFalse()
        ->and(ServiceCategory::query()->where('business_id', $business->id)->where('name', 'Styling')->sole()->display_order)->toBe(13)
        ->and($foreign->fresh()->is_active)->toBeFalse()
        ->and(ServiceCategory::query()->where('business_id', $business->id)->count())->toBe(5);
});

it('does not add a starter category set over an existing service catalogue', function () {
    [$owner, $business, $membership] = createTenantMembership(StarterRole::Owner);
    activateTestSubscription($business);
    app(OnboardingManager::class)->resume($business);
    $category = ServiceCategory::query()->create(['business_id' => $business->id, 'name' => 'Our cuts']);
    $service = Service::query()->create(['business_id' => $business->id, 'service_category_id' => $category->id, 'name' => 'Our own service', 'kind' => 'service', 'currency_code' => 'INR', 'price_minor' => 123400, 'duration_minutes' => 40]);
    app(StarterWorkspaceProvisioner::class)->provision($business, $membership, $owner, starterCategoryAnswers('hair_salon', 'cut_finish'));
    expect(ServiceCategory::query()->where('business_id', $business->id)->count())->toBe(1)
        ->and($business->services()->sole()->id)->toBe($service->id)
        ->and($service->fresh()->price_minor)->toBe(123400)
        ->and($business->fresh()->onboardingSession->generated_data['categories'])->toBe([]);
});

it('keeps existing and partially configured businesses out of automatic starter seeding', function () {
    [$owner, $business] = createTenantMembership(StarterRole::Owner);
    activateTestSubscription($business);
    $session = app(OnboardingManager::class)->resume($business);
    $session->forceFill([
        'schema_version' => 1,
        'answers' => ['business_type' => 'barber_shop'],
        'guided_completed_at' => now(),
    ])->save();

    $this->actingAs($owner)->get(route('business.configuration.show', $business))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('onboarding.schema_version', 1)
            ->where('onboarding.guided_completed_at', fn ($value) => filled($value)));

    expect($business->fresh()->business_type)->toBeNull()
        ->and($business->services()->count())->toBe(0)
        ->and($business->staffProfiles()->count())->toBe(0);
});

it('rejects another tenant and mismatched regional answers during guided onboarding', function () {
    [$owner, $business] = createTenantMembership(StarterRole::Owner);
    [$otherOwner, $otherBusiness] = createTenantMembership(StarterRole::Owner);
    activateTestSubscription($business);
    activateTestSubscription($otherBusiness);

    $this->actingAs($owner)->patch(route('business.configuration.guided-onboarding.update', $otherBusiness), [
        'step' => 'business_type', 'business_type' => 'spa',
    ])->assertForbidden();

    $this->actingAs($otherOwner)->get(route('business.configuration.show', $otherBusiness))->assertOk();
    $this->actingAs($otherOwner)->patch(route('business.configuration.guided-onboarding.update', $otherBusiness), [
        'step' => 'location', 'country_code' => 'IN', 'time_zone' => 'America/New_York',
        'address' => 'Test address', 'phone' => '+919876543210', 'schedule_preset' => 'weekdays',
    ])->assertSessionHasErrors('time_zone');
});

it('serves private booking artwork only for a live public business', function () {
    [, $business] = createTenantMembership(StarterRole::Owner);
    Storage::fake('private');
    $business->forceFill([
        'booking_slug' => 'cedar-and-stone',
        'configuration_published_at' => now(),
        'online_booking_enabled' => true,
        'logo_path' => 'configuration/branding/logo-test.png',
    ])->save();
    Storage::disk('private')->put(TenantFilePath::private($business, $business->logo_path), "\x89PNG\r\n\x1a\n");

    $this->get(route('public.booking.media', [$business->booking_slug, 'logo']))
        ->assertOk()
        ->assertHeader('Content-Type', 'image/png')
        ->assertHeader('X-Content-Type-Options', 'nosniff');

    $business->forceFill(['configuration_published_at' => null])->save();
    $this->get(route('public.booking.media', [$business->booking_slug, 'logo']))->assertNotFound();
});
