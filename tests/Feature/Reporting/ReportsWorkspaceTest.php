<?php

use App\Domain\BusinessConfiguration\Models\LocationHour;
use App\Domain\BusinessConfiguration\Models\Service;
use App\Domain\BusinessConfiguration\Models\StaffAvailabilityRule;
use App\Domain\ClientRecords\Models\Client;
use App\Domain\MoneyCommerce\Models\PaymentTransaction;
use App\Domain\MoneyCommerce\Models\Sale;
use App\Domain\MoneyCommerce\Models\SaleLine;
use App\Domain\PlatformAccess\Enums\PermissionName;
use App\Domain\PlatformAccess\Enums\StarterRole;
use App\Domain\PlatformAccess\Models\Business;
use App\Domain\PlatformAccess\Models\Location;
use App\Domain\PlatformAccess\Models\Membership;
use App\Domain\PlatformAccess\Models\StaffProfile;
use App\Domain\PlatformAccess\Services\MembershipAccessManager;
use App\Domain\Reporting\Services\ReportExportService;
use App\Domain\Reporting\Services\ReportMoney;
use App\Domain\Reporting\Services\ReportService;
use App\Domain\SchedulingOperations\Models\Appointment;
use App\Domain\SchedulingOperations\Models\AppointmentSegment;
use App\Domain\SchedulingOperations\Models\AppointmentServiceLine;
use App\Domain\SchedulingOperations\Models\WalkInEntry;
use App\Models\User;
use App\Support\Money\MoneyCalculator;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

uses(RefreshDatabase::class);

function analyticsTenant(StarterRole $role = StarterRole::Owner, string $zone = 'Asia/Kolkata'): array
{
    $business = Business::factory()->create(['currency_code' => 'INR', 'time_zone' => $zone]);
    activateTestSubscription($business);
    $location = Location::factory()->create(['business_id' => $business->id, 'time_zone' => $zone]);
    $user = User::factory()->create(['email_verified_at' => now()]);
    $membership = Membership::factory()->create(['business_id' => $business->id, 'user_id' => $user->id]);
    app(MembershipAccessManager::class)->assignStarterRole($membership, $role, $user, 'Report fixture.');
    $membership->locations()->attach($location->id, ['business_id' => $business->id]);
    $staff = StaffProfile::factory()->create(['business_id' => $business->id, 'membership_id' => $membership->id]);
    $staff->locations()->attach($location->id, ['business_id' => $business->id]);
    $service = Service::query()->create(['business_id' => $business->id, 'kind' => 'service', 'name' => 'Recorded cut', 'price_minor' => 10000, 'currency_code' => 'INR', 'duration_minutes' => 30, 'is_active' => true]);

    return compact('business', 'location', 'user', 'membership', 'staff', 'service');
}
function analyticsSale(array $p, string $key, string $at = '2026-10-01 06:00:00', array $options = []): Sale
{
    $lines = [['kind' => 'service', 'description' => $options['description'] ?? 'Recorded cut', 'quantity' => 1, 'unit_price_minor' => $options['price'] ?? 10000, 'discount_minor' => $options['discount'] ?? 1000, 'tax_rate_bps' => 500]];
    $snapshot = app(MoneyCalculator::class)->calculate($lines, $options['currency'] ?? 'INR', $options['inclusive'] ?? false, $options['tip'] ?? 500);
    $sale = Sale::query()->create(['business_id' => $p['business']->id, 'location_id' => $p['location']->id, 'client_id' => $options['client_id'] ?? null, 'status' => $options['status'] ?? 'completed', 'currency_code' => $snapshot['currency_code'], 'subtotal_minor' => $snapshot['subtotal_minor'], 'discount_minor' => $snapshot['discount_minor'], 'tax_minor' => $snapshot['tax_minor'], 'tip_minor' => $snapshot['tip_minor'], 'total_minor' => $snapshot['total_minor'], 'paid_minor' => $options['paid'] ?? $snapshot['total_minor'], 'balance_minor' => $options['balance'] ?? 0, 'deposit_applied_minor' => $options['deposit'] ?? 0, 'refunded_minor' => $options['refund'] ?? 0, 'calculation_snapshot' => $snapshot, 'completed_at' => $at, 'created_at' => $at]);
    SaleLine::query()->create(['business_id' => $p['business']->id, 'sale_id' => $sale->id, 'sequence' => 1, 'kind' => 'service', 'service_id' => $p['service']->id, 'staff_profile_id' => $options['staff_id'] ?? $p['staff']->id, 'source_snapshot' => [], ...$lines[0]]);

    return $sale;
}
function analyticsReport(array $p, string $key = 'sales', array $filters = []): array
{
    return app(TenantContext::class)->run($p['business'], $p['membership'], fn () => app(ReportService::class)->run($p['business'], $p['membership'], $key, ['start_date' => '2026-10-01', 'end_date' => '2026-10-01', ...$filters]));
}
function analyticsVisit(array $p, string $key, string $at, string $status = 'completed', int $minutes = 30): Appointment
{
    $from = CarbonImmutable::parse($at, 'UTC');
    $until = $from->addMinutes($minutes);
    $visit = Appointment::query()->create(['business_id' => $p['business']->id, 'location_id' => $p['location']->id, 'status' => $status, 'source' => 'reception', 'idempotency_key' => $key, 'request_hash' => hash('sha256', $key), 'starts_at_utc' => $from, 'ends_at_utc' => $until, 'time_zone' => $p['location']->time_zone, 'local_starts_at' => $from->setTimezone($p['location']->time_zone)->toIso8601String(), 'local_ends_at' => $until->setTimezone($p['location']->time_zone)->toIso8601String(), 'price_minor' => 10000, 'currency_code' => 'INR']);
    $line = AppointmentServiceLine::query()->create(['business_id' => $p['business']->id, 'appointment_id' => $visit->id, 'service_id' => $p['service']->id, 'primary_staff_profile_id' => $p['staff']->id, 'sequence' => 1, 'name' => 'Historical service', 'price_minor' => 10000, 'currency_code' => 'INR', 'bookable_minutes' => $minutes, 'configuration_snapshot' => []]);
    AppointmentSegment::query()->create(['business_id' => $p['business']->id, 'appointment_id' => $visit->id, 'appointment_service_line_id' => $line->id, 'staff_profile_id' => $p['staff']->id, 'kind' => 'service', 'sequence' => 1, 'starts_at_utc' => $from, 'ends_at_utc' => $until, 'time_zone' => $p['location']->time_zone, 'local_starts_at' => $visit->local_starts_at, 'local_ends_at' => $visit->local_ends_at, 'occupies_staff' => true]);

    return $visit;
}

it('keeps full SQL totals independent of pages and narrows search and exports consistently', function () {
    Storage::fake('private');
    $p = analyticsTenant();
    for ($i = 0; $i < 65; $i++) {
        analyticsSale($p, 'sale-'.$i, options: ['description' => $i < 5 ? 'Colour ritual' : 'Recorded cut']);
    }
    $r = analyticsReport($p);
    expect($r['rows'])->toHaveCount(30)->and($r['totals']['row_count'])->toBe(65)->and($r['totals']['collected_minor'])->toBe(9950 * 65);
    $page = analyticsReport($p, filters: ['page' => 3]);
    expect($page['rows'])->toHaveCount(5)->and($page['totals'])->toBe($r['totals']);
    $search = analyticsReport($p, 'service_revenue', ['search' => 'Colour ritual']);
    expect($search['totals']['quantity'])->toBe(5);
    $export = app(TenantContext::class)->run($p['business'], $p['membership'], fn () => app(ReportExportService::class)->queue($p['business'], $p['membership'], 'service_revenue', ['start_date' => '2026-10-01', 'end_date' => '2026-10-01', 'search' => 'Colour ritual']));
    expect($export->fresh()->row_count)->toBe(5)->and(Storage::disk('private')->get($export->fresh()->storage_path))->not->toContain('Recorded cut');
});
it('updates previous UTC boundaries and keeps DST and local midnight comparisons exact', function () {
    $p = analyticsTenant(zone: 'America/New_York');
    analyticsSale($p, 'current', '2026-11-01 05:30:00');
    analyticsSale($p, 'previous', '2026-10-31 04:30:00');
    analyticsSale($p, 'outside', '2026-11-02 05:00:00');
    $r = analyticsReport($p, filters: ['start_date' => '2026-11-01', 'end_date' => '2026-11-01']);
    expect($r['totals']['row_count'])->toBe(1)->and($r['previous_period']['totals']['row_count'])->toBe(1)->and($r['trend']['points'])->toHaveCount(25)->and(array_sum(array_column($r['trend']['points'], 'value')))->toBe(9950);
});
it('separates sale-cohort receipts from transaction-date partial payments and later returns', function () {
    $p = analyticsTenant();
    $sale = analyticsSale($p, 'old', '2026-09-01 06:00:00', ['refund' => 1000]);
    PaymentTransaction::query()->create(['business_id' => $p['business']->id, 'sale_id' => $sale->id, 'kind' => 'refund', 'status' => 'succeeded', 'method' => 'cash', 'amount_minor' => 1000, 'currency_code' => 'INR', 'idempotency_key' => 'later-return', 'reason' => 'Recorded return', 'occurred_at' => '2026-10-01 07:00:00']);
    $open = analyticsSale($p, 'partial', options: ['status' => 'open', 'paid' => 3000, 'balance' => 6950]);
    PaymentTransaction::query()->create(['business_id' => $p['business']->id, 'sale_id' => $open->id, 'kind' => 'payment', 'status' => 'succeeded', 'method' => 'card', 'amount_minor' => 3000, 'currency_code' => 'INR', 'idempotency_key' => 'partial', 'occurred_at' => '2026-10-01 08:00:00']);
    expect(analyticsReport($p)['totals']['row_count'])->toBe(0)->and(analyticsReport($p, 'refund')['totals']['refund_minor'])->toBe(1000)->and(analyticsReport($p, 'payment_activity')['totals']['collected_minor'])->toBe(2000)->and(analyticsReport($p, filters: ['statuses' => ['open', 'completed']])['totals']['outstanding_minor'])->toBe(6950);
});
it('preserves tax basis tips discounts deposits and current catalogue independence', function () {
    $p = analyticsTenant();
    $sale = analyticsSale($p, 'frozen', options: ['description' => 'Original name', 'paid' => 7000, 'deposit' => 2950, 'refund' => 1000]);
    $p['service']->update(['name' => 'New name', 'price_minor' => 999999, 'is_active' => false]);
    $p['staff']->update(['status' => 'inactive']);
    $r = analyticsReport($p);
    expect($r['totals']['gross_minor'])->toBe(10000)->and($r['totals']['discount_minor'])->toBe(1000)->and($r['totals']['tax_minor'])->toBe(450)->and($r['totals']['tip_minor'])->toBe(500)->and($r['totals']['collected_minor'])->toBe(8950)->and($r['rows'][0]['tax_basis'])->toBe('Exclusive')->and(analyticsReport($p, 'service_revenue')['rows'][0]['service'])->toBe('Original name');
});
it('never sums currencies and formats zero and three decimal currencies correctly', function () {
    $p = analyticsTenant();
    analyticsSale($p, 'inr');
    analyticsSale($p, 'usd', options: ['currency' => 'USD']);
    expect(analyticsReport($p)['totals']['row_count'])->toBe(1)->and(analyticsReport($p, filters: ['currency_code' => 'USD'])['totals']['row_count'])->toBe(1)->and(ReportMoney::decimal(1234, 'JPY'))->toBe('1234')->and(ReportMoney::decimal(1234, 'KWD'))->toBe('1.234');
});
it('counts distinct paying clients by their first sale before the selected period', function () {
    $p = analyticsTenant();
    $new = Client::query()->create(['business_id' => $p['business']->id, 'name' => 'New fixture', 'normalized_name' => 'new', 'status' => 'active']);
    $returning = Client::query()->create(['business_id' => $p['business']->id, 'name' => 'Returning fixture', 'normalized_name' => 'returning', 'status' => 'active']);
    analyticsSale($p, 'prior', '2026-09-01 05:00:00', ['client_id' => $returning->id]);
    foreach ([$new, $new, $returning] as $i => $client) {
        analyticsSale($p, 'visit-'.$i, options: ['client_id' => $client->id]);
    }analyticsSale($p, 'anonymous');
    $r = analyticsReport($p, 'client_classification');
    expect($r['totals']['new_count'])->toBe(1)->and($r['totals']['returning_count'])->toBe(1)->and($r['totals']['visit_count'])->toBe(3)->and($r['totals']['row_count'])->toBe(2);
});
it('independently restricts calendar-only staff and redacts financial popular-service columns', function () {
    $p = analyticsTenant(StarterRole::BarberStylist);
    $peer = StaffProfile::factory()->create(['business_id' => $p['business']->id]);
    $peer->locations()->attach($p['location']->id, ['business_id' => $p['business']->id]);
    app(TenantContext::class)->run($p['business'], $p['membership'], function () use ($p) {
        $p['membership']->syncRoles([]);
        $p['membership']->syncPermissions([PermissionName::CalendarViewOwn->value]);
    });
    analyticsVisit($p, 'own', '2026-10-01 05:00:00');
    analyticsVisit([...$p, 'staff' => $peer], 'peer', '2026-10-01 06:00:00');
    analyticsSale($p, 'own-sale');
    analyticsSale($p, 'peer-sale', options: ['staff_id' => $peer->id]);
    $r = analyticsReport($p, 'appointments');
    expect($r['totals']['row_count'])->toBe(1)->and($r['columns'])->not->toContain('expected_minor');
    $mix = analyticsReport($p, 'popular_service');
    expect($mix['totals']['quantity'])->toBe(1)->and($mix['columns'])->not->toContain('net_minor')->and($mix['rows'][0])->not->toHaveKey('net_minor');
    expect(fn () => analyticsReport($p))->toThrow(AccessDeniedHttpException::class);
});
it('uses filtered cancellation denominators without summing or assigning cause', function () {
    $p = analyticsTenant();
    foreach (['completed', 'no_show', 'cancelled_by_client', 'rescheduled'] as $i => $status) {
        analyticsVisit($p, 'outcome-'.$i, '2026-10-01 0'.(5 + $i).':00:00', $status);
    }
    $r = analyticsReport($p, 'cancellation_no_show');
    expect($r['totals']['row_count'])->toBe(2)->and($r['totals']['eligible_count'])->toBe(2)->and($r['totals']['no_show_percent'])->toBe(50.0)->and($r['totals']['cancellation_percent'])->toBe(33.3);
    expect(analyticsReport($p, 'appointments', ['start_date' => '2026-10-02', 'end_date' => '2026-10-02'])['totals']['no_show_percent'])->toBeNull();
});
it('uses Calendar windows and unions overlapping bookings while excluding breaks leave and closures', function () {
    $p = analyticsTenant();
    $date = CarbonImmutable::parse('2026-10-01', 'Asia/Kolkata');
    LocationHour::query()->create(['business_id' => $p['business']->id, 'location_id' => $p['location']->id, 'day_of_week' => $date->dayOfWeekIso, 'opens_at' => '09:00', 'closes_at' => '17:00']);
    foreach ([['working', '09:00', '17:00'], ['break', '12:00', '13:00']] as [$kind,$from,$until]) {
        StaffAvailabilityRule::query()->create(['business_id' => $p['business']->id, 'location_id' => $p['location']->id, 'staff_profile_id' => $p['staff']->id, 'kind' => $kind, 'day_of_week' => $date->dayOfWeekIso, 'starts_at' => $from, 'ends_at' => $until]);
    }
    analyticsVisit($p, 'one', '2026-10-01 03:30:00', minutes: 120);
    analyticsVisit($p, 'overlap', '2026-10-01 04:30:00', minutes: 90);
    analyticsVisit($p, 'break-booking', '2026-10-01 06:30:00', minutes: 60);
    $r = analyticsReport($p, 'utilisation');
    expect($r['totals']['available_minutes'])->toBe(420)->and($r['totals']['booked_minutes'])->toBe(150)->and($r['rows'][0]['recorded_minutes'])->toBe(210)->and($r['rows'][0]['outside_windows_minutes'])->toBe(60)->and($r['totals']['utilisation_percent'])->toBe(35.7);
    StaffAvailabilityRule::query()->create(['business_id' => $p['business']->id, 'location_id' => $p['location']->id, 'staff_profile_id' => $p['staff']->id, 'kind' => 'leave', 'starts_on' => '2026-10-01', 'ends_on' => '2026-10-01']);
    expect(analyticsReport($p, 'utilisation')['totals']['available_minutes'])->toBe(0)->and(analyticsReport($p, 'utilisation')['totals']['utilisation_percent'])->toBeNull();
});
it('uses measured walk-in waits and excludes missing measurements', function () {
    $p = analyticsTenant();
    foreach ([0, 20, null] as $i => $wait) {
        WalkInEntry::query()->create(['business_id' => $p['business']->id, 'location_id' => $p['location']->id, 'service_id' => $p['service']->id, 'client_name' => 'Synthetic arrival', 'client_mobile' => '+12025550100', 'status' => $wait === null ? 'waiting' : 'in_service', 'arrived_at' => '2026-10-01 05:00:00', 'service_started_at' => $wait === null ? null : '2026-10-01 05:20:00', 'actual_wait_minutes' => $wait, 'queue_position' => $i + 1]);
    }
    $r = analyticsReport($p, 'walk_ins');
    expect($r['totals']['row_count'])->toBe(3)->and($r['totals']['average_wait_minutes'])->toEqual(10)->and($r['totals']['longest_wait_minutes'])->toBe(20)->and($r['totals']['started_count'])->toBe(2);
});
it('rejects unauthorized branches foreign filters mixed zones and malformed ranges', function () {
    $p = analyticsTenant(StarterRole::Manager);
    $other = Location::factory()->create(['business_id' => $p['business']->id]);
    expect(fn () => analyticsReport($p, filters: ['location_ids' => [$other->id]]))->toThrow(AccessDeniedHttpException::class);
    $owner = analyticsTenant();
    Location::factory()->create(['business_id' => $owner['business']->id, 'time_zone' => 'America/New_York']);
    expect(fn () => analyticsReport($owner))->toThrow(DomainException::class);
    $this->actingAs($p['user'])->getJson(route('business.reports.index', ['business' => $p['business'], 'start_date' => '2026-02-30']))->assertUnprocessable();
});
it('makes exports inert readable complete and private after permission narrowing', function () {
    Storage::fake('private');
    $p = analyticsTenant();
    for ($i = 0; $i < 35; $i++) {
        analyticsSale($p, 'export-'.$i, options: ['description' => '=HYPERLINK("unsafe")']);
    }
    $export = app(TenantContext::class)->run($p['business'], $p['membership'], fn () => app(ReportExportService::class)->queue($p['business'], $p['membership'], 'service_revenue', ['start_date' => '2026-10-01', 'end_date' => '2026-10-01']));
    $export->refresh();
    $csv = Storage::disk('private')->get($export->storage_path);
    expect($export->row_count)->toBe(35)->and($csv)->toContain("'=HYPERLINK")->not->toContain('Source id');
    $this->actingAs($p['user'])->get(route('business.report-exports.download', [$p['business'], $export]))->assertOk()->assertHeader('Content-Disposition', 'attachment; filename=service-revenue-report-2026-10-01-to-2026-10-01.csv');
    app(TenantContext::class)->run($p['business'], $p['membership'], function () use ($p) {
        $p['membership']->syncRoles([]);
        $p['membership']->syncPermissions([PermissionName::CalendarViewOwn->value, PermissionName::ExportCreate->value]);
    });
    $this->get(route('business.report-exports.download', [$p['business'], $export]))->assertForbidden();
});

it('classifies a returning client across currencies without adding unlike money', function () {
    $p = analyticsTenant();
    $client = Client::query()->create(['business_id' => $p['business']->id, 'name' => 'Synthetic multi-currency client', 'normalized_name' => 'multi', 'status' => 'active']);
    analyticsSale($p, 'usd-prior', '2026-09-01 05:00:00', ['currency' => 'USD', 'client_id' => $client->id]);
    analyticsSale($p, 'inr-current', options: ['client_id' => $client->id]);
    $result = analyticsReport($p, 'client_classification');
    expect($result['totals']['new_count'])->toBe(0)->and($result['totals']['returning_count'])->toBe(1)->and($result['totals']['revenue_minor'])->toBe(9950);
});
it('rechecks own-compensation when downloading an existing all-team file', function () {
    Storage::fake('private');
    $p = analyticsTenant();
    $export = app(TenantContext::class)->run($p['business'], $p['membership'], fn () => app(ReportExportService::class)->queue($p['business'], $p['membership'], 'commission', ['start_date' => '2026-10-01', 'end_date' => '2026-10-01']));
    app(TenantContext::class)->run($p['business'], $p['membership'], function () use ($p) {
        $p['membership']->syncRoles([]);
        $p['membership']->syncPermissions([PermissionName::CommissionsViewOwn->value, PermissionName::ExportCreate->value]);
    });
    expect(analyticsReport($p, 'commission')['filters']['staff_ids'])->toBe([$p['staff']->id]);
    $this->actingAs($p['user'])->get(route('business.report-exports.download', [$p['business'], $export->fresh()]))->assertForbidden();
});
it('keeps 5000-row totals exact with a bounded detail payload and constant query count', function () {
    $p = analyticsTenant();
    $sale = analyticsSale($p, 'large-template');
    $record = $sale->getRawOriginal();
    unset($record['id']);
    for ($batch = 0; $batch < 10; $batch++) {
        $records = [];
        for ($i = 0; $i < 500; $i++) {
            $records[] = [...$record, 'public_id' => (string) Str::ulid()];
        }
        DB::table('sales')->insert($records);
    }
    DB::enableQueryLog();
    $started = microtime(true);
    $result = analyticsReport($p, filters: ['compare' => 'none']);
    $queries = DB::getQueryLog();
    DB::disableQueryLog();
    expect($result['totals']['row_count'])->toBe(5001)->and($result['totals']['collected_minor'])->toBe(5001 * 9950)->and($result['rows'])->toHaveCount(30)->and(count($queries))->toBeLessThan(30)->and(strlen(json_encode($result)))->toBeLessThan(50000);
    if ($evidencePath = getenv('REPORT_SCALE_EVIDENCE_PATH')) {
        file_put_contents($evidencePath, json_encode(['fixture_rows' => 5001, 'details' => count($result['rows']), 'queries' => count($queries), 'elapsed_ms' => round((microtime(true) - $started) * 1000, 1), 'response_bytes' => strlen(json_encode($result)), 'driver' => DB::getDriverName()], JSON_PRETTY_PRINT));
    }
});
it('runs every permitted empty report without fabricated rates or source errors', function () {
    $p = analyticsTenant();
    foreach (app(TenantContext::class)->run($p['business'], $p['membership'], fn () => app(ReportService::class)->allowedReportKeys($p['membership'])) as $key) {
        $result = analyticsReport($p, $key);
        expect($result['totals']['row_count'])->toBe($key === 'utilisation' ? 1 : 0)->and($result['chart_error'])->toBeNull();
    }
});
it('denies an existing protected-reason export after notes permission is removed', function () {
    Storage::fake('private');
    $p = analyticsTenant();
    analyticsVisit($p, 'cancelled-note', '2026-10-01 06:00:00', 'cancelled_by_shop');
    $export = app(TenantContext::class)->run($p['business'], $p['membership'], fn () => app(ReportExportService::class)->queue($p['business'], $p['membership'], 'appointments', ['start_date' => '2026-10-01', 'end_date' => '2026-10-01']));
    app(TenantContext::class)->run($p['business'], $p['membership'], function () use ($p) {
        $p['membership']->syncRoles([]);
        $p['membership']->syncPermissions([PermissionName::CalendarViewAll->value, PermissionName::RevenueView->value, PermissionName::ExportCreate->value]);
    });
    $this->actingAs($p['user'])->get(route('business.report-exports.download', [$p['business'], $export->fresh()]))->assertForbidden();
});

it('aligns comparison hours across a daylight-saving fallback and distinguishes a missing hour from zero', function () {
    $p = analyticsTenant(zone: 'America/New_York');
    analyticsSale($p, 'prior-two', '2026-10-31 06:30:00');
    $result = analyticsReport($p, filters: ['start_date' => '2026-11-01', 'end_date' => '2026-11-01']);
    $points = collect($result['trend']['points'])->keyBy('label');
    expect($points['02:00 EST']['previous'])->toBe(9950)->and($points['01:00 EST']['previous'])->toBeNull()->and($points['03:00 EST']['previous'])->toBe(0);
});
