<?php

namespace App\Domain\PlatformAccess\Services;

use App\Domain\PlatformAccess\Enums\PlatformRole;
use App\Domain\PlatformAccess\Enums\SupportScope;
use App\Domain\PlatformAccess\Models\Business;
use App\Domain\PlatformAccess\Models\SupportAccessGrant;
use App\Domain\PlatformAccess\Models\SupportAccessSession;
use App\Models\User;
use App\Support\Audit\AuditWriter;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SupportAccessService
{
    public function __construct(private readonly AuditWriter $audit) {}

    /** @param list<string> $scopes */
    public function grant(Business $business, User $operator, User $approver, string $ticket, string $reason, array $scopes, CarbonInterface $expiresAt): SupportAccessGrant
    {
        abort_unless($operator->hasPlatformRole(PlatformRole::SupportOperator) || $operator->hasPlatformRole(PlatformRole::Administrator), 422, 'The operator has no active support role.');
        if ($operator->is($approver)) {
            throw ValidationException::withMessages(['approved_by' => 'Support access requires explicit approval by another platform administrator.']);
        }
        $allowed = collect($scopes)->unique()->values();
        if ($allowed->isEmpty() || $allowed->contains(fn ($scope) => SupportScope::tryFrom((string) $scope) === null)) {
            throw ValidationException::withMessages(['scopes' => 'Every support scope must be explicit and approved.']);
        }
        if ($expiresAt->isPast() || $expiresAt->greaterThan(now()->addHours(4))) {
            throw ValidationException::withMessages(['expires_at' => 'Support grants must expire within four hours.']);
        }

        $grant = SupportAccessGrant::query()->create([
            'business_id' => $business->id, 'operator_user_id' => $operator->id, 'approved_by_user_id' => $approver->id,
            'ticket_reference' => trim($ticket), 'reason' => trim($reason), 'scopes' => $allowed->all(), 'expires_at' => $expiresAt,
        ]);
        $this->audit->write('support.access.granted', $business, $approver, $grant, $reason, after: ['operator_user_id' => $operator->id, 'ticket_reference' => $ticket, 'scopes' => $allowed->all(), 'expires_at' => $expiresAt->toIso8601String()], source: 'platform');

        return $grant;
    }

    public function enter(SupportAccessGrant $grant, User $operator, Request $request): SupportAccessSession
    {
        abort_unless($grant->operator_user_id === $operator->id && $grant->isActive(), 403);
        $sessionToken = Str::random(64);
        $session = DB::transaction(function () use ($grant, $operator, $sessionToken): SupportAccessSession {
            SupportAccessSession::query()->where('operator_user_id', $operator->id)->whereNull('ended_at')->with(['business', 'grant'])->get()
                ->each(function (SupportAccessSession $open) use ($operator): void {
                    $open->update(['ended_at' => now(), 'ended_reason' => 'superseded']);
                    $this->audit->write('support.access.exited', $open->business, $operator, $open, 'A newer support session superseded this session.', after: ['grant_id' => $open->grant->public_id, 'operator_user_id' => $open->operator_user_id, 'ended_reason' => 'superseded'], source: 'support');
                });
            $row = SupportAccessSession::query()->create([
                'support_access_grant_id' => $grant->id, 'business_id' => $grant->business_id, 'operator_user_id' => $operator->id,
                'session_fingerprint' => hash_hmac('sha256', $sessionToken, (string) config('app.key')),
                'started_at' => now(), 'last_used_at' => now(),
            ]);
            $this->audit->write('support.access.entered', $grant->business, $operator, $row, $grant->reason, after: ['grant_id' => $grant->public_id, 'ticket_reference' => $grant->ticket_reference, 'scopes' => $grant->scopes, 'expires_at' => $grant->expires_at->toIso8601String()], source: 'support');

            return $row;
        });
        $request->session()->put('support_access_session_id', $session->public_id);
        $request->session()->put('support_access_session_token', $sessionToken);
        $this->detectCrossTenantPattern($operator, $grant->business);

        return $session;
    }

    public function requireSession(Request $request, Business $business, SupportScope $scope): SupportAccessSession
    {
        $session = SupportAccessSession::query()->where('public_id', $request->session()->get('support_access_session_id'))->with('grant')->first();
        $sessionToken = $request->session()->get('support_access_session_token');
        $fingerprint = is_string($sessionToken) ? hash_hmac('sha256', $sessionToken, (string) config('app.key')) : '';
        abort_unless(
            $session
            && $session->operator_user_id === $request->user()?->id
            && $session->business_id === $business->id
            && hash_equals($session->session_fingerprint, $fingerprint),
            403
        );
        if (! $session->isActive()) {
            if ($session->ended_at === null) {
                $endedReason = $session->grant->revoked_at ? 'grant_revoked' : 'grant_expired';
                $session->update(['ended_at' => now(), 'ended_reason' => $endedReason]);
                $this->audit->write('support.access.exited', $business, $request->user(), $session, 'Support grant is no longer active.', after: ['grant_id' => $session->grant->public_id, 'operator_user_id' => $session->operator_user_id, 'ended_reason' => $endedReason], source: 'support');
            }
            abort(403);
        }
        abort_unless($session->grant->permits($scope->value), 403);
        $session->update(['last_used_at' => now()]);

        return $session;
    }

    public function leave(SupportAccessSession $session, User $operator, string $reason = 'operator_exit'): void
    {
        abort_unless($session->operator_user_id === $operator->id, 403);
        if ($session->ended_at === null) {
            $session->update(['ended_at' => now(), 'ended_reason' => $reason]);
            $this->audit->write('support.access.exited', $session->business, $operator, $session, $reason, after: ['grant_id' => $session->grant->public_id], source: 'support');
        }
    }

    public function revoke(SupportAccessGrant $grant, User $actor, string $reason): void
    {
        if ($grant->revoked_at !== null) {
            return;
        }
        DB::transaction(function () use ($grant, $actor, $reason): void {
            $grant->update(['revoked_at' => now(), 'revoked_by_user_id' => $actor->id, 'revocation_reason' => $reason]);
            $grant->sessions()->whereNull('ended_at')->with('business')->get()->each(function (SupportAccessSession $session) use ($actor, $grant): void {
                $session->update(['ended_at' => now(), 'ended_reason' => 'grant_revoked']);
                $this->audit->write('support.access.exited', $session->business, $actor, $session, 'Support grant was revoked.', after: ['grant_id' => $grant->public_id, 'operator_user_id' => $session->operator_user_id, 'ended_reason' => 'grant_revoked'], source: 'support');
            });
            $this->audit->write('support.access.revoked', $grant->business, $actor, $grant, $reason, after: ['ticket_reference' => $grant->ticket_reference], source: 'platform');
        });
    }

    private function detectCrossTenantPattern(User $operator, Business $business): void
    {
        $tenantCount = SupportAccessSession::query()->where('operator_user_id', $operator->id)->where('started_at', '>=', now()->subMinutes(15))->distinct()->count('business_id');
        if ($tenantCount < 3) {
            return;
        }
        DB::table('platform_alerts')->insert([
            'public_id' => (string) Str::ulid(), 'operator_user_id' => $operator->id, 'business_id' => $business->id,
            'kind' => 'unusual_cross_tenant_access', 'severity' => 'high', 'summary' => 'Operator entered three or more businesses within fifteen minutes.',
            'evidence' => json_encode(['tenant_count' => $tenantCount, 'window_minutes' => 15], JSON_THROW_ON_ERROR), 'detected_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}
