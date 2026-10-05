<?php

use App\Domain\BusinessConfiguration\Models\LocationScheduleException;
use App\Domain\BusinessConfiguration\Services\BusinessSetupProgress;
use App\Domain\BusinessConfiguration\Services\OnboardingManager;
use App\Domain\BusinessConfiguration\Services\ReadinessEvaluator;
use App\Domain\BusinessConfiguration\Services\StarterWorkspaceProvisioner;
use App\Domain\MoneyCommerce\Models\CommerceSetting;
use App\Domain\PlatformAccess\Enums\StarterRole;
use App\Domain\PlatformAccess\Models\AuditEvent;
use App\Domain\PlatformAccess\Models\Location;
use App\Domain\PlatformAccess\Services\MembershipAccessManager;
use App\Domain\SchedulingOperations\Models\Appointment;
use App\Domain\SchedulingOperations\Models\CapacityHold;
use App\Http\Controllers\Shop\SetupHoursController;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);
beforeEach(function () {
    $this->withoutVite();
    CarbonImmutable::setTestNow('2035-10-08 04:30 UTC');
});
afterEach(fn () => CarbonImmutable::setTestNow());

function setupAnswers(array $changes = []): array
{
    return [...[
        'business_type' => 'hair_salon', 'operation_model' => 'at_location', 'team_size' => '1',
        'location_scale' => 'single', 'owner_bookable' => true, 'accepts_online_bookings' => false,
        'country_code' => 'IN', 'time_zone' => 'Asia/Kolkata', 'address' => '18 Example Lane',
        'phone' => '+919876543210', 'schedule_preset' => 'weekdays', 'service_keys' => ['cut_finish'],
    ], ...$changes];
}
function setupTenant(): array
{
    [$user, $business, $membership] = createTenantMembership();
    activateTestSubscription($business);
    app(OnboardingManager::class)->resume($business);
    $business = app(StarterWorkspaceProvisioner::class)->provision($business, $membership, $user, setupAnswers());

    return [$user, $business, $business->locations()->sole(), $business->staffProfiles()->sole(), $business->services()->sole()];
}
function setupVisit($business, $location): Appointment
{
    return Appointment::query()->create([
        'business_id' => $business->id, 'location_id' => $location->id, 'idempotency_key' => (string) Str::uuid(),
        'request_hash' => str_repeat('a', 64), 'status' => 'confirmed', 'source' => 'internal',
        'starts_at_utc' => now()->addDays(2), 'ends_at_utc' => now()->addDays(2)->addHour(),
        'time_zone' => $location->time_zone, 'local_starts_at' => '2035-10-10 10:00:00', 'local_ends_at' => '2035-10-10 11:00:00',
        'currency_code' => 'INR', 'price_minor' => 50000,
    ]);
}
function hoursProposal($location): array
{
    return ['revision' => app(SetupHoursController::class)->revision($location->fresh()),
        'windows' => [['day_of_week' => 1, 'opens_at' => '10:00', 'closes_at' => '12:00', 'sequence' => 1],
            ['day_of_week' => 1, 'opens_at' => '13:00', 'closes_at' => '18:00', 'sequence' => 2]]];
}

it('is operational before publication without logos providers or public contact fields', function () {
    [$user, $b] = setupTenant();
    $b->update(['logo_path' => null, 'phone' => null, 'email' => null, 'address' => null, 'terms_url' => null]);
    $summary = app(BusinessSetupProgress::class)->for($b->fresh());
    expect($summary['required_complete'])->toBeTrue()->and($summary['completed'])->toBe(6)
        ->and($summary['publication']['publishable'])->toBeFalse()->and($summary['online_state'])->toBe('Not published');
    $this->actingAs($user)->get(route('business.configuration.show', $b))->assertOk()
        ->assertInertia(fn (Assert $p) => $p->where('setupSummary.required_complete', true)->has('serviceReview', 1)->where('serviceReview.0.bookable', true));
});

it('recomputes readiness after configuration breaks elsewhere', function (string $problem) {
    [, $b, $location, $staff, $service] = setupTenant();
    match ($problem) {
        'inactive service' => $service->update(['is_active' => false]),
        'no duration' => $service->update(['duration_minutes' => 0]),
        'too long' => $service->update(['duration_minutes' => 900]),
        'wrong currency' => $service->update(['currency_code' => 'USD']),
        'inactive staff' => $staff->update(['status' => 'inactive']),
        'no qualification' => $service->staffAssignments()->update(['is_qualified' => false]),
        'expired qualification' => $service->staffAssignments()->update(['effective_until' => now()->subDay()]),
        'expired hours' => $staff->availabilityRules()->update(['ends_on' => now()->subDay()->toDateString()]),
        'nonmatching hours' => $staff->availabilityRules()->update(['starts_at' => '20:00', 'ends_at' => '21:00']),
        'closed location' => $location->update(['is_active' => false]),
        'different branch' => $staff->locations()->detach(),
        'closed month' => LocationScheduleException::query()->create(['business_id' => $b->id, 'location_id' => $location->id, 'kind' => 'closure', 'name' => 'Renovation', 'starts_on' => now()->toDateString(), 'ends_on' => now()->addDays(30)->toDateString(), 'reason' => 'Renovation']),
    };
    $summary = app(BusinessSetupProgress::class)->for($b->fresh());
    expect($summary['required_complete'])->toBeFalse()->and($summary['counts']['bookable_services'])->toBe(0)->and($summary['next'])->not->toBeNull();
})->with(['inactive service', 'no duration', 'too long', 'wrong currency', 'inactive staff', 'no qualification', 'expired qualification', 'expired hours', 'nonmatching hours', 'closed location', 'different branch', 'closed month']);

it('does not treat an assignment expiring before opening as a delivery path', function () {
    [, $b, , , $service] = setupTenant();
    $service->staffAssignments()->update(['effective_until' => now()->startOfDay()->addHours(3)]);
    expect(app(ReadinessEvaluator::class)->inspect($b)['counts']['bookable_services'])->toBe(0);
});

it('keeps internal bookings ready while online staff visibility is off', function () {
    [, $b, , $staff] = setupTenant();
    $staff->update(['online_visible' => false]);
    app(OnboardingManager::class)->markPreviewed($b);
    $summary = app(BusinessSetupProgress::class)->for($b->fresh());
    expect($summary['required_complete'])->toBeTrue()->and($summary['publication']['publishable'])->toBeFalse()->and($summary['online_service_ids'])->toBe([]);
});

it('allows removing every starter suggestion and does not put them back on retry', function () {
    [$user, $b, $member] = createTenantMembership();
    activateTestSubscription($b);
    $session = app(OnboardingManager::class)->resume($b);
    $session->update(['answers' => setupAnswers()]);
    $this->actingAs($user)->post(route('business.configuration.guided-onboarding.complete', $b), ['service_keys' => []])->assertSessionHasNoErrors();
    expect($b->services()->count())->toBe(0)->and($b->fresh()->onboardingSession->guided_completed_at)->not->toBeNull();
    $this->post(route('business.configuration.guided-onboarding.complete', $b), ['service_keys' => ['cut_finish']])->assertSessionHasNoErrors();
    expect($b->services()->count())->toBe(0)->and($b->staffProfiles()->count())->toBe(1)->and($b->locations()->count())->toBe(1);
});

it('rolls starter initialization back and recovers after validation fails', function () {
    [$user, $b, $member] = createTenantMembership();
    app(OnboardingManager::class)->resume($b);
    expect(fn () => app(StarterWorkspaceProvisioner::class)->provision($b, $member, $user, setupAnswers(['service_keys' => ['unknown']])))->toThrow(ValidationException::class);
    expect($b->locations()->count())->toBe(0)->and($b->staffProfiles()->count())->toBe(0)->and($b->fresh()->business_type)->toBeNull();
    app(StarterWorkspaceProvisioner::class)->provision($b->fresh(), $member, $user, setupAnswers());
    expect($b->services()->count())->toBe(1)->and($b->staffProfiles()->count())->toBe(1);
});

it('preserves prepared commerce settings rather than resetting them', function () {
    [$user, $b, $member] = createTenantMembership();
    app(OnboardingManager::class)->resume($b);
    $settings = CommerceSetting::query()->create(['business_id' => $b->id, 'currency_code' => 'INR', 'default_tax_rate_bps' => 1800, 'tax_inclusive' => true, 'cancellation_cutoff_minutes' => 90]);
    app(StarterWorkspaceProvisioner::class)->provision($b, $member, $user, setupAnswers());
    expect($settings->fresh()->default_tax_rate_bps)->toBe(1800)->and($settings->fresh()->cancellation_cutoff_minutes)->toBe(90);
});

it('will not restore starter defaults over an established business', function () {
    [$user, $b, $member] = createTenantMembership();
    $location = Location::factory()->create(['business_id' => $b->id]);
    setupVisit($b, $location);
    app(OnboardingManager::class)->resume($b);
    expect(fn () => app(StarterWorkspaceProvisioner::class)->provision($b, $member, $user, setupAnswers()))->toThrow(ValidationException::class);
    expect($b->fresh()->business_type)->toBeNull()->and($b->services()->count())->toBe(0);
});

it('invalidates saved starter choices when business type changes and preserves other answers', function () {
    [, $b] = createTenantMembership();
    $manager = app(OnboardingManager::class);
    $manager->resume($b)->update(['answers' => setupAnswers()]);
    $session = $manager->saveGuidedAnswers($b, 'business_type', ['business_type' => 'barber_shop']);
    expect($session->answers)->not->toHaveKey('service_keys')->and($session->answers['phone'])->toBe('+919876543210');
});

it('reviews and saves split opening hours idempotently without rewriting appointments', function () {
    [$user, $b, $location] = setupTenant();
    $visit = setupVisit($b, $location);
    $snapshot = $visit->fresh()->getAttributes();
    $data = hoursProposal($location);
    $preview = $this->actingAs($user)->postJson(route('business.configuration.setup-hours.review', [$b, $location]), $data)->assertOk()->assertJsonPath('count', 1)->json('public_id');
    $url = route('business.configuration.setup-hours.save', [$b, $location]);
    $this->putJson($url, [...$data, 'preview_id' => $preview])->assertUnprocessable()->assertJsonValidationErrors('reason');
    $data = [...$data, 'preview_id' => $preview, 'reason' => 'Existing appointments reviewed in Calendar'];
    $this->put($url, $data)->assertRedirect()->assertSessionHasNoErrors();
    $this->put($url, $data)->assertRedirect()->assertSessionHasNoErrors();
    expect($location->hours()->count())->toBe(2)->and($visit->fresh()->getAttributes())->toBe($snapshot)
        ->and(AuditEvent::query()->where('business_id', $b->id)->where('action', 'configuration.location_hours.updated')->count())->toBe(1);
});

it('rejects changed payloads stale reviews and active client holds', function (string $problem) {
    [$user, $b, $location] = setupTenant();
    $data = hoursProposal($location);
    $preview = $this->actingAs($user)->postJson(route('business.configuration.setup-hours.review', [$b, $location]), $data)->assertOk()->json('public_id');
    if ($problem === 'payload') {
        $data['windows'][0]['opens_at'] = '10:30';
    }
    if ($problem === 'hours') {
        $location->hours()->first()->update(['opens_at' => '08:00']);
    }
    if ($problem === 'appointment') {
        setupVisit($b, $location);
    }
    if ($problem === 'expired') {
        CarbonImmutable::setTestNow(now()->addMinutes(16));
    }
    if ($problem === 'hold') {
        CapacityHold::query()->create(['business_id' => $b->id, 'location_id' => $location->id, 'idempotency_key' => (string) Str::uuid(), 'request_hash' => str_repeat('a', 64), 'source' => 'online', 'owner_key' => 'synthetic', 'starts_at_utc' => now()->addDays(2), 'ends_at_utc' => now()->addDays(2)->addHour(), 'expires_at' => now()->addMinutes(5)]);
    }
    $this->putJson(route('business.configuration.setup-hours.save', [$b, $location]), [...$data, 'preview_id' => $preview])->assertUnprocessable();
    expect($location->hours()->count())->toBe(5)->and(AuditEvent::query()->where('action', 'configuration.location_hours.updated')->count())->toBe(0);
})->with(['payload', 'hours', 'appointment', 'expired', 'hold']);

it('rejects overlapping and backwards hours with an actionable field error', function () {
    [$user, $b, $location] = setupTenant();
    $data = hoursProposal($location);
    $data['windows'][0]['closes_at'] = '09:00';
    $this->actingAs($user)->postJson(route('business.configuration.setup-hours.review', [$b, $location]), $data)->assertUnprocessable()->assertJsonValidationErrors('windows.0.closes_at');
    $data = hoursProposal($location);
    $data['windows'][1]['opens_at'] = '11:00';
    $this->postJson(route('business.configuration.setup-hours.review', [$b, $location]), $data)->assertUnprocessable()->assertJsonValidationErrors('windows');
});

it('enforces tenant and branch scope for the summary and new editor', function () {
    [$owner, $b, $location] = setupTenant();
    [$foreign, $other] = createTenantMembership();
    activateTestSubscription($other);
    $this->actingAs($foreign)->get(route('business.configuration.show', $b))->assertForbidden();
    $this->postJson(route('business.configuration.setup-hours.review', [$other, $location]), hoursProposal($location))->assertNotFound();
    $member = $b->memberships()->create(['user_id' => $foreign->id, 'status' => 'active']);
    app(MembershipAccessManager::class)->assignStarterRole($member, StarterRole::Manager, $owner, 'Synthetic manager');
    $member->locations()->syncWithPivotValues([$location->id], ['business_id' => $b->id]);
    Location::factory()->create(['business_id' => $b->id]);
    $this->actingAs($foreign)->get(route('business.configuration.show', $b))->assertForbidden();
    $this->postJson(route('business.configuration.setup-hours.review', [$b, $location]), hoursProposal($location))->assertForbidden();
});

it('loads readiness in a bounded number of queries even with a large catalogue', function () {
    [, $b, $location, $staff, $service] = setupTenant();
    foreach (range(1, 50) as $i) {
        $s = $service->replicate();
        $s->public_id = (string) Str::ulid();
        $s->name = 'Review service '.$i;
        $s->save();
        $s->locations()->syncWithPivotValues([$location->id], ['business_id' => $b->id, 'is_eligible' => true]);
        $assignment = $service->staffAssignments()->first()->replicate();
        $assignment->service_id = $s->id;
        $assignment->save();
    }
    DB::enableQueryLog();
    DB::flushQueryLog();
    $summary = app(ReadinessEvaluator::class)->inspect($b->fresh());
    $queries = count(DB::getQueryLog());
    DB::disableQueryLog();
    expect($summary['counts']['bookable_services'])->toBe(51)->and($queries)->toBeLessThan(18);
});

function setupProfilePayload($business): array
{
    return $business->only(['name', 'booking_slug', 'business_type', 'description', 'brand_color', 'country_code', 'locale', 'currency_code', 'time_zone', 'week_starts_on', 'appointment_interval_minutes', 'tax_posture', 'phone', 'email', 'website_url', 'social_links', 'address', 'map_url', 'default_cancellation_policy', 'terms_url', 'privacy_url']);
}
it('saves draft public profile fields without blocking internal operations', function () {
    [$user, $b] = setupTenant();
    $data = [...setupProfilePayload($b), 'phone' => null, 'email' => null, 'address' => null, 'terms_url' => null, 'privacy_url' => null, 'default_cancellation_policy' => null];
    $this->actingAs($user)->patch(route('business.configuration.profile.update', $b), $data)->assertSessionHasNoErrors();
    expect(app(BusinessSetupProgress::class)->for($b->fresh())['required_complete'])->toBeTrue()
        ->and(app(ReadinessEvaluator::class)->evaluate($b->fresh())->publishable)->toBeFalse();
});
it('rolls profile and commerce updates back when the booking slug is unavailable', function () {
    [$user, $b] = setupTenant();
    [, $other] = createTenantMembership();
    $other->update(['booking_slug' => 'already-taken']);
    $oldName = $b->name;
    $this->actingAs($user)->patch(route('business.configuration.profile.update', $b), [...setupProfilePayload($b), 'name' => 'Changed name', 'booking_slug' => 'already-taken', 'default_tax_rate_bps' => 1800, 'tax_posture' => 'inclusive'])->assertSessionHasErrors('booking_slug');
    expect($b->fresh()->name)->toBe($oldName)->and(CommerceSetting::query()->where('business_id', $b->id)->value('default_tax_rate_bps'))->toBe(0);
});
it('rejects stale setup profile revisions and preserves the newer saved settings', function () {
    [$user, $b] = setupTenant();
    $revision = '';
    $this->actingAs($user)->get(route('business.configuration.show', $b))->assertInertia(function (Assert $p) use (&$revision) {
        $revision = $p->toArray()['props']['business']['setup_revision'];

        return $p->where('setupSummary.required_complete', true);
    });
    $b->update(['name' => 'Newer saved name']);
    $this->patch(route('business.configuration.profile.update', $b), [...setupProfilePayload($b), 'name' => 'Old browser name', 'setup_revision' => $revision])->assertSessionHasErrors('setup_revision');
    expect($b->fresh()->name)->toBe('Newer saved name');
});

it('saves the calendar interval from booking preferences and rejects stale or unsupported values', function () {
    [$user, $b] = setupTenant();
    $revision = null;
    $this->actingAs($user)->get(route('business.configuration.show', $b))->assertInertia(function (Assert $p) use (&$revision) {
        $revision = $p->toArray()['props']['business']['setup_revision'];
    });
    $data = [...$b->only(['online_booking_enabled', 'online_staff_preference', 'online_price_display', 'online_new_client_rule', 'staff_gender_request_enabled', 'cancellation_cutoff_minutes', 'waitlist_offer_batch_size', 'public_link_ttl_minutes']), 'setup_revision' => $revision, 'appointment_interval_minutes' => 20];
    $url = route('business.configuration.public-booking-policy.update', $b);
    $this->patch($url, $data)->assertSessionHasNoErrors();
    expect($b->fresh()->appointment_interval_minutes)->toBe(20)->and($b->fresh()->online_booking_enabled)->toBeFalse();
    $this->patch($url, [...$data, 'appointment_interval_minutes' => 30])->assertSessionHasErrors('setup_revision');
    $this->patch($url, [...$data, 'appointment_interval_minutes' => 7])->assertSessionHasErrors('appointment_interval_minutes');
    expect($b->fresh()->appointment_interval_minutes)->toBe(20);
});

it('prepares an operational starter workspace without requiring public contact details', function () {
    [$user, $b] = createTenantMembership();
    activateTestSubscription($b);
    app(OnboardingManager::class)->resume($b)->update(['answers' => setupAnswers()]);
    $this->actingAs($user)->patch(route('business.configuration.guided-onboarding.update', $b), ['step' => 'location', 'country_code' => 'IN', 'time_zone' => 'Asia/Kolkata', 'address' => null, 'phone' => null, 'schedule_preset' => 'weekdays'])->assertSessionHasNoErrors();
    $this->post(route('business.configuration.guided-onboarding.complete', $b), ['service_keys' => ['cut_finish']])->assertSessionHasNoErrors();
    $summary = app(BusinessSetupProgress::class)->for($b->fresh());
    expect($summary['required_complete'])->toBeTrue()->and($summary['publication']['publishable'])->toBeFalse()
        ->and($b->fresh()->configuration_published_at)->toBeNull()->and($b->fresh()->phone)->toBe('');
});
