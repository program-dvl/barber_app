<?php

use App\Domain\Marketplace\LocalGrowth\Services\GrowthOutcomeReport;
use App\Domain\Marketplace\LocalGrowth\Services\PublicSupplyProjection;
use App\Domain\Marketplace\LocalGrowth\Services\SearchPerformanceImport;
use App\Domain\MoneyCommerce\Models\PaymentTransaction;
use App\Domain\PublicBooking\Models\PublicBookingFlow;
use App\Domain\PublicBooking\Services\AppointmentSelfService;
use App\Domain\PublicBooking\Services\SecureAppointmentLinkService;
use App\Domain\SchedulingOperations\Contracts\AppointmentLifecycleCommand;
use App\Domain\SchedulingOperations\Models\Appointment;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

require_once __DIR__.'/../../Support/MarketplaceGrowthFixture.php';
uses(RefreshDatabase::class);
beforeEach(function () {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-08T10:23:45.784321+05:30'));
    Queue::fake();
    Mail::fake();
    $this->withoutVite();
    config(['inertia.ssr.enabled' => false]);
});
afterEach(fn () => CarbonImmutable::setTestNow());

function growthReport(array $f, string $currency = 'CAD'): array
{
    return app(TenantContext::class)->run($f['business'], $f['membership']->fresh(), fn () => app(GrowthOutcomeReport::class)->for($f['business'], $f['membership']->fresh(), ['start_date' => '2026-11-01', 'end_date' => '2026-11-30', 'location_ids' => [$f['location']->id], 'currency_code' => $currency]));
}
function growthObserve($test, string $source = 'marketplace_discovery', string $page = '/marketplace', string $owner = 'unknown')
{
    return $test->postJson('/marketplace/acquisition/observe', ['consent' => true, 'version' => 'mpg-consent-v1', 'page' => $page, 'source' => $source, 'owner_source' => $owner]);
}
function growthConfirm($test, array $f, string $suffix = '', bool $marketplace = true): Appointment
{
    $base = '/marketplace/salons/'.$f['business']->booking_slug.'/booking/';
    $endpoint = fn ($action) => $marketplace ? $base.$action : route('public.booking.'.$action, ['slug' => $f['business']->booking_slug]);
    $headers = ['X-Marketplace-Analytics-Consent' => '1'];
    $started = $test->postJson($endpoint('start'), [], $headers)->assertOk()->json();
    $creds = ['flow' => $started['flow'], 'secret' => $started['secret']];
    $selection = ['location' => $f['location']->public_id, 'services' => [$f['service']->public_id], 'staff' => null, 'client_eligibility' => 'auto'];
    $slots = $test->postJson($endpoint('search'), [...$creds, ...$selection, 'from_date' => '2026-11-10', 'until_date' => '2026-11-10'])->assertOk()->json('slots');
    $test->postJson($endpoint('hold'), [...$creds, ...$selection, 'starts_at' => $slots[0]['starts_at_utc'], 'idempotency_key' => (string) Str::uuid()])->assertOk();
    $details = ['client_name' => 'Test Client '.$suffix, 'client_email' => 'client'.$suffix.'@example.test', 'client_mobile' => '+1416555'.str_pad((string) (abs(crc32($suffix)) % 10000), 4, '0', STR_PAD_LEFT)];
    $test->postJson($endpoint('details'), [...$creds, ...$details])->assertOk();
    $payload = [...$creds, ...$details, 'policy_accepted' => true, 'communication_preferences' => [], 'idempotency_key' => (string) Str::uuid()];
    $test->postJson($endpoint('confirm'), $payload, $headers)->assertOk();
    $test->postJson($endpoint('confirm'), $payload, $headers)->assertOk();
    $test->postJson($endpoint('resume'), $creds)->assertOk()->assertJsonPath('status', 'confirmed');

    return PublicBookingFlow::query()->where('public_id', $started['flow'])->firstOrFail()->appointment;
}

it('preserves original discovery across direct return confirmation replay resume and rescheduling', function () {
    $f = growthSupply();
    growthApproveProfile($f);
    growthObserve($this)->assertNoContent();
    growthObserve($this, 'direct', '/marketplace/salons/'.$f['business']->booking_slug)->assertNoContent();
    $appointment = growthConfirm($this, $f);
    $row = DB::table('marketplace_booking_attributions')->first();
    expect($row->first_source)->toBe('marketplace_discovery')->and($row->last_source)->toBe('marketplace_discovery')->and($row->entry)->toBe('marketplace_profile');
    expect(DB::table('marketplace_booking_attributions')->count())->toBe(1);
    expect(growthReport($f)['rows'][0]['confirmations'])->toBe(1);
    $links = app(SecureAppointmentLinkService::class);
    $token = $links->issue($appointment, 'reschedule')['token'];
    $replacement = app(AppointmentSelfService::class)->reschedule($links->resolve($token, 'reschedule'), CarbonImmutable::parse('2026-11-11T10:00:00-05:00')->utc(), 'growth-replacement');
    expect($replacement->marketplace_attribution_root_id)->toBe($appointment->id);
    expect(growthReport($f)['rows'][0]['confirmations'])->toBe(1);
    $lifecycle = app(AppointmentLifecycleCommand::class);
    foreach (['arrived', 'in_service', 'completed'] as $status) {
        $replacement = $lifecycle->transition($replacement, $status, 'growth-'.$status, $replacement->version, 'test');
    }
    $r = growthReport($f)['rows'][0];
    expect($r['completed_visits'])->toBe(1)->and($r['new_to_business_clients'])->toBe(1);
    $payment = PaymentTransaction::query()->create(['business_id' => $f['business']->id, 'appointment_id' => $replacement->id, 'kind' => 'payment', 'status' => 'succeeded', 'method' => 'cash', 'amount_minor' => 3500, 'currency_code' => 'CAD', 'idempotency_key' => 'collection-1', 'occurred_at' => now()->addMicrosecond()]);
    PaymentTransaction::query()->create(['business_id' => $f['business']->id, 'appointment_id' => $replacement->id, 'parent_transaction_id' => $payment->id, 'kind' => 'refund', 'status' => 'succeeded', 'method' => 'cash', 'amount_minor' => 500, 'currency_code' => 'CAD', 'idempotency_key' => 'refund-1', 'occurred_at' => now()->subSecond()]); // deliberately out of order
    $r = growthReport($f)['rows'][0];
    expect($r['booked_minor'])->toBe(3500)->and($r['collected_minor'])->toBe(3500)->and($r['refunded_minor'])->toBe(500)->and($r['net_recorded_minor'])->toBe(3000);
    expect(growthReport($f)['rows'][0])->toBe($r); // read replay never mutates/double-counts
    expect(growthReport($f, 'USD')['rows'])->toBe([]);
});

it('keeps owner-shared labels separate from booking entry and requires consent for acquisition', function () {
    $f = growthSupply();
    growthApproveProfile($f);
    growthObserve($this, 'owner_link', '/marketplace/salons/'.$f['business']->booking_slug, 'whatsapp')->assertNoContent();
    $a = growthConfirm($this, $f, 'owner');
    $r = growthReport($f)['rows'][0];
    expect($r['source'])->toBe('owner_link')->and($r['entry'])->toBe('marketplace_profile')->and($r['owner_source'])->toBe('whatsapp');
    $this->deleteJson('/marketplace/acquisition')->assertNoContent();
    $a = app(AppointmentLifecycleCommand::class)->transition($a, 'cancelled_by_client', 'cancel-owner', $a->version, 'test', reason: 'Client changed plans.');
    $r = growthReport($f)['rows'][0];
    expect($r['source'])->toBe('unknown')->and($r['cancellations'])->toBe(1)->and($r['completed_visits'])->toBe(0);
});

it('does not attribute failed abandoned private-source or nonconsenting requests', function () {
    $f = growthSupply();
    $base = '/marketplace/salons/'.$f['business']->booking_slug.'/booking/';
    $start = $this->postJson($base.'start')->assertOk()->json();
    $this->postJson($base.'confirm', ['flow' => $start['flow'], 'secret' => $start['secret'], 'client_name' => 'No hold', 'client_mobile' => '+14165550123', 'client_email' => 'nohold@example.test', 'policy_accepted' => true, 'idempotency_key' => 'missing-hold'])->assertConflict();
    expect(DB::table('marketplace_booking_attributions')->first()->first_source)->toBe('unknown');
    expect(growthReport($f)['rows'])->toBe([]);
    DB::table('public_booking_flows')->update(['expires_at' => now()->subSecond()]);
    expect(growthReport($f)['unconfirmed_expired_sessions'])->toBe(1);
    $this->postJson('/marketplace/acquisition/observe', ['consent' => false, 'version' => 'mpg-consent-v1', 'page' => '/marketplace', 'source' => 'marketplace_discovery', 'owner_source' => 'unknown'])->assertUnprocessable();
    growthObserve($this, 'direct', '/marketplace/account')->assertUnprocessable();
    growthObserve($this, 'direct', '/appointments/secure/private-token')->assertUnprocessable();
    $this->withHeader('Sec-GPC', '1');
    growthObserve($this)->assertNoContent();
    $s = $this->postJson($base.'start', [], ['X-Marketplace-Analytics-Consent' => '1'])->assertOk()->json();
    expect(DB::table('marketplace_booking_attributions')->orderByDesc('id')->first()->first_source)->toBe('unknown');
    expect(DB::table('marketplace_booking_attributions')->whereNotNull('appointment_id')->count())->toBe(0);
});

it('rejects cross-tenant owner reports and prunes optional evidence without deleting history', function () {
    $f = growthSupply();
    growthObserve($this)->assertNoContent();
    growthConfirm($this, $f, 'retention');
    $other = growthSupply();
    expect(fn () => app(GrowthOutcomeReport::class)->for($f['business'], $other['membership'], []))->toThrow(HttpException::class);
    expect(growthReport($other)['rows'])->toBe([]);
    $this->travel(61)->days();
    $this->artisan('marketplace:growth-prune')->assertSuccessful();
    expect(DB::table('marketplace_booking_attributions')->count())->toBe(1)->and(DB::table('marketplace_booking_attributions')->first()->first_source)->toBe('unknown');
    expect(Appointment::query()->where('business_id', $f['business']->id)->count())->toBe(1);
});

it('imports only authorized aggregate canonical page data idempotently and never invents Google data', function () {
    $f = growthSupply();
    $page = growthApproveProfile($f);
    config(['brand.website_url' => 'https://clipperdesk.example']);
    $import = app(SearchPerformanceImport::class);
    $data = [['day' => '2026-11-07', 'path' => $page->path, 'impressions' => 8, 'clicks' => 3, 'position' => 12.5]];
    $import->import($data, 'https://clipperdesk.example', 'authorized export', 'Test reviewer');
    expect(DB::table('marketplace_search_performance')->count())->toBe(0);
    $import->import($data, 'https://clipperdesk.example', 'authorized export', 'Test reviewer', true);
    $import->import($data, 'https://clipperdesk.example', 'authorized export', 'Test reviewer', true);
    expect(DB::table('marketplace_search_performance')->count())->toBe(1);
    expect(fn () => $import->import([[...$data[0], 'query' => 'private search phrase']], 'https://clipperdesk.example', 'export', 'reviewer', true))->toThrow(ValidationException::class);
    expect(fn () => $import->import([[...$data[0], 'path' => '/marketplace/account']], 'https://clipperdesk.example', 'export', 'reviewer', true))->toThrow(ValidationException::class);
});

it('separates direct owner entry no-shows currencies and allocation transfers without double counting', function () {
    $f = growthSupply();
    growthObserve($this, 'owner_link', 'provider:'.$f['business']->public_id, 'owner_website')->assertNoContent();
    $a = growthConfirm($this, $f, 'cad', false);
    $payment = PaymentTransaction::query()->create(['business_id' => $f['business']->id, 'appointment_id' => $a->id, 'kind' => 'payment', 'status' => 'succeeded', 'method' => 'cash', 'amount_minor' => 3500, 'currency_code' => 'CAD', 'idempotency_key' => 'cad-received', 'occurred_at' => now()]);
    $lifecycle = app(AppointmentLifecycleCommand::class);
    $a = $lifecycle->transition($a, 'no_show', 'no-show-cad', $a->version, 'test', reason: 'Did not attend.');
    $lifecycle->transition($a, 'no_show', 'no-show-cad', $a->version - 1, 'test', reason: 'Did not attend.');
    $r = growthReport($f)['rows'][0];
    expect($r['entry'])->toBe('owner_booking_link')->and($r['source'])->toBe('owner_link')->and($r['no_shows'])->toBe(1);
    $this->travel(61)->seconds(); // a separate booking intent after the existing shared mutation cooldown
    $f['service']->update(['currency_code' => 'USD']);
    app(PublicSupplyProjection::class)->rebuild($f['business']->id);
    $b = growthConfirm($this, $f, 'usd', false);
    PaymentTransaction::query()->create(['business_id' => $f['business']->id, 'appointment_id' => $b->id, 'kind' => 'payment', 'status' => 'succeeded', 'method' => 'cash', 'amount_minor' => 3200, 'currency_code' => 'USD', 'idempotency_key' => 'usd-received', 'occurred_at' => now()]);
    expect(growthReport($f)['rows'][0]['collected_minor'])->toBe(3500);
    expect(growthReport($f, 'USD')['rows'][0]['collected_minor'])->toBe(3200);
    expect(growthReport($f, 'USD')['rows'][0]['confirmations'])->toBe(1);
    $cadDuplicate = $a->replicate()->fill(['public_id' => (string) Str::ulid(), 'idempotency_key' => 'cad-target', 'booking_reference' => 'CAD-TARGET', 'status' => 'confirmed', 'marketplace_attribution_root_id' => null]);
    $cadDuplicate->save();
    $cadDuplicate->update(['marketplace_attribution_root_id' => $cadDuplicate->id]);
    $flow = PublicBookingFlow::query()->create(['business_id' => $f['business']->id, 'appointment_id' => $cadDuplicate->id, 'policy_version' => 1, 'secret_hash' => hash('sha256', 'synthetic'), 'status' => 'confirmed', 'state' => [], 'expires_at' => now()->addHour(), 'confirmed_at' => now()]);
    PaymentTransaction::query()->create(['business_id' => $f['business']->id, 'appointment_id' => $cadDuplicate->id, 'parent_transaction_id' => $payment->id, 'kind' => 'correction', 'status' => 'succeeded', 'method' => 'cash', 'amount_minor' => 400, 'currency_code' => 'CAD', 'idempotency_key' => 'move-cad-allocation', 'occurred_at' => now()]);
    $rows = growthReport($f)['rows'];
    expect(array_sum(array_column($rows, 'collected_minor')))->toBe(3500);
    expect(array_sum(array_column($rows, 'net_recorded_minor')))->toBe(3500);
    expect(array_sum(array_column($rows, 'allocation_in_minor')))->toBe(400)->and(array_sum(array_column($rows, 'allocation_out_minor')))->toBe(400);
});
