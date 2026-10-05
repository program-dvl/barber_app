<?php

use App\Domain\BusinessConfiguration\Models\Service;
use App\Domain\ClientRecords\Models\Client;
use App\Domain\ClientRecords\Models\ClientDuplicateCandidate;
use App\Domain\ClientRecords\Models\ClientNote;
use App\Domain\ClientRecords\Services\ClientIdentityService;
use App\Domain\ClientRecords\Services\ClientWorkspaceQuery;
use App\Domain\MoneyCommerce\Models\PaymentTransaction;
use App\Domain\MoneyCommerce\Models\Sale;
use App\Domain\PlatformAccess\Enums\PermissionName;
use App\Domain\PlatformAccess\Enums\StarterRole;
use App\Domain\PlatformAccess\Models\Location;
use App\Domain\PlatformAccess\Models\StaffProfile;
use App\Domain\SchedulingOperations\Models\Appointment;
use App\Domain\SchedulingOperations\Models\AppointmentSegment;
use App\Domain\SchedulingOperations\Models\AppointmentServiceLine;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);
afterEach(fn () => app(TenantContext::class)->clear());

function crmWorkspaceTenant(StarterRole $role = StarterRole::Owner): array
{
    [$user,$business,$membership] = createTenantMembership($role);
    activateTestSubscription($business);
    $location = Location::factory()->create(['business_id' => $business->id]);
    $membership->locations()->syncWithPivotValues([$location->id], ['business_id' => $business->id]);

    return compact('user', 'business', 'membership', 'location');
}
function crmWorkspaceVisit(array $tenant, Client $client, string $status = 'completed', int $days = -2, ?StaffProfile $staff = null): Appointment
{
    $start = now()->addDays($days);
    $visit = Appointment::query()->create(['business_id' => $tenant['business']->id, 'location_id' => $tenant['location']->id, 'client_id' => $client->id,
        'status' => $status, 'source' => 'walk_in', 'client_name' => $client->name, 'client_mobile' => $client->mobile, 'client_email' => $client->email,
        'starts_at_utc' => $start, 'ends_at_utc' => $start->copy()->addHour(), 'time_zone' => 'Asia/Kolkata',
        'local_starts_at' => $start->format('Y-m-d H:i:s').' +00:00', 'local_ends_at' => $start->copy()->addHour()->format('Y-m-d H:i:s').' +00:00',
        'price_minor' => 3500, 'currency_code' => 'INR', 'idempotency_key' => (string) Str::ulid(), 'request_hash' => hash('sha256', (string) Str::ulid())]);
    if ($staff) {
        $service = Service::query()->create(['business_id' => $client->business_id, 'name' => 'Cut', 'price_minor' => 3500, 'duration_minutes' => 60, 'currency_code' => 'INR']);
        $line = AppointmentServiceLine::query()->create(['business_id' => $client->business_id, 'appointment_id' => $visit->id, 'service_id' => $service->id, 'primary_staff_profile_id' => $staff->id, 'sequence' => 1, 'name' => 'Cut', 'price_minor' => 3500, 'currency_code' => 'INR', 'bookable_minutes' => 60, 'configuration_snapshot' => []]);
        AppointmentSegment::query()->create(['business_id' => $client->business_id, 'appointment_id' => $visit->id, 'appointment_service_line_id' => $line->id, 'staff_profile_id' => $staff->id, 'sequence' => 1, 'kind' => 'service', 'starts_at_utc' => $visit->starts_at_utc, 'ends_at_utc' => $visit->ends_at_utc, 'occupies_staff' => true, 'time_zone' => 'Asia/Kolkata', 'local_starts_at' => $visit->local_starts_at, 'local_ends_at' => $visit->local_ends_at]);
    }

    return $visit;
}

it('searches names without a blank phone wildcard and normalizes phone and email independently', function () {
    $t = crmWorkspaceTenant();
    $c = Client::factory()->create(['business_id' => $t['business']->id, 'name' => 'Maya Singh', 'normalized_name' => 'mayasingh', 'mobile' => '+91 90000 01003', 'normalized_mobile' => '919000001003', 'email' => 'Maya@example.test', 'normalized_email' => 'maya@example.test']);
    Client::factory()->create(['business_id' => $t['business']->id]);
    $foreign = crmWorkspaceTenant();
    Client::factory()->create(['business_id' => $foreign['business']->id, 'name' => 'Maya Singh', 'normalized_name' => 'mayasingh']);
    foreach (['Maya', '+91 90000 01003', 'MAYA@example.test', $c->public_id] as $search) {
        $this->actingAs($t['user'])->get(route('business.clients.index', ['business' => $t['business'], 'search' => $search]))->assertOk()->assertInertia(fn (Assert $p) => $p->has('clients.data', 1)->where('clients.data.0.public_id', $c->public_id));
    }
});

it('filters and sorts from all scoped visits and excludes future terminal appointments', function () {
    $t = crmWorkspaceTenant();
    $c = Client::factory()->create(['business_id' => $t['business']->id]);
    $fresh = Client::factory()->create(['business_id' => $t['business']->id]);
    crmWorkspaceVisit($t, $c, 'completed', -100);
    crmWorkspaceVisit($t, $c, 'no_show', 3);
    crmWorkspaceVisit($t, $c, 'rescheduled', 4);
    $this->actingAs($t['user'])->get(route('business.clients.index', ['business' => $t['business'], 'relationship' => 'lapsed']))->assertOk()->assertInertia(fn (Assert $p) => $p->has('clients.data', 1)->where('clients.data.0.visit_count', 1)->where('clients.data.0.next_appointment', null));
    crmWorkspaceVisit($t, $fresh, 'confirmed', 1);
    $this->get(route('business.clients.index', ['business' => $t['business'], 'relationship' => 'upcoming', 'sort' => 'next_appointment']))->assertOk()->assertInertia(fn (Assert $p) => $p->has('clients.data', 1)->where('clients.data.0.public_id', $fresh->public_id));
});

it('projects exact totals independently from bounded history and never serializes appointment contact snapshots', function () {
    $t = crmWorkspaceTenant();
    $c = Client::factory()->create(['business_id' => $t['business']->id]);
    for ($i = 1; $i <= 25; $i++) {
        crmWorkspaceVisit($t, $c, 'completed', -$i);
    }
    $next = crmWorkspaceVisit($t, $c, 'confirmed', 1);
    crmWorkspaceVisit($t, $c, 'no_show', 2);
    $this->actingAs($t['user'])->get(route('business.clients.show', [$t['business'], $c]))->assertOk()->assertInertia(fn (Assert $p) => $p->where('summary.visit_count', 25)->where('summary.no_shows', 1)->has('appointments', 20)->has('recentAppointments', 4)->where('recentAppointments.0.status', 'completed')->where('visitPagination.total', 27)->where('upcoming.public_id', $next->public_id)->missing('visitPagination.data')->missing('appointments.0.client_mobile'));
    $this->get(route('business.clients.show', ['business' => $t['business'], 'client' => $c, 'visits_page' => 2, 'section' => 'visits']))->assertOk()->assertInertia(fn (Assert $p) => $p->where('summary.visit_count', 25)->has('appointments', 7)->where('section', 'visits'));
});

it('calculates ledger net receipts and open balances per currency and presents append-only refund rows', function () {
    $t = crmWorkspaceTenant();
    $c = Client::factory()->create(['business_id' => $t['business']->id]);
    $visit = crmWorkspaceVisit($t, $c);
    $sale = Sale::query()->create(['business_id' => $c->business_id, 'location_id' => $t['location']->id, 'client_id' => $c->id, 'appointment_id' => $visit->id, 'status' => 'completed', 'calculation_snapshot' => [], 'currency_code' => 'INR', 'paid_minor' => 3000, 'deposit_applied_minor' => 500, 'refunded_minor' => 700, 'tip_minor' => 100, 'total_minor' => 3500, 'balance_minor' => 0, 'completed_at' => now()]);
    Sale::query()->create(['business_id' => $c->business_id, 'location_id' => $t['location']->id, 'client_id' => $c->id, 'status' => 'open', 'calculation_snapshot' => [], 'currency_code' => 'INR', 'balance_minor' => 1800]);
    Sale::query()->create(['business_id' => $c->business_id, 'location_id' => $t['location']->id, 'client_id' => $c->id, 'status' => 'completed', 'calculation_snapshot' => [], 'currency_code' => 'USD', 'paid_minor' => 6000, 'completed_at' => now()]);
    PaymentTransaction::query()->create(['business_id' => $c->business_id, 'sale_id' => $sale->id, 'kind' => 'refund', 'method' => 'cash', 'status' => 'succeeded', 'amount_minor' => 700, 'currency_code' => 'INR', 'idempotency_key' => 'crm-refund', 'occurred_at' => now(), 'evidence' => ['private' => 'do not serialize']]);
    $this->actingAs($t['user'])->get(route('business.clients.show', [$t['business'], $c]))->assertOk()->assertInertia(fn (Assert $p) => $p->has('financial.totals', 2)->where('financial.totals.0.net_minor', 2800)->where('financial.totals.0.outstanding_minor', 1800)->where('financial.totals.1.net_minor', 6000)->where('financial.payments.data.0.kind', 'refund')->missing('financial.payments.data.0.evidence')->where('financial.awaiting_checkout', 0));
});

it('redacts finance contacts and sensitive context and scopes own visits to assigned locations', function () {
    $t = crmWorkspaceTenant(StarterRole::BarberStylist);
    $c = Client::factory()->create(['business_id' => $t['business']->id]);
    $staff = $t['membership']->staffProfile ?? StaffProfile::factory()->create(['business_id' => $t['business']->id, 'membership_id' => $t['membership']->id, 'user_id' => $t['user']->id]);
    $other = StaffProfile::factory()->create(['business_id' => $t['business']->id]);
    crmWorkspaceVisit($t, $c, 'completed', -2, $staff);
    crmWorkspaceVisit($t, $c, 'completed', -3, $other);
    $elsewhere = $t;
    $elsewhere['location'] = Location::factory()->create(['business_id' => $t['business']->id]);
    crmWorkspaceVisit($elsewhere, $c, 'completed', -4, $staff);
    ClientNote::query()->create(['business_id' => $c->business_id, 'client_id' => $c->id, 'kind' => 'warning', 'visibility' => 'sensitive', 'content' => 'Private', 'is_important' => true]);
    app(TenantContext::class)->activate($t['business'], $t['membership']);
    $t['membership']->syncRoles([]);
    $t['membership']->syncPermissions([PermissionName::CalendarViewOwn->value, PermissionName::ClientView->value, PermissionName::ClientManage->value]);
    $this->actingAs($t['user'])->get(route('business.clients.show', [$t['business'], $c]))->assertOk()->assertInertia(fn (Assert $p) => $p->where('client.mobile', null)->where('client.email', null)->where('financial', null)->where('permissions.finance', false)->where('summary.visit_count', 1)->has('appointments', 1)->has('notes', 0)->where('appointments.0.price_minor', null));
    $this->get(route('business.clients.index', ['business' => $t['business'], 'search' => $c->mobile]))->assertOk()->assertInertia(fn (Assert $p) => $p->has('clients.data', 0));
    $this->patch(route('business.clients.update', [$t['business'], $c]), ['name' => $c->name, 'mobile' => null, 'email' => null, 'version' => 1, 'reason' => 'Preference update', 'preferred_services' => [], 'tags' => []])->assertRedirect();
    expect($c->fresh()->mobile)->toBe($c->mobile)->and($c->fresh()->email)->toBe($c->email);
});

it('warns before different people sharing contacts and keeps exact intake reuse', function () {
    $t = crmWorkspaceTenant();
    $c = Client::factory()->create(['business_id' => $t['business']->id, 'name' => 'Maya Singh', 'normalized_name' => 'mayasingh', 'mobile' => '+919000001003', 'normalized_mobile' => '919000001003']);
    $payload = ['name' => 'Maya S', 'mobile' => $c->mobile];
    $this->actingAs($t['user'])->get(route('business.clients.matches', ['business' => $t['business'], 'mobile' => '+91 90000 01003']))->assertOk()->assertJsonPath('clients.0.public_id', $c->public_id);
    $this->post(route('business.clients.store', $t['business']), $payload)->assertSessionHasErrors('duplicate_confirmed');
    expect(Client::query()->count())->toBe(1);
    $this->post(route('business.clients.store', $t['business']), [...$payload, 'duplicate_confirmed' => true])->assertRedirect();
    expect(Client::query()->count())->toBe(2);
    $this->post(route('business.clients.store', $t['business']), ['name' => $c->name, 'mobile' => $c->mobile])->assertRedirect(route('business.clients.show', [$t['business'], $c]));
    expect(Client::query()->count())->toBe(2);
});

it('keeps normalized identity consistent when authorized contacts are cleared', function () {
    $t = crmWorkspaceTenant();
    $c = Client::factory()->create(['business_id' => $t['business']->id]);
    app(TenantContext::class)->activate($t['business'], $t['membership']);
    $fresh = app(ClientIdentityService::class)->updateProfile($c, ['email' => null, 'mobile' => null], 1, 'Client requested removal');
    expect($fresh->mobile)->toBeNull()->and($fresh->normalized_mobile)->toBeNull()->and($fresh->email)->toBeNull()->and($fresh->normalized_email)->toBeNull();
});

it('validates canonical client handoffs and rejects foreign or inactive identities', function () {
    $t = crmWorkspaceTenant();
    $c = Client::factory()->create(['business_id' => $t['business']->id]);
    $this->actingAs($t['user'])->get(route('business.calendar', ['business' => $t['business'], 'client' => $c->public_id, 'create' => 1]))->assertOk()->assertInertia(fn (Assert $p) => $p->where('clientPrefill.id', $c->public_id)->where('clientPrefill.name', $c->name));
    $this->get(route('business.walk-ins.index', ['business' => $t['business'], 'client' => $c->public_id]))->assertOk()->assertInertia(fn (Assert $p) => $p->where('clientPrefill.public_id', $c->public_id));
    $foreign = crmWorkspaceTenant();
    $other = Client::factory()->create(['business_id' => $foreign['business']->id]);
    $this->get(route('business.calendar', ['business' => $t['business'], 'client' => $other->public_id]))->assertNotFound();
    $c->update(['status' => 'inactive']);
    $this->get(route('business.calendar', ['business' => $t['business'], 'client' => $c->public_id]))->assertNotFound();
});

it('rebooks with current eligible services and requires review of unavailable staff or services', function () {
    $t = crmWorkspaceTenant();
    $client = Client::factory()->create(['business_id' => $t['business']->id]);
    $staff = StaffProfile::factory()->create(['business_id' => $t['business']->id]);
    $staff->locations()->attach($t['location']->id, ['business_id' => $t['business']->id]);
    $visit = crmWorkspaceVisit($t, $client, staff: $staff);
    $service = $visit->serviceLines->first()->service;
    $service->locations()->attach($t['location']->id, ['business_id' => $t['business']->id, 'is_eligible' => true]);
    $url = route('business.calendar', ['business' => $t['business'], 'client' => $client->public_id, 'rebook' => $visit->public_id]);
    $this->actingAs($t['user'])->get($url)->assertOk()->assertInertia(fn (Assert $p) => $p->where('clientPrefill.lines.0.service', $service->public_id)->where('clientPrefill.lines.0.staff', $staff->public_id)->where('clientPrefill.lines.0.duration_minutes', null)->where('clientPrefill.rebooking', true));
    $staff->update(['status' => 'inactive']);
    $this->get($url)->assertOk()->assertInertia(fn (Assert $p) => $p->where('clientPrefill.lines.0.staff', ''));
    $service->update(['is_active' => false]);
    $this->get($url)->assertOk()->assertInertia(fn (Assert $p) => $p->has('clientPrefill.lines', 0)->where('clientPrefill.skippedServices', 1));
});

it('attributes owner notes through their tenant audit without exposing audit payloads', function () {
    $t = crmWorkspaceTenant();
    $client = Client::factory()->create(['business_id' => $t['business']->id]);
    $this->actingAs($t['user'])->post(route('business.clients.notes.store', [$t['business'], $client]), ['kind' => 'preference', 'visibility' => 'standard', 'content' => 'Quiet appointment please', 'important' => true])->assertRedirect();
    $this->get(route('business.clients.show', [$t['business'], $client]))->assertOk()->assertInertia(fn (Assert $p) => $p->where('notes.0.author', $t['user']->name)->where('notes.0.important', true)->missing('notes.0.audit'));
});

it('keeps directory query growth constant from one to thirty clients', function () {
    $t = crmWorkspaceTenant();
    app(TenantContext::class)->activate($t['business'], $t['membership']);
    $staff = StaffProfile::factory()->create(['business_id' => $t['business']->id]);
    Client::factory()->create(['business_id' => $t['business']->id, 'preferred_staff_profile_id' => $staff->id]);
    $workspace = app(ClientWorkspaceQuery::class);
    $filters = ['search' => '', 'relationship' => '', 'staff' => '', 'sort' => 'name'];
    $workspace->directory($t['membership'], $filters); // warm permission relations
    DB::enableQueryLog();
    DB::flushQueryLog();
    $workspace->directory($t['membership'], $filters);
    $one = count(DB::getQueryLog());
    Client::factory()->count(29)->create(['business_id' => $t['business']->id, 'preferred_staff_profile_id' => $staff->id]);
    DB::flushQueryLog();
    $result = $workspace->directory($t['membership'], $filters);
    $many = count(DB::getQueryLog());
    DB::disableQueryLog();
    expect($result->count())->toBe(30)->and($many)->toBe($one)->and($many)->toBeLessThanOrEqual(4);
});

it('exposes only permissioned duplicate reviews and handles punctuation-only search', function () {
    $t = crmWorkspaceTenant();
    $c = Client::factory()->create(['business_id' => $t['business']->id]);
    $other = Client::factory()->create(['business_id' => $t['business']->id]);
    ClientDuplicateCandidate::query()->create(['business_id' => $t['business']->id, 'first_client_id' => $c->id, 'second_client_id' => $other->id, 'status' => 'pending', 'confidence' => 70, 'reasons' => ['same_normalized_mobile'], 'detected_at' => now()]);
    $this->actingAs($t['user'])->get(route('business.clients.index', ['business' => $t['business'], 'relationship' => 'duplicates']))->assertOk()->assertInertia(fn (Assert $p) => $p->has('clients.data', 2)->where('duplicateCount', 1));
    $this->get(route('business.clients.index', ['business' => $t['business'], 'search' => '%%%']))->assertOk()->assertInertia(fn (Assert $p) => $p->has('clients.data', 0));
});
