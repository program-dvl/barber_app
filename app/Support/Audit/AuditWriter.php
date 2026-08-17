<?php

namespace App\Support\Audit;

use App\Domain\PlatformAccess\Models\AuditEvent;
use App\Domain\PlatformAccess\Models\Business;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AuditWriter
{
    private const REDACTED_KEYS = [
        'password',
        'password_confirmation',
        'token',
        'token_hash',
        'secret',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'authorization',
        'cookie',
    ];

    public function __construct(
        private readonly TenantContext $context,
        private readonly Request $request,
    ) {}

    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @param  array<string, mixed>  $metadata
     */
    public function write(
        string $action,
        ?Business $business = null,
        ?User $actor = null,
        ?Model $target = null,
        ?string $reason = null,
        array $before = [],
        array $after = [],
        array $metadata = [],
        string $source = 'application',
        ?string $correlationId = null,
    ): AuditEvent {
        $business ??= $this->context->hasBusiness() ? $this->context->business() : null;
        $actor ??= $this->request->user();
        $membership = $this->context->membership();
        $platformRole = $actor?->activePlatformRoles()->first()?->role?->value;

        return AuditEvent::query()->create([
            'business_id' => $business?->getKey(),
            'actor_user_id' => $actor?->getKey(),
            'actor_membership_id' => $membership?->getKey(),
            'actor_platform_role' => $platformRole,
            'action' => $action,
            'auditable_type' => $target?->getMorphClass(),
            'auditable_id' => $target?->getKey(),
            'source' => $source,
            'correlation_id' => $correlationId ?? $this->request->headers->get('X-Correlation-ID') ?? (string) Str::uuid(),
            'ip_address_hash' => $this->request->ip() ? hash_hmac('sha256', $this->request->ip(), (string) config('app.key')) : null,
            'user_agent' => Str::limit((string) $this->request->userAgent(), 500, ''),
            'reason' => $reason,
            'before' => $this->redact($before),
            'after' => $this->redact($after),
            'metadata' => $this->redact($metadata),
        ]);
    }

    /** @param array<string, mixed> $values */
    private function redact(array $values): array
    {
        foreach ($values as $key => $value) {
            if (in_array(Str::lower((string) $key), self::REDACTED_KEYS, true)) {
                $values[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                $values[$key] = $this->redact($value);
            }
        }

        return $values;
    }
}
