<?php

namespace App\Domain\PlatformAccess\Services;

use App\Domain\PlatformAccess\Enums\PlatformCapability;
use App\Domain\PlatformAccess\Enums\PlatformRole;
use App\Models\User;

class PlatformAuthorizer
{
    /** @var array<string, list<PlatformCapability>> */
    private const MATRIX = [
        PlatformRole::Administrator->value => [
            PlatformCapability::TenantView, PlatformCapability::TenantLifecycle, PlatformCapability::BillingManage,
            PlatformCapability::FailureView, PlatformCapability::FailureReplay, PlatformCapability::FeatureFlagManage,
            PlatformCapability::NoticeManage, PlatformCapability::NotesManage, PlatformCapability::SupportGrantManage,
            PlatformCapability::SupportEnter, PlatformCapability::HealthView, PlatformCapability::AuditView,
            PlatformCapability::ExportInitiate, PlatformCapability::AlertView,
        ],
        PlatformRole::SupportOperator->value => [
            PlatformCapability::TenantView, PlatformCapability::FailureView, PlatformCapability::FailureReplay,
            PlatformCapability::NotesManage, PlatformCapability::SupportEnter, PlatformCapability::HealthView,
        ],
        PlatformRole::SecurityAuditor->value => [
            PlatformCapability::TenantView, PlatformCapability::HealthView, PlatformCapability::AuditView,
            PlatformCapability::AlertView,
        ],
    ];

    public function allows(User $user, PlatformCapability $capability): bool
    {
        return $user->activePlatformRoles()->get()->contains(
            fn ($assignment) => in_array($capability, self::MATRIX[$assignment->role->value] ?? [], true)
        );
    }

    public function authorize(User $user, PlatformCapability $capability): void
    {
        abort_unless($this->allows($user, $capability), 403);
    }

    /** @return list<string> */
    public function capabilities(User $user): array
    {
        return $user->activePlatformRoles()->get()
            ->flatMap(fn ($assignment) => self::MATRIX[$assignment->role->value] ?? [])
            ->map(fn (PlatformCapability $capability) => $capability->value)
            ->unique()->sort()->values()->all();
    }
}
