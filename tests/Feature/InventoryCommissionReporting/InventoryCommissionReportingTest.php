<?php

use App\Domain\BusinessConfiguration\Models\Service;
use App\Domain\Commissions\Models\CommissionEntry;
use App\Domain\Commissions\Models\TipEntry;
use App\Domain\Commissions\Services\CommissionLedger;
use App\Domain\Inventory\Models\InventoryMovement;
use App\Domain\Inventory\Models\InventoryProduct;
use App\Domain\Inventory\Services\InventoryLedger;
use App\Domain\Inventory\Services\ProductCatalogService;
use App\Domain\MoneyCommerce\Models\CommerceSetting;
use App\Domain\MoneyCommerce\Models\PaymentTransaction;
use App\Domain\MoneyCommerce\Services\CheckoutService;
use App\Domain\PlatformAccess\Enums\StarterRole;
use App\Domain\PlatformAccess\Models\Business;
use App\Domain\PlatformAccess\Models\Location;
use App\Domain\PlatformAccess\Models\Membership;
use App\Domain\PlatformAccess\Models\StaffProfile;
use App\Domain\PlatformAccess\Services\MembershipAccessManager;
use App\Domain\Reporting\Models\InstrumentationEvent;
use App\Domain\Reporting\Models\ReportExport;
use App\Domain\Reporting\Services\InstrumentationService;
use App\Domain\Reporting\Services\MetricCatalog;
use App\Domain\Reporting\Services\ReportExportService;
use App\Domain\Reporting\Services\ReportService;
use App\Domain\Reporting\Services\TodayDashboardService;
use App\Domain\SchedulingOperations\Models\Appointment;
use App\Domain\SchedulingOperations\Models\AppointmentServiceLine;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

uses(RefreshDatabase::class);

/** @return array<string,mixed> */
function managementTenant(StarterRole $role = StarterRole::Owner, string $timeZone = 'Asia/Kolkata'): array
{
    $business = Business::factory()->create(['currency_code' => 'INR', 'time_zone' => $timeZone]);
    $location = Location::factory()->create(['business_id' => $business->id, 'time_zone' => $timeZone]);
    $user = User::factory()->create(['email_verified_at' => now()]);
    $membership = Membership::factory()->create(['business_id' => $business->id, 'user_id' => $user->id]);
    app(MembershipAccessManager::class)->assignStarterRole($membership, $role, $user, 'Management report fixture.');
    $membership->locations()->attach($location->id, ['business_id' => $business->id]);
    $staff = StaffProfile::factory()->create(['business_id' => $business->id, 'membership_id' => $membership->id, 'user_id' => $user->id]);
    $staff->locations()->attach($location->id, ['business_id' => $business->id]);
    $service = Service::query()->create(['business_id' => $business->id, 'kind' => 'service', 'name' => 'Precision cut', 'price_type' => 'fixed', 'price_minor' => 3000, 'currency_code' => 'INR', 'duration_minutes' => 30, 'minimum_notice_minutes' => 0, 'maximum_advance_days' => 30, 'client_eligibility' => 'all', 'is_active' => true]);
    CommerceSetting::query()->create(['business_id' => $business->id, 'currency_code' => 'INR', 'tax_inclusive' => true, 'default_tax_rate_bps' => 0, 'discount_manager_limit_bps' => 10000]);

    return compact('business', 'location', 'user', 'membership', 'staff', 'service');
}

function managementAppointment(array $path, string $key, CarbonImmutable $at): Appointment
{
    $appointment = Appointment::query()->create(['business_id' => $path['business']->id, 'location_id' => $path['location']->id, 'idempotency_key' => $key, 'request_hash' => hash('sha256', $key), 'status' => 'completed', 'source' => 'reception', 'starts_at_utc' => $at, 'ends_at_utc' => $at->addMinutes(30), 'time_zone' => $path['location']->time_zone, 'local_starts_at' => $at->setTimezone($path['location']->time_zone)->format(DATE_ATOM), 'local_ends_at' => $at->addMinutes(30)->setTimezone($path['location']->time_zone)->format(DATE_ATOM), 'price_minor' => 3000, 'currency_code' => 'INR', 'completed_at' => $at]);
    AppointmentServiceLine::query()->create(['business_id' => $path['business']->id, 'appointment_id' => $appointment->id, 'service_id' => $path['service']->id, 'primary_staff_profile_id' => $path['staff']->id, 'sequence' => 1, 'name' => $path['service']->name, 'price_minor' => 3000, 'currency_code' => 'INR', 'bookable_minutes' => 30, 'configuration_snapshot' => ['taxRateBps' => 0]]);

    return $appointment;
}

it('deducts product stock exactly once and preserves explicit refund and void dispositions', function () {
    CarbonImmutable::setTestNow('2026-08-15 06:00:00 UTC');
    $path = managementTenant();
    $product = InventoryProduct::query()->create(['business_id' => $path['business']->id, 'name' => 'Texture clay', 'sku' => 'CLAY-1', 'sale_price_minor' => 1000, 'cost_minor' => 400, 'tax_rate_bps' => 0, 'currency_code' => 'INR', 'status' => 'active', 'current_stock' => 10, 'low_stock_threshold' => 2]);
    $appointment = managementAppointment($path, 'stock-once', CarbonImmutable::now());
    $checkout = app(CheckoutService::class);
    $sale = $checkout->openForAppointment($appointment, [['kind' => 'product', 'source_id' => $product->id, 'staff_profile_id' => $path['staff']->id, 'quantity' => 2, 'unit_price_minor' => 1000, 'discount_minor' => 0]]);
    $payment = $checkout->recordTender($sale, 'cash', 5000, 'stock-final-tender');
    $replay = $checkout->recordTender($sale->fresh(), 'cash', 5000, 'stock-final-tender');
    $productLine = $sale->fresh('lines')->lines->firstWhere('kind', 'product');

    expect($replay->id)->toBe($payment->id)
        ->and($product->fresh()->current_stock)->toBe(8)
        ->and(InventoryMovement::query()->where('type', 'sale')->where('sale_line_id', $productLine->id)->count())->toBe(1);

    $checkout->refund($sale->fresh(), $payment, 1000, 'stock-refund-restock', 'Unopened return.', [['sale_line_id' => $productLine->id, 'amount_minor' => 1000, 'quantity' => 1, 'disposition' => 'restock']]);
    $checkout->refund($sale->fresh(), $payment, 1000, 'stock-refund-keep', 'Opened product cannot be returned to stock.', [['sale_line_id' => $productLine->id, 'amount_minor' => 1000, 'quantity' => 1, 'disposition' => 'customer_keeps']], 'void');

    expect($product->fresh()->current_stock)->toBe(9)
        ->and(InventoryMovement::query()->where('type', 'refund')->pluck('disposition')->all())->toEqualCanonicalizing(['restock', 'customer_keeps'])
        ->and(DB::table('sale_line_refunds')->where('sale_line_id', $productLine->id)->count())->toBe(2)
        ->and(fn () => $checkout->refund($sale->fresh(), $payment, 100, 'missing-disposition', 'Missing disposition.'))->toThrow(DomainException::class, 'explicit stock disposition');
});

it('freezes effective commission rules, calculates after discount, and offsets refunds and manager changes', function () {
    CarbonImmutable::setTestNow('2026-08-15 06:00:00 UTC');
    $path = managementTenant();
    $product = InventoryProduct::query()->create(['business_id' => $path['business']->id, 'name' => 'Shine serum', 'sku' => 'SERUM-1', 'sale_price_minor' => 1000, 'cost_minor' => 300, 'tax_rate_bps' => 0, 'currency_code' => 'INR', 'status' => 'active', 'current_stock' => 5, 'low_stock_threshold' => 1]);
    $ledger = app(CommissionLedger::class);
    $ledger->createRule(['business_id' => $path['business']->id, 'staff_profile_id' => $path['staff']->id, 'service_id' => $path['service']->id, 'kind' => 'fixed_service', 'amount_minor' => 500, 'currency_code' => 'INR', 'effective_from' => now()->subDay(), 'created_by_membership_id' => $path['membership']->id, 'reason' => 'Initial fixed service rule.']);
    $ledger->createRule(['business_id' => $path['business']->id, 'staff_profile_id' => $path['staff']->id, 'kind' => 'product_percentage', 'rate_bps' => 1000, 'currency_code' => 'INR', 'effective_from' => now()->subDay(), 'created_by_membership_id' => $path['membership']->id, 'reason' => 'Initial product rule.']);
    $appointment = managementAppointment($path, 'commission-sale', CarbonImmutable::now());
    $sale = app(CheckoutService::class)->openForAppointment($appointment, [['kind' => 'product', 'source_id' => $product->id, 'staff_profile_id' => $path['staff']->id, 'quantity' => 1, 'unit_price_minor' => 1000, 'discount_minor' => 200]], [['staff_profile_id' => $path['staff']->id, 'amount_minor' => 500]], true);
    $payment = app(CheckoutService::class)->recordTender($sale, 'card', 4300, 'commission-payment');
    $productLine = $sale->fresh('lines')->lines->firstWhere('kind', 'product');

    expect((int) CommissionEntry::query()->where('type', 'earned')->sum('amount_minor'))->toBe(580)
        ->and((int) CommissionEntry::query()->where('sale_line_id', $productLine->id)->value('base_minor'))->toBe(800)
        ->and((int) TipEntry::query()->where('type', 'earned')->sum('amount_minor'))->toBe(500);

    app(CheckoutService::class)->refund($sale->fresh(), $payment, 400, 'commission-refund', 'Half of discounted product refunded.', [['sale_line_id' => $productLine->id, 'amount_minor' => 400, 'quantity' => 0, 'disposition' => 'customer_keeps']]);
    $ledger->adjustCommission($path['staff'], -25, 'INR', $path['membership'], 'Payroll correction.', 'commission-manager-adjustment');
    $statement = $ledger->statement($path['business']->id, $path['staff']->id, now()->subDay(), now()->addDay());

    expect((int) CommissionEntry::query()->where('type', 'refund_reversal')->sum('amount_minor'))->toBe(-40)
        ->and((int) TipEntry::query()->where('type', 'refund_reversal')->sum('amount_minor'))->toBe(-47)
        ->and($statement['commission_minor'])->toBe(515)
        ->and($statement['tips_minor'])->toBe(453)
        ->and($statement['total_minor'])->toBe(968);

    CarbonImmutable::setTestNow('2026-08-16 06:00:00 UTC');
    $ledger->createRule(['business_id' => $path['business']->id, 'staff_profile_id' => $path['staff']->id, 'kind' => 'product_percentage', 'rate_bps' => 2000, 'currency_code' => 'INR', 'effective_from' => now(), 'created_by_membership_id' => $path['membership']->id, 'reason' => 'New product rule.']);
    $second = managementAppointment($path, 'commission-sale-two', CarbonImmutable::now());
    $secondSale = app(CheckoutService::class)->openForAppointment($second, [['kind' => 'product', 'source_id' => $product->id, 'staff_profile_id' => $path['staff']->id, 'quantity' => 1, 'unit_price_minor' => 1000, 'discount_minor' => 0]]);
    app(CheckoutService::class)->recordTender($secondSale, 'cash', 4000, 'commission-payment-two');
    expect((int) CommissionEntry::query()->where('sale_line_id', $secondSale->fresh('lines')->lines->firstWhere('kind', 'product')->id)->where('type', 'earned')->value('amount_minor'))->toBe(200)
        ->and((int) CommissionEntry::query()->where('sale_line_id', $productLine->id)->where('type', 'earned')->value('amount_minor'))->toBe(80);
});

it('reconciles dashboard, reports, sale lines, payments, CSV, and printable totals with filter parity', function () {
    Storage::fake('private');
    CarbonImmutable::setTestNow('2026-08-15 06:00:00 UTC');
    $path = managementTenant();
    $appointment = managementAppointment($path, 'reconciliation-sale', CarbonImmutable::now());
    $sale = app(CheckoutService::class)->openForAppointment($appointment);
    $payment = app(CheckoutService::class)->recordTender($sale, 'cash', 3000, 'reconciliation-payment');
    $context = app(TenantContext::class);
    $salesReport = $context->run($path['business'], $path['membership'], fn () => app(ReportService::class)->run($path['business'], $path['membership'], 'sales', ['start_date' => '2026-08-15', 'end_date' => '2026-08-15', 'location_ids' => [$path['location']->id]]));
    $paymentReport = $context->run($path['business'], $path['membership'], fn () => app(ReportService::class)->run($path['business'], $path['membership'], 'payment_method', ['start_date' => '2026-08-15', 'end_date' => '2026-08-15', 'location_ids' => [$path['location']->id]]));
    $serviceReport = $context->run($path['business'], $path['membership'], fn () => app(ReportService::class)->run($path['business'], $path['membership'], 'service_revenue', ['start_date' => '2026-08-15', 'end_date' => '2026-08-15', 'location_ids' => [$path['location']->id], 'service_ids' => [$path['service']->id]]));
    $dashboard = $context->run($path['business'], $path['membership'], fn () => app(TodayDashboardService::class)->forLocation($path['business'], $path['membership'], $path['location'], CarbonImmutable::parse('2026-08-15', 'Asia/Kolkata')));

    expect($salesReport['totals']['collected_minor'])->toBe(3000)
        ->and($paymentReport['totals']['collected_minor'])->toBe(3000)
        ->and($dashboard['cards']['collected_revenue_minor']['value'])->toBe(3000)
        ->and($salesReport['rows'][0]['source_id'])->toBe($sale->id)
        ->and($paymentReport['rows'][0]['transaction_count'])->toBe(1)
        ->and($serviceReport['totals']['row_count'])->toBe(1)
        ->and($serviceReport['rows'][0]['service_id'])->toBe($path['service']->id)
        ->and(PaymentTransaction::query()->find($payment->id)->sale_id)->toBe($sale->id);

    $export = $context->run($path['business'], $path['membership'], fn () => app(ReportExportService::class)->queue($path['business'], $path['membership'], 'sales', ['start_date' => '2026-08-15', 'end_date' => '2026-08-15', 'location_ids' => [$path['location']->id]]));
    $export->refresh();
    expect($export->status)->toBe('completed')->and($export->totals['collected_minor'])->toBe(3000)->and($export->row_count)->toBe(1);
    Storage::disk('private')->assertExists($export->storage_path);
    expect(Storage::disk('private')->get($export->storage_path))->toContain('total:collected_minor,3000');

    $this->actingAs($path['user'])->get(route('business.reports.print', ['business' => $path['business'], 'report' => 'sales', 'start_date' => '2026-08-15', 'end_date' => '2026-08-15', 'location_ids' => [$path['location']->id]]))->assertOk()->assertSee('Reconciled totals')->assertSee('3000');
});

it('uses governing location-day boundaries instead of server dates', function () {
    $path = managementTenant(timeZone: 'Asia/Kolkata');
    CarbonImmutable::setTestNow('2026-08-14 18:20:00 UTC');
    $first = managementAppointment($path, 'before-midnight', CarbonImmutable::now());
    app(CheckoutService::class)->recordTender(app(CheckoutService::class)->openForAppointment($first), 'cash', 3000, 'before-midnight-payment');
    CarbonImmutable::setTestNow('2026-08-14 18:40:00 UTC');
    $second = managementAppointment($path, 'after-midnight', CarbonImmutable::now());
    app(CheckoutService::class)->recordTender(app(CheckoutService::class)->openForAppointment($second), 'cash', 3000, 'after-midnight-payment');

    $context = app(TenantContext::class);
    $august14 = $context->run($path['business'], $path['membership'], fn () => app(ReportService::class)->run($path['business'], $path['membership'], 'sales', ['start_date' => '2026-08-14', 'end_date' => '2026-08-14', 'location_ids' => [$path['location']->id]]));
    $august15 = $context->run($path['business'], $path['membership'], fn () => app(ReportService::class)->run($path['business'], $path['membership'], 'sales', ['start_date' => '2026-08-15', 'end_date' => '2026-08-15', 'location_ids' => [$path['location']->id]]));
    expect($august14['totals']['row_count'])->toBe(1)->and($august15['totals']['row_count'])->toBe(1)->and($august15['time_zone'])->toBe('Asia/Kolkata');
});

it('enforces role, staff, location, and cross-tenant export scope', function () {
    $accountant = managementTenant(StarterRole::Accountant);
    $barber = managementTenant(StarterRole::BarberStylist);
    $manager = managementTenant(StarterRole::Manager);
    $other = managementTenant();
    $context = app(TenantContext::class);
    expect(fn () => $context->run($accountant['business'], $accountant['membership'], fn () => app(ReportService::class)->run($accountant['business'], $accountant['membership'], 'appointments')))->toThrow(AccessDeniedHttpException::class)
        ->and(fn () => $context->run($barber['business'], $barber['membership'], fn () => app(ReportService::class)->run($barber['business'], $barber['membership'], 'commission', ['staff_ids' => [$other['staff']->id]])))->toThrow(AccessDeniedHttpException::class);

    $unassignedLocation = Location::factory()->create(['business_id' => $manager['business']->id]);
    $managerProduct = InventoryProduct::query()->create(['business_id' => $manager['business']->id, 'name' => 'Scoped stock', 'sku' => 'SCOPED-1', 'sale_price_minor' => 500, 'cost_minor' => 200, 'tax_rate_bps' => 0, 'currency_code' => 'INR', 'status' => 'active', 'current_stock' => 0, 'low_stock_threshold' => 1]);
    $this->actingAs($manager['user'])->post(route('business.inventory.receipts.store', [$manager['business'], $managerProduct]), ['location_id' => $unassignedLocation->public_id, 'quantity' => 2, 'reason' => 'Must remain scoped.', 'idempotency_key' => 'unassigned-receipt'])->assertForbidden();

    $foreignExport = ReportExport::query()->create(['business_id' => $other['business']->id, 'requested_by_membership_id' => $other['membership']->id, 'report_key' => 'sales', 'format' => 'csv', 'filters' => [], 'scope_snapshot' => [], 'status' => 'queued']);
    $this->actingAs($accountant['user'])->get(route('business.report-exports.show', [$accountant['business'], $foreignExport]))->assertNotFound();
});

it('publishes the complete metric and event catalogs and rejects personal instrumentation', function () {
    $path = managementTenant();
    expect(MetricCatalog::reportKeys())->toContain('appointments', 'sales', 'service_revenue', 'staff_revenue', 'payment_method', 'location', 'discount', 'refund', 'tip', 'commission', 'client_classification', 'cancellation_no_show', 'utilisation', 'popular_service', 'visit_frequency', 'product_sales', 'stock', 'cash_close')
        ->and(array_keys(MetricCatalog::definitions()))->toContain('gross_revenue', 'net_revenue', 'collected_revenue', 'expected_revenue', 'taxes', 'discounts', 'refunds', 'deposits', 'tips', 'utilisation', 'client_classification')
        ->and(collect(MetricCatalog::instrumentation())->pluck('category')->unique()->values()->all())->toEqualCanonicalizing(['acquisition', 'activation', 'booking', 'reliability', 'revenue_protection', 'operations', 'retention', 'usage', 'support']);
    $events = app(InstrumentationService::class);
    $events->record($path['business'], 'booking.completed', 'booking-event-one', ['acquisition_channel' => 'organic', 'geography' => 'IN']);
    $events->record($path['business'], 'booking.completed', 'booking-event-one', ['acquisition_channel' => 'organic']);
    $events->record(Business::factory()->create(), 'booking.completed', 'booking-event-one', ['acquisition_channel' => 'organic']);
    expect(InstrumentationEvent::query()->count())->toBe(2)
        ->and(fn () => $events->record($path['business'], 'booking.completed', 'unsafe-event', ['source' => 'client@example.test']))->toThrow(DomainException::class, 'direct contact data');

    $reports = app(TenantContext::class)->run($path['business'], $path['membership'], fn () => collect(MetricCatalog::reportKeys())->mapWithKeys(fn ($key) => [$key => app(ReportService::class)->run($path['business'], $path['membership'], $key)]));
    expect($reports)->toHaveCount(count(MetricCatalog::reportKeys()))
        ->and($reports->every(fn ($report) => isset($report['fresh_at'], $report['time_zone'], $report['source'], $report['totals'])))->toBeTrue();
});

it('imports and exports product CSV and keeps receipts and manual changes in the movement ledger', function () {
    $path = managementTenant();
    $csv = "name,category,sku,barcode,sale_price_minor,cost_minor,tax_rate_bps,currency_code,status,current_stock,low_stock_threshold\nTexture Dust,Styling,DUST-1,890100000001,1200,500,1800,INR,active,6,2\n";
    $catalog = app(ProductCatalogService::class);
    $result = $catalog->importCsv($path['business'], $csv, $path['location']);
    $product = InventoryProduct::query()->where('sku', 'DUST-1')->firstOrFail();

    expect($result)->toMatchArray(['created' => 1, 'updated' => 0, 'errors' => []])
        ->and($product->current_stock)->toBe(6)
        ->and(InventoryMovement::query()->where('inventory_product_id', $product->id)->where('type', 'import')->count())->toBe(1)
        ->and($catalog->exportCsv($path['business'], [$path['location']->id]))->toContain('"Texture Dust",Styling,DUST-1');

    $ledger = app(InventoryLedger::class);
    $ledger->receive($product, $path['location'], 4, $path['membership'], 'Supplier delivery 42.', 'receipt-42');
    $ledger->adjust($product, $path['location'], -1, $path['membership'], 'One damaged unit.', 'damage-1');
    $ledger->receive($product, $path['location'], 4, $path['membership'], 'Supplier delivery 42.', 'receipt-42');
    expect($product->fresh()->current_stock)->toBe(9)
        ->and(InventoryMovement::query()->where('inventory_product_id', $product->id)->count())->toBe(3)
        ->and(InventoryMovement::query()->where('idempotency_key', 'damage-1')->value('reason'))->toBe('One damaged unit.');
});

it('keeps realistic report volume bounded and indexed', function () {
    CarbonImmutable::setTestNow('2026-08-15 06:00:00 UTC');
    $path = managementTenant();
    $now = now();
    foreach (array_chunk(range(1, 2000), 250) as $chunk) {
        DB::table('sales')->insert(array_map(fn ($number) => ['business_id' => $path['business']->id, 'location_id' => $path['location']->id, 'public_id' => (string) Str::ulid(), 'status' => 'completed', 'currency_code' => 'INR', 'subtotal_minor' => 1000, 'discount_minor' => 100, 'tax_minor' => 0, 'tip_minor' => 0, 'total_minor' => 900, 'deposit_applied_minor' => 0, 'paid_minor' => 900, 'refunded_minor' => 0, 'balance_minor' => 0, 'calculation_snapshot' => '{}', 'completed_at' => $now, 'created_at' => $now, 'updated_at' => $now], $chunk));
    }
    $queries = 0;
    DB::listen(function () use (&$queries): void {
        $queries++;
    });
    $started = hrtime(true);
    $result = app(TenantContext::class)->run($path['business'], $path['membership'], fn () => app(ReportService::class)->run($path['business'], $path['membership'], 'sales', ['start_date' => '2026-08-15', 'end_date' => '2026-08-15', 'location_ids' => [$path['location']->id]]));
    $milliseconds = (hrtime(true) - $started) / 1_000_000;
    if (getenv('REPORT_PERF_EVIDENCE') === '1') {
        fwrite(STDOUT, sprintf("\nREPORT_PERF rows=2000 queries=%d milliseconds=%.2f collected_minor=%d\n", $queries, $milliseconds, $result['totals']['collected_minor']));
    }

    expect($result['totals']['row_count'])->toBe(2000)
        ->and($result['totals']['collected_minor'])->toBe(1_800_000)
        ->and($queries)->toBeLessThanOrEqual(15)
        ->and($milliseconds)->toBeLessThan(2500);
});
