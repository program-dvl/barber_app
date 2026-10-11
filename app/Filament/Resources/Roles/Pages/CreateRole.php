<?php

namespace App\Filament\Resources\Roles\Pages;

use App\Domain\PlatformAccess\Models\Business;
use App\Domain\PlatformAccess\Services\MembershipAccessManager;
use App\Filament\Resources\Roles\RoleResource;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class CreateRole extends CreateRecord
{
    protected static string $resource = RoleResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        abort_unless(RoleResource::canCreate(), 403);

        return DB::transaction(fn () => app(MembershipAccessManager::class)->defineCustomRole(
            Business::query()->findOrFail($data['business_id']), $data['name'], $data['_admin_permissions'],
            Filament::auth()->user(), $data['_admin_reason'],
        ));
    }
}
