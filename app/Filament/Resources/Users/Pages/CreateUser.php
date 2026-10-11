<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use App\Support\Audit\AuditWriter;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        abort_unless(UserResource::canCreate(), 403);
        $reason = $data['_admin_reason'];
        unset($data['_admin_reason']);

        return DB::transaction(function () use ($data, $reason): Model {
            $user = User::query()->create($data);
            app(AuditWriter::class)->write('platform.user.created', target: $user, reason: $reason,
                after: $user->only(['name', 'email']), source: 'admin');

            return $user;
        });
    }
}
