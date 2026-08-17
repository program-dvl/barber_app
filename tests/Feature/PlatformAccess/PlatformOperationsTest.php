<?php

use App\Domain\Billing\Enums\RestrictionLevel;
use App\Domain\Billing\Enums\SubscriptionStatus;
use App\Domain\Billing\Models\BillingPlan;
use App\Domain\Billing\Models\BillingProviderEvent;
use App\Domain\Billing\Models\BusinessSubscription;
use App\Domain\PlatformAccess\Enums\PlatformRole;
use App\Domain\PlatformAccess\Models\AuditEvent;
use App\Domain\PlatformAccess\Models\Business;
use App\Domain\PlatformAccess\Models\PlatformAccountNote;
use App\Domain\PlatformAccess\Models\PlatformRoleAssignment;
use App\Domain\PlatformAccess\Models\SupportAccessGrant;
use App\Domain\PlatformAccess\Models\SupportAccessSession;
use App\Domain\PlatformAccess\Services\SupportAccessService;
use App\Filament\Resources\Permissions\PermissionResource;
use App\Filament\Resources\Roles\RoleResource;
use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

function platformOperator(PlatformRole $role, bool $withTotp = true): User
{
    $user = User::factory()->create(['two_factor_confirmed_at' => $withTotp ? now() : null]);
    PlatformRoleAssignment::query()->create(['user_id' => $user->id, 'role' => $role, 'reason' => 'FR-20 platform test assignment.']);

    return $user;
}

function platformTrial(Business $business): BusinessSubscription
{
    return BusinessSubscription::query()->create([
        'business_id' => $business->id, 'billing_plan_id' => BillingPlan::query()->where('code', 'trial')->firstOrFail()->id,
        'provider' => 'paddle', 'status' => SubscriptionStatus::Trialing, 'restriction_level' => RestrictionLevel::None,
        'trial_started_at' => now(), 'trial_ends_at' => now()->addDays(14),
    ]);
}

it('separates platform roles and enforces MFA plus idle and absolute session limits', function () {
    $business = Business::factory()->create();
    $administrator = platformOperator(PlatformRole::Administrator);
    $support = platformOperator(PlatformRole::SupportOperator);
    $auditor = platformOperator(PlatformRole::SecurityAuditor);
    $weak = platformOperator(PlatformRole::Administrator, false);

    $this->actingAs($administrator)->postJson(route('platform.businesses.status', $business), ['status' => 'suspended', 'reason' => 'Security review GH-1001.'])->assertOk();
    $this->actingAs($support)->postJson(route('platform.businesses.status', $business), ['status' => 'active', 'reason' => 'Support cannot activate.'])->assertForbidden();
    $this->actingAs($support)->getJson(route('platform.health'))->assertOk();
    $this->actingAs($auditor)->getJson(route('platform.audit-events.index'))->assertOk();
    $this->actingAs($auditor)->postJson(route('platform.feature-flags.store'), ['key' => 'calendar.test', 'enabled' => true, 'description' => 'Test', 'reason' => 'Auditor must be read only.'])->assertForbidden();
    $this->actingAs($weak)->get(route('platform.overview'))->assertForbidden();

    $this->actingAs($administrator)->withSession([
        'platform_session_started_at' => now()->subHours(9)->timestamp,
        'platform_session_last_used_at' => now()->timestamp,
    ])->get(route('platform.overview'))->assertForbidden();
    $this->actingAs($administrator)->withSession([
        'platform_session_started_at' => now()->subHour()->timestamp,
        'platform_session_last_used_at' => now()->subMinutes(16)->timestamp,
    ])->get(route('platform.overview'))->assertForbidden();
});

it('returns only safe tenant summaries and rejects identifier manipulation', function () {
    [$owner, $business] = createTenantMembership();
    $other = Business::factory()->create();
    platformTrial($business);
    $administrator = platformOperator(PlatformRole::Administrator);

    $response = $this->actingAs($administrator)->getJson(route('platform.businesses.show', $business))->assertOk()
        ->assertJsonPath('business.owner.email', $owner->email)
        ->assertJsonMissingPath('business.owner.password')
        ->assertJsonMissingPath('business.subscription.provider_customer_id');
    expect(json_encode($response->json(), JSON_THROW_ON_ERROR))->not->toContain('two_factor')->not->toContain('recipient');
    $this->actingAs($administrator)->getJson('/platform/businesses/'.$other->public_id.'x')->assertNotFound();
});

it('requires approved scoped expiring support access and makes active access tenant visible', function () {
    [$owner, $business] = createTenantMembership();
    $other = Business::factory()->create();
    platformTrial($business);
    $administrator = platformOperator(PlatformRole::Administrator);
    $support = platformOperator(PlatformRole::SupportOperator);

    $this->actingAs($administrator)->postJson(route('platform.support-access.store', $business), [
        'operator_user_id' => $administrator->id, 'ticket_reference' => 'GH-SELF-2201', 'reason' => 'An operator must not approve their own access.',
        'scopes' => ['account_summary'], 'expires_at' => now()->addHour()->toIso8601String(),
    ])->assertUnprocessable();

    $this->actingAs($administrator)->postJson(route('platform.support-access.store', $business), [
        'operator_user_id' => $support->id, 'ticket_reference' => 'GH-2201', 'reason' => 'Investigate onboarding account summary.',
        'scopes' => ['account_summary'], 'expires_at' => now()->addHour()->toIso8601String(),
    ])->assertCreated();
    $grant = SupportAccessGrant::query()->firstOrFail();

    $this->actingAs($support)->postJson(route('platform.support-access.enter', $grant))->assertOk()
        ->assertJsonPath('session.operator', $support->name)
        ->assertJsonPath('session.ticket_reference', 'GH-2201');
    $this->getJson(route('platform.support.businesses.show', $business))->assertOk()
        ->assertJsonPath('support_context.operator_user_id', $support->id);
    $this->getJson(route('platform.support.businesses.show', $other))->assertForbidden();
    $this->getJson(route('platform.support.businesses.failures', $business))->assertForbidden();

    $this->actingAs($owner)->get(route('business.dashboard', $business))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('supportAccessBanner', 1)->where('supportAccessBanner.0.operator', $support->name)->where('supportAccessBanner.0.ticket_reference', 'GH-2201'));

    expect(AuditEvent::query()->where('action', 'support.access.granted')->exists())->toBeTrue()
        ->and(AuditEvent::query()->where('action', 'support.access.entered')->exists())->toBeTrue();
});

it('ends an active support session when its grant expires', function () {
    $business = Business::factory()->create();
    $administrator = platformOperator(PlatformRole::Administrator);
    $support = platformOperator(PlatformRole::SupportOperator);
    $grant = app(SupportAccessService::class)->grant($business, $support, $administrator, 'GH-EXPIRED-1', 'Inspect a safe account summary.', ['account_summary'], now()->addMinute());
    $this->actingAs($support)->postJson(route('platform.support-access.enter', $grant))->assertOk();
    $grant->update(['expires_at' => now()->subSecond()]);

    $this->getJson(route('platform.support.businesses.show', $business))->assertForbidden();
    expect(SupportAccessSession::query()->firstOrFail()->ended_reason)->toBe('grant_expired')
        ->and(AuditEvent::query()->where('action', 'support.access.exited')->exists())->toBeTrue();
});

it('revokes support grants immediately and preserves immutable entry and exit evidence', function () {
    $business = Business::factory()->create();
    $administrator = platformOperator(PlatformRole::Administrator);
    $support = platformOperator(PlatformRole::SupportOperator);
    $secondAdministrator = platformOperator(PlatformRole::Administrator);
    $grant = app(SupportAccessService::class)->grant($business, $support, $administrator, 'GH-2202', 'Resolve a verified webhook failure.', ['account_summary', 'webhook_failures'], now()->addHour());

    $this->actingAs($support)->postJson(route('platform.support-access.enter', $grant))->assertOk();
    $session = SupportAccessSession::query()->firstOrFail();
    $this->actingAs($secondAdministrator)->postJson(route('platform.support-access.revoke', $grant), ['reason' => 'Ticket resolved and access no longer required.'])->assertOk();
    $this->actingAs($support)->getJson(route('platform.support.businesses.show', $business))->assertForbidden();

    expect($session->fresh()->ended_reason)->toBe('grant_revoked')
        ->and(fn () => AuditEvent::query()->where('action', 'support.access.entered')->firstOrFail()->update(['reason' => 'changed']))->toThrow(LogicException::class);
});

it('restricts bulk export attempts, raises an alert, and creates only lineage-bound single-tenant requests', function () {
    $business = Business::factory()->create();
    $other = Business::factory()->create();
    $administrator = platformOperator(PlatformRole::Administrator);
    $support = platformOperator(PlatformRole::SupportOperator);

    $this->actingAs($support)->postJson(route('platform.businesses.exports', $business), ['export_type' => 'business_archive', 'reason' => 'Support request GH-3000.'])->assertForbidden();
    $this->actingAs($administrator)->postJson(route('platform.businesses.exports', $business), [
        'export_type' => 'business_archive', 'reason' => 'Approved data portability request GH-3001.', 'business_ids' => [$business->public_id, $other->public_id],
    ])->assertUnprocessable();
    expect(DB::table('platform_export_requests')->count())->toBe(0)
        ->and(DB::table('platform_alerts')->where('kind', 'restricted_bulk_export_attempt')->where('severity', 'critical')->exists())->toBeTrue();

    $this->actingAs($administrator)->postJson(route('platform.businesses.exports', $business), ['export_type' => 'business_archive', 'reason' => 'Approved data portability request GH-3002.'])->assertAccepted();
    expect(DB::table('platform_export_requests')->value('business_id'))->toBe($business->id)
        ->and(json_decode(DB::table('platform_export_requests')->value('scope_snapshot'), true)['business_id'])->toBe($business->id);
});

it('manages application-owned flags notices and append-only internal notes with explicit visibility', function () {
    $business = Business::factory()->create();
    $administrator = platformOperator(PlatformRole::Administrator);
    $support = platformOperator(PlatformRole::SupportOperator);

    $this->actingAs($administrator)->postJson(route('platform.feature-flags.store'), [
        'key' => 'booking.waitlist_v2', 'enabled' => true, 'description' => 'Reviewed waitlist rollout.', 'reason' => 'Enable for controlled production validation.', 'business_id' => $business->public_id,
    ])->assertOk()->assertJsonPath('flag.enabled', true);
    $this->actingAs($administrator)->postJson(route('platform.notices.store'), [
        'title' => 'Provider maintenance', 'message' => 'Message delivery may be delayed.', 'severity' => 'warning', 'audience' => 'single_business',
        'business_id' => $business->public_id, 'starts_at' => now()->toIso8601String(), 'ends_at' => now()->addHour()->toIso8601String(), 'reason' => 'Publish provider maintenance notice.',
    ])->assertCreated();
    $this->actingAs($support)->postJson(route('platform.businesses.notes', $business), ['body' => 'Owner confirmed callback retry window.', 'retain_until' => now()->addYear()->toDateString()])->assertCreated()
        ->assertJsonPath('note.visibility', 'platform_internal');
    $note = PlatformAccountNote::query()->firstOrFail();
    expect(fn () => $note->update(['body' => 'rewritten']))->toThrow(LogicException::class);
});

it('replays a verified provider failure once and returns the original result for duplicate operator commands', function () {
    $business = Business::factory()->create();
    $support = platformOperator(PlatformRole::SupportOperator);
    $payload = ['id' => 'evt_platform_replay', 'type' => 'unknown.safe_test', 'created' => now()->timestamp, 'data' => ['object' => []]];
    $event = BillingProviderEvent::query()->create([
        'business_id' => $business->id, 'provider' => 'stripe', 'provider_event_id' => $payload['id'], 'event_type' => $payload['type'],
        'status' => 'failed', 'signature_verified' => true, 'provider_created_at' => now(), 'attempts' => 1,
        'payload_hash' => hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR)), 'payload' => $payload, 'last_error' => 'Simulated transient processor failure.',
    ]);

    $body = ['operation_key' => 'GH-REPLAY-4001', 'reason' => 'Provider recovered; replay verified idempotent event.'];
    $this->actingAs($support)->postJson(route('platform.failures.replay', ['billing_webhook', $event->id]), $body)->assertOk()
        ->assertJsonPath('replay.duplicate', false)->assertJsonPath('replay.result_code', 'ignored');
    $this->actingAs($support)->postJson(route('platform.failures.replay', ['billing_webhook', $event->id]), $body)->assertOk()
        ->assertJsonPath('replay.duplicate', true)->assertJsonPath('replay.result_code', 'ignored');

    expect($event->fresh()->attempts)->toBe(2)
        ->and(DB::table('platform_replay_attempts')->count())->toBe(1)
        ->and(AuditEvent::query()->where('action', 'platform.failure.replayed')->exists())->toBeTrue();
});

it('alerts when one operator enters an unusual number of tenants in a short window', function () {
    $administrator = platformOperator(PlatformRole::Administrator);
    $support = platformOperator(PlatformRole::SupportOperator);
    foreach (range(1, 3) as $index) {
        $business = Business::factory()->create();
        $grant = app(SupportAccessService::class)->grant($business, $support, $administrator, 'GH-CROSS-'.$index, 'Investigate correlated provider incident.', ['account_summary'], now()->addHour());
        $this->actingAs($support)->postJson(route('platform.support-access.enter', $grant))->assertOk();
    }

    expect(DB::table('platform_alerts')->where('kind', 'unusual_cross_tenant_access')->where('severity', 'high')->exists())->toBeTrue();
});

it('quarantines legacy Filament identity and permission editors after the platform resource audit', function () {
    expect(UserResource::canAccess())->toBeFalse()
        ->and(RoleResource::canAccess())->toBeFalse()
        ->and(PermissionResource::canAccess())->toBeFalse();
});
