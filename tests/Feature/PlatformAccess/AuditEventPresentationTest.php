<?php

use App\Domain\Billing\Models\BillingPlan;
use App\Domain\Billing\Models\BillingPlanPrice;
use App\Domain\PlatformAccess\Enums\StarterRole;
use App\Domain\PlatformAccess\Models\AuditEvent;
use App\Domain\PlatformAccess\Models\BusinessRole;
use App\Support\AuditEventPresentation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('presents meaningful billing changes without exposing private payloads or rewriting history', function () {
    [$owner, $business] = createTenantMembership(StarterRole::Owner);
    $business->update(['time_zone' => 'Asia/Kolkata']);
    $plan = BillingPlan::where('code', 'pro')->firstOrFail();
    $price = BillingPlanPrice::where('billing_plan_id', $plan->id)->firstOrFail();
    $event = AuditEvent::create([
        'business_id' => $business->id, 'actor_user_id' => $owner->id,
        'action' => 'subscription.plan_change.requested', 'source' => 'test', 'correlation_id' => (string) Str::uuid(),
        'before' => ['status' => 'trialing'],
        'after' => ['status' => 'active', 'plan_id' => $plan->id, 'price_id' => $price->id,
            'effective_at' => '2026-10-02T00:00:00Z', 'notes' => 'Private treatment details', 'token' => 'secret'],
    ]);
    $event->refresh();
    $original = $event->getRawOriginal();
    $presentation = app(AuditEventPresentation::class);
    expect($presentation->label($event))->toBe('Plan change requested')
        ->and($presentation->summary($event))->toContain('Status: Free trial → Active', 'Plan: '.$plan->name, 'Effective date: 2 Oct 2026, 05:30')
        ->and($presentation->summary($event))->not->toContain('Private treatment details', 'secret', 'plan id', 'price id')
        ->and($event->fresh()->getRawOriginal())->toBe($original);
});

it('does not present unchanged fields, unknown fields, or missing catalogue IDs', function () {
    $event = new AuditEvent(['action' => 'membership.role.changed', 'before' => ['status' => 'active'], 'after' => ['status' => 'active', 'plan_id' => 999999, 'client_email' => 'private@example.test']]);
    expect(app(AuditEventPresentation::class)->summary($event))->toBe('');
});

it('keeps readable activity scoped to the authorised business', function () {
    $this->withoutVite();
    [$owner, $business] = createTenantMembership(StarterRole::Owner);
    [, $other] = createTenantMembership(StarterRole::Owner);
    AuditEvent::create(['business_id' => $business->id, 'action' => 'membership.access.revoked', 'source' => 'test', 'correlation_id' => (string) Str::uuid(), 'after' => ['status' => 'revoked']]);
    AuditEvent::create(['business_id' => $other->id, 'action' => 'membership.access.revoked', 'source' => 'test', 'correlation_id' => (string) Str::uuid(), 'reason' => 'Other business private reason']);
    $this->actingAs($owner)->get(route('business.activity.index', $business))
        ->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Team/Activity')->where('business.public_id', $business->public_id)
        ->where('business.time_zone', $business->time_zone)
        ->where('events.data', fn ($events) => collect($events)->every(fn ($event) => ($event['reason'] ?? '') !== 'Other business private reason')));
});

it('resolves role names only within the event business', function () {
    [, $business] = createTenantMembership(StarterRole::Owner);
    [, $other] = createTenantMembership(StarterRole::Owner);
    $role = BusinessRole::where('business_id', $business->id)->where('name', 'receptionist')->firstOrFail();
    $foreign = BusinessRole::where('business_id', $other->id)->firstOrFail();
    $presentation = app(AuditEventPresentation::class);
    expect($presentation->summary(new AuditEvent(['business_id' => $business->id, 'after' => ['role_id' => $role->id]])))->toBe('Role: Receptionist')
        ->and($presentation->summary(new AuditEvent(['business_id' => $business->id, 'after' => ['role_id' => $foreign->id]])))->toBe('');
});
