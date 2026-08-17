<?php

use App\Domain\PlatformAccess\Enums\StarterRole;
use App\Domain\PlatformAccess\Models\AuditEvent;
use App\Support\Audit\AuditWriter;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('writes tenant-attributed redacted append-only audit events', function () {
    [$user, $business, $membership] = createTenantMembership(StarterRole::Owner);
    app(TenantContext::class)->activate($business, $membership);

    $event = app(AuditWriter::class)->write(
        action: 'settings.security.changed',
        business: $business,
        actor: $user,
        target: $membership,
        reason: 'Security policy review.',
        before: ['token' => 'secret-token', 'setting' => 'old'],
        after: ['password' => 'secret-password', 'setting' => 'new'],
    );

    expect($event->business_id)->toBe($business->id)
        ->and($event->actor_user_id)->toBe($user->id)
        ->and($event->actor_membership_id)->toBe($membership->id)
        ->and($event->before['token'])->toBe('[REDACTED]')
        ->and($event->after['password'])->toBe('[REDACTED]')
        ->and($event->reason)->toBe('Security policy review.');

    expect(fn () => $event->update(['reason' => 'rewritten']))->toThrow(LogicException::class)
        ->and(fn () => $event->delete())->toThrow(LogicException::class)
        ->and(AuditEvent::query()->whereKey($event->id)->exists())->toBeTrue();

    app(TenantContext::class)->clear();
});
