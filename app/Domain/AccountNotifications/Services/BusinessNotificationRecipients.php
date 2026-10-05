<?php

namespace App\Domain\AccountNotifications\Services;

use App\Domain\PlatformAccess\Models\Business;
use App\Domain\PlatformAccess\Models\Membership;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class BusinessNotificationRecipients
{
    /** @return Collection<int, User> */
    public function owners(Business $business): Collection
    {
        $roleTable = config('permission.table_names.roles');
        $pivotTable = config('permission.table_names.model_has_roles');
        $modelKey = config('permission.column_names.model_morph_key');
        $businessKey = config('permission.column_names.team_foreign_key');

        $userIds = DB::table('memberships')
            ->join($pivotTable, function ($join) use ($pivotTable, $modelKey, $businessKey): void {
                $join->on("{$pivotTable}.{$modelKey}", '=', 'memberships.id')
                    ->on("{$pivotTable}.{$businessKey}", '=', 'memberships.business_id')
                    ->where("{$pivotTable}.model_type", Membership::class);
            })
            ->join($roleTable, "{$roleTable}.id", '=', "{$pivotTable}.role_id")
            ->where('memberships.business_id', $business->getKey())
            ->where('memberships.status', 'active')
            ->whereNull('memberships.revoked_at')
            ->where("{$roleTable}.name", 'owner')
            ->distinct()
            ->pluck('memberships.user_id');

        return User::query()->whereKey($userIds)->whereNotNull('email_verified_at')->get();
    }
}
