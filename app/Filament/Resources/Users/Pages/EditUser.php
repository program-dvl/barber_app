<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Support\Audit\AuditWriter;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        abort_unless(UserResource::canEdit($record), 403);
        $reason = $data['_admin_reason'];
        unset($data['_admin_reason']);

        return DB::transaction(function () use ($record, $data, $reason): Model {
            $before = $record->only(['name', 'email', 'email_verified_at']);
            if (isset($data['email']) && $data['email'] !== $record->email) {
                $record->email_verified_at = null;
            }
            $record->fill($data)->save();
            app(AuditWriter::class)->write('platform.user.updated', target: $record, reason: $reason,
                before: $before, after: $record->only(['name', 'email', 'email_verified_at']),
                metadata: ['password_changed' => filled($data['password'] ?? null)], source: 'admin');

            return $record;
        });
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
