<?php

namespace App\Filament\Resources\Roles\Pages;

use App\Domain\PlatformAccess\Services\MembershipAccessManager;
use App\Filament\Resources\Roles\RoleResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Facades\Filament;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class EditRole extends EditRecord
{
    protected static string $resource = RoleResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['_admin_permissions'] = $this->getRecord()->permissions()->pluck('name')->all();

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        abort_unless(RoleResource::canEdit($record), 403);

        return DB::transaction(fn () => app(MembershipAccessManager::class)->defineCustomRole(
            $record->business, $record->name, $data['_admin_permissions'], Filament::auth()->user(), $data['_admin_reason'],
        ));
    }

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
