<?php

namespace App\Domain\PlatformAccess\Services;

use App\Domain\PlatformAccess\Models\Business;
use App\Domain\PlatformAccess\Models\PlatformFeatureFlag;
use App\Models\User;
use App\Support\Audit\AuditWriter;

class PlatformFeatureFlagService
{
    public function __construct(private readonly AuditWriter $audit) {}

    public function enabled(string $key, ?Business $business = null): bool
    {
        $tenant = $business ? PlatformFeatureFlag::query()->where(['key' => $key, 'scope_type' => 'business', 'scope_id' => $business->id])->first() : null;

        return $tenant?->enabled ?? PlatformFeatureFlag::query()->where(['key' => $key, 'scope_type' => 'global', 'scope_id' => 0])->value('enabled') ?? false;
    }

    public function set(string $key, bool $enabled, string $description, string $reason, User $actor, ?Business $business = null): PlatformFeatureFlag
    {
        abort_unless((bool) preg_match('/^[a-z][a-z0-9_.-]{2,95}$/', $key), 422, 'Feature flag keys must be stable application identifiers.');
        $scope = $business ? ['scope_type' => 'business', 'scope_id' => $business->id] : ['scope_type' => 'global', 'scope_id' => 0];
        $flag = PlatformFeatureFlag::query()->firstOrNew(['key' => $key, ...$scope]);
        $before = $flag->exists ? ['enabled' => $flag->enabled] : [];
        $flag->fill(['enabled' => $enabled, 'description' => $description, 'reason' => $reason, 'updated_by_user_id' => $actor->id])->save();
        $this->audit->write('platform.feature_flag.changed', $business, $actor, $flag, $reason, $before, ['key' => $key, 'enabled' => $enabled, ...$scope], source: 'platform');

        return $flag->fresh();
    }
}
