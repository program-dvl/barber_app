<?php

namespace App\Filament\Resources\Users\RelationManagers;

use App\Domain\PlatformAccess\Enums\PlatformRole;
use App\Domain\PlatformAccess\Models\PlatformRoleAssignment;
use App\Domain\PlatformAccess\Services\PlatformRoleManager;
use App\Filament\Resources\Users\UserResource;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RoleRelationManager extends RelationManager
{
    protected static string $relationship = 'platformRoleAssignments';

    protected static ?string $title = 'Platform roles';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return UserResource::canAccess();
    }

    public function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('role')->formatStateUsing(fn (PlatformRole $state) => str($state->value)->headline()),
            TextColumn::make('reason')->wrap(),
            TextColumn::make('expires_at')->dateTime(),
            TextColumn::make('revoked_at')->dateTime(),
        ])->headerActions([
            Action::make('grantRole')->label('Grant platform role')
                ->schema([
                    Select::make('role')->options(collect(PlatformRole::cases())->mapWithKeys(fn ($role) => [$role->value => str($role->value)->headline()->toString()]))->required(),
                    DateTimePicker::make('expires_at')->after('now'),
                    Textarea::make('reason')->required()->minLength(10)->maxLength(1000),
                ])->action(function (array $data): void {
                    abort_unless(UserResource::canAccess(), 403);
                    $owner = $this->getOwnerRecord();
                    if ($owner->hasPlatformRole(PlatformRole::from($data['role']))) {
                        throw ValidationException::withMessages(['role' => 'This platform role is already active.']);
                    }
                    DB::transaction(fn () => app(PlatformRoleManager::class)->grant(
                        $owner, PlatformRole::from($data['role']), Filament::auth()->user(), $data['reason'],
                        filled($data['expires_at'] ?? null) ? Carbon::parse($data['expires_at']) : null,
                    ));
                }),
        ])->recordActions([
            Action::make('revokeRole')->label('Revoke role')->color('danger')
                ->visible(fn (PlatformRoleAssignment $record) => ! $record->revoked_at && $record->user_id !== Filament::auth()->id())
                ->schema([Textarea::make('reason')->required()->minLength(10)->maxLength(1000)])
                ->requiresConfirmation()
                ->action(function (PlatformRoleAssignment $record, array $data): void {
                    abort_unless(UserResource::canAccess(), 403);
                    app(PlatformRoleManager::class)->revoke($record, Filament::auth()->user(), $data['reason']);
                }),
        ]);
    }
}
