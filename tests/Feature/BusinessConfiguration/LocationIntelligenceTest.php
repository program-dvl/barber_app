<?php

use App\Domain\BusinessConfiguration\Models\LocationHour;
use App\Domain\ClientRecords\Models\Client;
use App\Domain\ClientRecords\Models\ClientConsent;
use App\Domain\Communications\Models\CommunicationMessage;
use App\Domain\Communications\Services\CommunicationTemplateRenderer;
use App\Domain\Communications\Services\OperationalCommunicationService;
use App\Domain\PlatformAccess\Enums\StarterRole;
use App\Domain\PlatformAccess\Models\Location;
use App\Domain\PublicBooking\Services\SecureAppointmentLinkService;
use App\Domain\SchedulingOperations\Models\Appointment;
use App\Domain\SchedulingOperations\Models\OperationalNotificationEvent;
use App\Support\Locations\LocationData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);
beforeEach(function () {
    $this->withoutVite();
    config(['inertia.ssr.enabled' => false]);
    Http::preventStrayRequests();
});

function locationOwner(): array
{
    [$user, $business, $membership] = createTenantMembership();
    activateTestSubscription($business);
    $location = Location::factory()->create(['business_id' => $business->id, 'address' => '18 Example Lane', 'latitude' => '12.1234567', 'longitude' => '77.1234567']);
    $membership->locations()->syncWithPivotValues([$location->id], ['business_id' => $business->id]);

    return [$user, $business, $location, $membership];
}
function locationSave($location, array $changes = []): array
{
    return [...['location' => $location->public_id, 'name' => $location->name, 'address' => $location->address, 'time_zone' => $location->time_zone, 'working_days' => [1, 2, 3, 4, 5], 'opens_at' => '09:00', 'closes_at' => '18:00', 'keep_hours' => true], ...$changes];
}
function locationVisit($business, $location): Appointment
{
    return Appointment::query()->create(['business_id' => $business->id, 'location_id' => $location->id, 'idempotency_key' => (string) Str::uuid(), 'request_hash' => str_repeat('a', 64), 'status' => 'confirmed', 'source' => 'internal', 'starts_at_utc' => now()->addDays(3), 'ends_at_utc' => now()->addDays(3)->addHour(), 'time_zone' => $location->time_zone, 'local_starts_at' => now()->addDays(3)->format('Y-m-d H:i:s'), 'local_ends_at' => now()->addDays(3)->addHour()->format('Y-m-d H:i:s'), 'price_minor' => 3500, 'currency_code' => 'USD', 'client_name' => 'Location test', 'client_email' => 'guest@example.test', 'communication_preferences' => ['email']]);
}

it('saves confirmed structured addresses and entrance pins while retaining provider-free legacy geometry and split hours', function () {
    [$user,$business,$location] = locationOwner();
    foreach ([['09:00', '12:00'], ['13:00', '18:00']] as $index => [$from,$until]) {
        LocationHour::query()->create(['business_id' => $business->id, 'location_id' => $location->id, 'day_of_week' => 1, 'opens_at' => $from, 'closes_at' => $until, 'sequence' => $index + 1]);
    }
    $hours = $location->hours()->pluck('id')->all();
    $this->actingAs($user)->post(route('business.locations.activation.store', $business), locationSave($location, ['address_line_1' => '18 Example Lane', 'city' => 'Toronto', 'country_code' => 'CA', 'google_place_id' => 'ChIJ-test', 'entrance_latitude' => 43.65, 'entrance_longitude' => -79.38, 'entrance_instructions' => 'Floor 2', 'parking_instructions' => 'Rear lot', 'is_primary' => true, 'location_revision' => LocationData::revision($location)]))->assertSessionHasNoErrors();
    expect($location->fresh()->google_place_id)->toBe('ChIJ-test')->and($location->fresh()->entrance_latitude)->toBe('43.6500000')->and($location->fresh()->latitude)->toBe(12.1234567)->and($location->hours()->pluck('id')->all())->toBe($hours);
    $this->get(route('business.locations.index', $business))->assertOk()->assertInertia(fn (Assert $page) => $page->where('locations.0.entrance_instructions', 'Floor 2'));
});
it('supports manual incomplete regional addresses with no coordinates', function () {
    [$user,$business,$location] = locationOwner();
    $this->actingAs($user)->post(route('business.locations.activation.store', $business), locationSave($location, ['address' => 'Market Road, next to the old post office', 'google_place_id' => null, 'entrance_latitude' => null, 'entrance_longitude' => null]))->assertSessionHasNoErrors();
    expect($location->fresh()->address)->toContain('old post office')->and($location->fresh()->entrance_latitude)->toBeNull();
});
it('rejects unsafe incomplete coordinates empty addresses and unknown country codes', function (array $changes, string $field) {
    [$user,$business,$location] = locationOwner();
    $this->actingAs($user)->post(route('business.locations.activation.store', $business), locationSave($location, $changes))->assertSessionHasErrors($field);
    expect($location->fresh()->address)->toBe('18 Example Lane');
})->with([[['address' => '   '], 'address'], [['entrance_latitude' => 91, 'entrance_longitude' => 0], 'entrance_latitude'], [['entrance_latitude' => 0], 'entrance_longitude'], [['entrance_longitude' => 181, 'entrance_latitude' => 0], 'entrance_longitude'], [['country_code' => 'ZZ'], 'country_code'], [['google_place_id' => '<script>'], 'google_place_id']]);
it('preserves metadata omitted by legacy callers and detects stale location edits', function () {
    [$user,$business,$location] = locationOwner();
    $location->update(['entrance_latitude' => 0, 'entrance_longitude' => 0, 'google_place_id' => 'Place-test']);
    $revision = LocationData::revision($location->fresh());
    $this->actingAs($user)->post(route('business.locations.activation.store', $business), locationSave($location, ['name' => 'Updated branch']))->assertSessionHasNoErrors();
    expect($location->fresh()->entrance_latitude)->toBe('0.0000000')->and($location->fresh()->google_place_id)->toBe('Place-test');
    $this->post(route('business.locations.activation.store', $business), locationSave($location, ['location_revision' => $revision]))->assertSessionHasErrors('location');
});
it('cannot edit another tenant or use branch-only settings access to manage all locations', function () {
    [$user,$business,$location] = locationOwner();
    [,,$other] = locationOwner();
    $this->actingAs($user)->post(route('business.locations.activation.store', $business), locationSave($other))->assertNotFound();
    [$manager,$managed,$membership] = createTenantMembership(StarterRole::Manager);
    activateTestSubscription($managed);
    Location::factory()->create(['business_id' => $managed->id]);
    $this->actingAs($manager)->get(route('business.locations.index', $managed))->assertForbidden();
});
it('keeps only one primary branch and rejects duplicate names without adding records', function () {
    [$user,$business,$location] = locationOwner();
    $location->update(['is_primary' => true]);
    $second = Location::factory()->create(['business_id' => $business->id, 'address' => '20 Other Lane']);
    $this->actingAs($user)->post(route('business.locations.activation.store', $business), locationSave($second, ['is_primary' => true]))->assertSessionHasNoErrors();
    expect($business->locations()->where('is_primary', true)->count())->toBe(1)->and($location->fresh()->is_primary)->toBeFalse();
    $this->post(route('business.locations.activation.store', $business), locationSave($second, ['location' => null, 'create_new' => true, 'keep_hours' => false, 'name' => $location->name]))->assertSessionHasErrors('name');
    expect($business->locations()->count())->toBe(2);
});
it('freezes the actual booked branch through location and primary changes for details calendar and notifications', function () {
    [$user,$business,$location] = locationOwner();
    $location->update(['entrance_latitude' => 43.65, 'entrance_longitude' => -79.38, 'address_line_2' => 'Suite 2', 'entrance_instructions' => 'Side entrance', 'parking_instructions' => 'Rear lot']);
    $appointment = locationVisit($business, $location);
    $client = Client::factory()->create(['business_id' => $business->id, 'mobile' => '+14165550199', 'communication_preferences' => ['email', 'sms']]);
    $appointment->update(['client_id' => $client->id, 'client_mobile' => $client->mobile, 'communication_preferences' => ['email', 'sms']]);
    ClientConsent::create(['business_id' => $business->id, 'client_id' => $client->id, 'type' => 'sms', 'status' => 'granted', 'source' => 'booking', 'occurred_at' => now()]);
    $originalName = $location->name;
    $location->update(['name' => 'Moved branch', 'address' => '99 Changed Street', 'entrance_instructions' => 'Changed entrance']);
    Location::factory()->create(['business_id' => $business->id, 'is_primary' => true, 'address' => 'Other branch']);
    $links = app(SecureAppointmentLinkService::class);
    $link = $links->issue($appointment, 'view');
    $this->get(route('public.appointment.view', ['token' => $link['token']]))->assertOk()->assertInertia(fn (Assert $page) => $page->where('appointment.location', $originalName)->where('appointment.location_details.address', '18 Example Lane')->where('appointment.location_details.entrance_instructions', 'Side entrance'));
    $calendar = $this->get(route('public.appointment.calendar', ['token' => $link['token']]))->assertOk()->getContent();
    expect($calendar)->toContain('LOCATION:18 Example Lane', 'Side entrance', 'https://www.google.com/maps/dir/');
    app(OperationalCommunicationService::class)->process(OperationalNotificationEvent::query()->create(['business_id' => $business->id, 'subject_type' => Appointment::class, 'subject_id' => $appointment->id, 'event_type' => 'appointment.created', 'idempotency_key' => (string) Str::uuid(), 'payload' => [], 'occurred_at' => now(), 'status' => 'pending']));
    $messages = CommunicationMessage::query()->where('business_id', $business->id)->get();
    expect($messages)->not->toBeEmpty();
    expect($messages->pluck('channel')->unique()->sort()->values()->all())->toBe(['email', 'sms']);
    expect($messages->load('intent')->pluck('intent.intent_type')->unique()->sort()->values()->all())->toBe(['appointment_reminder', 'booking_confirmation']);
    foreach ($messages as $message) {
        expect($message->template_variables['location_address'])->toBe('18 Example Lane')->and($message->template_variables['location_name'])->toBe($originalName)->and($message->template_variables['directions_link'])->toContain('43.6500000%2C-79.3800000');
        $rendered = app(CommunicationTemplateRenderer::class)->render($message->template, [...$message->template_variables, 'action_link' => 'https://example.test/private', 'service_name' => 'Haircut', 'staff_name' => 'Avery']);
        expect($rendered['body'])->toContain('18 Example Lane', 'Side entrance', 'Rear lot', 'https://www.google.com/maps/dir/');
    }
    Http::assertNothingSent();
});
it('prevents timezone changes under upcoming appointments', function () {
    [$user,$business,$location] = locationOwner();
    locationVisit($business, $location);
    $this->actingAs($user)->post(route('business.locations.activation.store', $business), locationSave($location, ['time_zone' => 'Europe/London']))->assertSessionHasErrors('time_zone');
});
it('builds key-free navigation with entrance priority zero coordinates and place or address fallback', function () {
    $entrance = LocationData::present(['address' => '18 A & B Street', 'google_place_id' => 'Place-id', 'entrance_latitude' => 0, 'entrance_longitude' => 0]);
    expect($entrance['directions_url'])->toBe('https://www.google.com/maps/dir/?api=1&destination=0%2C0')->and($entrance['apple_maps_url'])->toContain('daddr=0%2C0');
    $place = LocationData::present(['address' => '18 A & B Street', 'google_place_id' => 'Place-id']);
    expect($place['directions_url'])->toContain('destination=18%20A%20%26%20B%20Street', 'destination_place_id=Place-id')->and($place['apple_maps_url'])->toBeNull();
});
it('backfills legacy snapshots and rolls back only added columns without rewriting appointment assignment', function () {
    [, $business,$location] = locationOwner();
    $appointment = locationVisit($business, $location);
    $migration = require database_path('migrations/2026_10_12_000002_add_location_intelligence.php');
    $migration->down();
    $migration->up();
    $row = DB::table('appointments')->find($appointment->id);
    expect(json_decode($row->location_snapshot, true)['address'])->toBe('18 Example Lane')->and((int) $row->location_id)->toBe($location->id)->and(DB::table('locations')->find($location->id)->latitude)->toBe(12.1234567);
});
