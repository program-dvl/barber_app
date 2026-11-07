<?php

use App\Domain\Billing\Services\EntitlementEvaluator;
use App\Domain\BusinessConfiguration\Models\BusinessPhoto;
use App\Domain\BusinessConfiguration\Services\BusinessPhotoGallery;
use App\Domain\BusinessConfiguration\Services\ReadinessEvaluator;
use App\Domain\BusinessConfiguration\Services\StaffPhotoService;
use App\Domain\ClientRecords\Models\Client;
use App\Domain\ClientRecords\Models\ClientFormSubmission;
use App\Domain\ClientRecords\Models\ClientNote;
use App\Domain\Commissions\Models\CommissionEntry;
use App\Domain\Communications\Models\CommunicationMessage;
use App\Domain\Inventory\Models\InventoryMovement;
use App\Domain\MoneyCommerce\Models\CashClose;
use App\Domain\MoneyCommerce\Models\PaymentTransaction;
use App\Domain\MoneyCommerce\Models\Sale;
use App\Domain\MoneyCommerce\Models\SaleReceipt;
use App\Domain\PlatformAccess\Models\AuditEvent;
use App\Domain\PlatformAccess\Models\Business;
use App\Domain\PlatformAccess\Models\Membership;
use App\Domain\PlatformAccess\Models\StaffProfile;
use App\Domain\PublicBooking\Models\WaitlistRequest;
use App\Domain\SchedulingOperations\Models\Appointment;
use App\Domain\SchedulingOperations\Models\OperationalNotificationEvent;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Database\Seeders\PitchDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('private');
    Storage::fake('public');
    Bus::fake();
    Mail::fake();
    Http::preventStrayRequests();
    config(['inertia.ssr.enabled' => false]);
    $this->withoutExceptionHandling();
    Carbon::setTestNow('2026-11-07T12:43:21.784321+05:30');
    CarbonImmutable::setTestNow('2026-11-07T12:43:21.784321+05:30');
});
afterEach(function () {
    Carbon::setTestNow();
    CarbonImmutable::setTestNow();
});

it('creates a usable isolated pitch business and safely preserves it on rerun', function () {
    $other = Business::factory()->create();
    $otherName = $other->name;
    config(['communications.dispatch_operational_events_immediately' => true]);
    $this->seed(PitchDemoSeeder::class);
    $business = Business::query()->where('slug', PitchDemoSeeder::SLUG)->firstOrFail();
    expect($business->public_id)->not->toBeEmpty()
        ->and($business->country_code)->toBe('CA')
        ->and($business->currency_code)->toBe('CAD')
        ->and($business->locale)->toBe('en-CA')
        ->and($business->time_zone)->toBe('America/Toronto')
        ->and($business->subscription->capacity_snapshot['currency'])->toBe('CAD')
        ->and($business->onboardingSession->guided_completed_at)->not->toBeNull()
        ->and($business->locations()->count())->toBe(2)
        ->and($business->physicalResources()->sum('quantity'))->toBe(10)
        ->and($business->memberships()->count())->toBe(14)
        ->and($business->staffProfiles()->count())->toBe(10)
        ->and($business->services()->count())->toBe(8)
        ->and($business->clients()->count())->toBe(60)
        ->and($other->fresh()->name)->toBe($otherName)
        ->and(app(EntitlementEvaluator::class)->value($business, 'staff.max'))->toBe(10)
        ->and(app(EntitlementEvaluator::class)->value($business, 'locations.max'))->toBe(2)
        ->and(config('communications.dispatch_operational_events_immediately'))->toBeTrue()
        ->and(CarbonImmutable::now('Asia/Kolkata')->format('H:i:s.u'))->toBe('12:43:21.784321');
    $today = CarbonImmutable::now('America/Toronto')->startOfDay();
    $visits = Appointment::query()->where('business_id', $business->id);
    expect(CarbonImmutable::parse((clone $visits)->min('starts_at_utc'))->setTimezone('America/Toronto')->toDateString())->toBe($today->subMonthsNoOverflow(2)->toDateString())
        ->and(CarbonImmutable::parse((clone $visits)->max('starts_at_utc'))->setTimezone('America/Toronto')->toDateString())->toBe($today->addMonthsNoOverflow(2)->toDateString())
        ->and((clone $visits)->where('currency_code', '!=', 'CAD')->count())->toBe(0)
        ->and($business->services()->where('price_minor', '>', 20000)->count())->toBe(0);
    $dstOffsets = (clone $visits)->get()->map(fn ($visit) => $visit->starts_at_utc->setTimezone('America/Toronto')->format('P'))->unique()->sort()->values()->all();
    expect($dstOffsets)->toBe(['-04:00', '-05:00']);
    foreach ($business->memberships()->with('user')->get() as $membership) {
        expect($membership->user->hasVerifiedEmail())->toBeTrue()
            ->and(Hash::check('Password@123', $membership->user->password))->toBeTrue()
            ->and($membership->user->is_admin)->toBeFalsy()
            ->and(Storage::disk('public')->exists($membership->user->profile_photo_path))->toBeTrue();
    }
    foreach (StaffProfile::query()->where('business_id', $business->id)->with('locations', 'membership.locations')->get() as $staff) {
        expect($staff->membership->locations->pluck('id')->all())->toBe($staff->locations->pluck('id')->all())
            ->and($staff->user_id)->toBe($staff->membership->user_id)
            ->and($staff->photo_path)->not->toBeNull();
    }
    expect(Appointment::query()->where('business_id', $business->id)->whereDate('starts_at_utc', '<', now()->toDateString())->count())->toBeGreaterThan(300)
        ->and(Appointment::query()->where('business_id', $business->id)->whereDate('starts_at_utc', '>', now()->toDateString())->count())->toBeGreaterThan(300)
        ->and(Appointment::query()->where('business_id', $business->id)->whereNull('booking_reference')->count())->toBe(0)
        ->and(Sale::query()->where('business_id', $business->id)->count())->toBeGreaterThan(300)
        ->and(Sale::query()->where('business_id', $business->id)->where('status', 'open')->count())->toBe(1)
        ->and(PaymentTransaction::query()->where('business_id', $business->id)->where('kind', 'refund')->count())->toBe(1)
        ->and(SaleReceipt::query()->where('business_id', $business->id)->count())->toBeGreaterThan(300)
        ->and(CommissionEntry::query()->where('business_id', $business->id)->count())->toBeGreaterThan(300)
        ->and(InventoryMovement::query()->where('business_id', $business->id)->where('type', 'sale')->count())->toBeGreaterThan(0)
        ->and(ClientNote::query()->where('business_id', $business->id)->count())->toBe(60)
        ->and(ClientFormSubmission::query()->where('business_id', $business->id)->count())->toBe(12)
        ->and(CashClose::query()->where('business_id', $business->id)->count())->toBe(14)
        ->and(WaitlistRequest::query()->where('business_id', $business->id)->count())->toBe(6)
        ->and(BusinessPhoto::query()->where('business_id', $business->id)->count())->toBe(2)
        ->and(CommunicationMessage::query()->where('status', 'suppressed')->count())->toBe(20)
        ->and(OperationalNotificationEvent::query()->where('business_id', $business->id)->where('status', 'pending')->count())->toBe(0);
    foreach (Sale::query()->where('business_id', $business->id)->get() as $sale) {
        expect($sale->paid_minor - $sale->refunded_minor + $sale->deposit_applied_minor + $sale->balance_minor)->toBe($sale->total_minor);
    }
    // Future appointments must never overlap another appointment for the same professional.
    $overlaps = DB::table('appointment_segments as a')->join('appointment_segments as b', function ($join) {
        $join->on('a.staff_profile_id', '=', 'b.staff_profile_id')->on('a.id', '<', 'b.id')->on('a.starts_at_utc', '<', 'b.ends_at_utc')->on('b.starts_at_utc', '<', 'a.ends_at_utc');
    })->where('a.business_id', $business->id)->count();
    expect($overlaps)->toBe(0);
    $owner = User::query()->where('email', 'pitch.owner@example.test')->firstOrFail();
    Bus::assertNothingDispatched();
    Mail::assertNothingSent();
    $this->post('/login', ['email' => $owner->email, 'password' => 'Password@123'])->assertRedirect();
    $this->assertAuthenticatedAs($owner);
    // Normal login security notices are outside the seeder; isolate subsequent seed checks.
    Bus::fake();
    $this->actingAs($owner);
    foreach (['business.dashboard', 'business.calendar', 'business.checkout.index', 'business.inventory.index', 'business.reports.index', 'business.walk-ins.index', 'business.clients.index', 'business.team.index', 'business.services.index', 'business.configuration.show', 'business.communications.page', 'business.billing.show'] as $route) {
        $this->get(route($route, $business))->assertOk();
    }
    $this->get(route('business.configuration.show', $business))->assertInertia(fn (Assert $page) => $page
        ->component('Configuration/Onboarding')->where('business.currency_code', 'CAD')->where('business.country_code', 'CA')
        ->where('onboarding.guided_completed_at', fn ($value) => filled($value))
        ->where('onboarding.personalized_at', fn ($value) => filled($value))
        ->where('onboarding.generated_data.source', 'pitch_demo')->missing('onboarding.generated_data.version'));
    foreach (app(BusinessPhotoGallery::class)->presentation($business)['photos'] as $photo) {
        $this->get($photo['url'])->assertOk()->assertHeader('Content-Type', 'image/jpeg');
    }
    $professional = $business->staffProfiles()->firstOrFail();
    $this->get(app(StaffPhotoService::class)->teamUrl($business, $professional))->assertOk()->assertHeader('Content-Type', 'image/jpeg');
    $this->app['session']->flush();
    $this->actingAs($professional->user);
    $this->get(route('business.calendar', $business))->assertOk();
    $this->app['session']->flush();
    $this->actingAs($owner);
    expect(app(ReadinessEvaluator::class)->evaluate($business->fresh())->publishable)->toBeTrue();
    $counts = [Appointment::count(), Sale::count(), PaymentTransaction::count(), User::count(), count(Storage::disk('public')->allFiles())];
    $owner->forceFill(['password' => Hash::make('Changed-demo-password')])->save();
    $this->seed(PitchDemoSeeder::class);
    expect([Appointment::count(), Sale::count(), PaymentTransaction::count(), User::count(), count(Storage::disk('public')->allFiles())])->toBe($counts);
    expect(Hash::check('Password@123', $owner->fresh()->password))->toBeTrue();
    Membership::query()->create(['business_id' => $other->id, 'user_id' => $owner->id, 'status' => 'active', 'joined_at' => now()]);
    $owner->forceFill(['password' => Hash::make('Shared-account-keep-this')])->save();
    expect(fn () => $this->seed(PitchDemoSeeder::class))->toThrow(RuntimeException::class, 'ownership changed');
    expect(Hash::check('Shared-account-keep-this', $owner->fresh()->password))->toBeTrue();
    Bus::assertNothingDispatched();
    Mail::assertNothingSent();
});

it('refuses existing login collisions without changing accounts', function () {
    $user = User::factory()->create(['email' => 'pitch.owner@example.test']);
    $password = $user->password;
    expect(fn () => $this->seed(PitchDemoSeeder::class))->toThrow(RuntimeException::class, 'already exists');
    expect($user->fresh()->password)->toBe($password)->and(Business::count())->toBe(0);
});

it('rolls back a late failure including images and restores the process settings', function () {
    config(['communications.dispatch_operational_events_immediately' => true]);
    AuditEvent::creating(function ($event) {
        if ($event->action === 'demo.pitch.seeded') {
            throw new RuntimeException('Demo test failure after media.');
        }
    });
    expect(fn () => $this->seed(PitchDemoSeeder::class))->toThrow(RuntimeException::class, 'after media');
    expect(Business::count())->toBe(0)->and(User::count())->toBe(0)
        ->and(Appointment::count())->toBe(0)->and(Sale::count())->toBe(0)
        ->and(Storage::disk('private')->allFiles())->toBe([])
        ->and(Storage::disk('public')->allFiles())->toBe([])
        ->and(config('communications.dispatch_operational_events_immediately'))->toBeTrue()
        ->and(CarbonImmutable::now('Asia/Kolkata')->format('H:i:s.u'))->toBe('12:43:21.784321');
    Bus::assertNothingDispatched();
});

it('upgrades an existing demo to Canada without rewriting issued financial history', function () {
    $this->seed(PitchDemoSeeder::class);
    $business = Business::query()->where('slug', PitchDemoSeeder::SLUG)->firstOrFail();
    // Reproduce the version-one saved region, missing guided completion and legacy currency records.
    DB::table('audit_events')->where('business_id', $business->id)->where('action', 'demo.pitch.seeded')->update(['after' => json_encode(['version' => 1])]);
    $business->update(['country_code' => 'IN', 'currency_code' => 'INR', 'locale' => 'en-IN', 'time_zone' => 'Asia/Kolkata']);
    $business->locations()->update(['time_zone' => 'Asia/Kolkata']);
    $business->onboardingSession->update(['personalized_at' => null, 'guided_completed_at' => null]);
    $sale = Sale::query()->where('business_id', $business->id)->where('status', 'completed')->firstOrFail();
    DB::table('sales')->where('id', $sale->id)->update(['currency_code' => 'INR']);
    DB::table('payment_transactions')->where('sale_id', $sale->id)->update(['currency_code' => 'INR']);
    $receipt = SaleReceipt::query()->where('sale_id', $sale->id)->firstOrFail();
    $snapshot = $receipt->snapshot;
    $snapshot['sale']['currency_code'] = 'INR';
    DB::table('sale_receipts')->where('id', $receipt->id)->update(['snapshot' => json_encode($snapshot)]);
    $originalSale = (array) DB::table('sales')->where('id', $sale->id)->first();
    $originalPayments = DB::table('payment_transactions')->where('sale_id', $sale->id)->get()->toJson();
    $originalReceipt = (array) DB::table('sale_receipts')->where('id', $receipt->id)->first();
    $future = Appointment::query()->where('business_id', $business->id)->where('starts_at_utc', '>', now())->where('status', 'confirmed')->firstOrFail();
    DB::table('booking_command_keys')->where('business_id', $business->id)->where('idempotency_key', $future->idempotency_key)->delete();
    $future->update(['currency_code' => 'INR', 'idempotency_key' => 'pitch-visit-legacy-future']);
    $manualClient = Client::query()->create(['business_id' => $business->id, 'name' => 'Manual demo guest', 'normalized_name' => 'manualdemoguest', 'email' => 'manual@example.test', 'mobile' => '+12025550199', 'status' => 'active']);
    $manualStart = CarbonImmutable::now('America/Toronto')->addDays(3)->setTime(18, 0);
    $manualVisit = Appointment::query()->create(['business_id' => $business->id, 'location_id' => $business->locations()->first()->id, 'client_id' => $manualClient->id, 'client_name' => $manualClient->name, 'currency_code' => 'CAD', 'price_minor' => 5000, 'status' => 'confirmed', 'source' => 'reception', 'idempotency_key' => 'manual-demo-visit', 'request_hash' => hash('sha256', 'manual-demo-visit'), 'time_zone' => 'America/Toronto', 'starts_at_utc' => $manualStart->utc(), 'ends_at_utc' => $manualStart->addMinutes(30)->utc(), 'local_starts_at' => $manualStart->toIso8601String(), 'local_ends_at' => $manualStart->addMinutes(30)->toIso8601String()]);
    $manualSnapshot = $manualVisit->fresh()->getRawOriginal();
    $count = Appointment::count();
    $failUpgrade = true;
    AuditEvent::creating(function ($event) use (&$failUpgrade) {
        if ($failUpgrade && $event->action === 'demo.pitch.canadian_upgraded') {
            throw new RuntimeException('Injected Canadian upgrade failure.');
        }
    });
    expect(fn () => $this->seed(PitchDemoSeeder::class))->toThrow(RuntimeException::class, 'upgrade failure');
    expect($business->fresh()->currency_code)->toBe('INR')
        ->and($future->fresh()->status)->toBe('confirmed')
        ->and($business->onboardingSession()->first()->guided_completed_at)->toBeNull()
        ->and(Appointment::count())->toBe($count);
    $failUpgrade = false;
    $this->seed(PitchDemoSeeder::class);
    expect($business->fresh()->currency_code)->toBe('CAD')
        ->and($business->fresh()->country_code)->toBe('CA')
        ->and($business->fresh()->time_zone)->toBe('America/Toronto')
        ->and($business->onboardingSession()->first()->guided_completed_at)->not->toBeNull()
        ->and($future->fresh()->status)->toBe('cancelled_by_shop')
        ->and($manualClient->fresh()->mobile)->toBe('+12025550199')
        ->and($manualVisit->fresh()->getRawOriginal())->toBe($manualSnapshot)
        ->and((array) DB::table('sales')->where('id', $sale->id)->first())->toBe($originalSale)
        ->and(DB::table('payment_transactions')->where('sale_id', $sale->id)->get()->toJson())->toBe($originalPayments)
        ->and((array) DB::table('sale_receipts')->where('id', $receipt->id)->first())->toBe($originalReceipt)
        ->and(Appointment::count())->toBe($count + 1)
        ->and(AuditEvent::query()->where('action', 'demo.pitch.canadian_upgraded')->count())->toBe(1)
        ->and(OperationalNotificationEvent::query()->where('status', 'pending')->count())->toBe(0);
    $this->seed(PitchDemoSeeder::class);
    expect(Appointment::count())->toBe($count + 1)
        ->and(AuditEvent::query()->where('action', 'demo.pitch.canadian_upgraded')->count())->toBe(1);
    $owner = User::query()->where('email', 'pitch.owner@example.test')->firstOrFail();
    $this->actingAs($owner)->get(route('business.configuration.show', $business))->assertInertia(fn (Assert $page) => $page
        ->where('business.currency_code', 'CAD')->where('onboarding.guided_completed_at', fn ($value) => filled($value)));
    Bus::assertNothingDispatched();
    Mail::assertNothingSent();
});
