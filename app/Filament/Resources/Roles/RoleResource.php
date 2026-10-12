<?php

namespace App\Filament\Resources\Roles;

use App\Domain\PlatformAccess\Enums\PermissionName;
use App\Domain\PlatformAccess\Enums\StarterRole;
use App\Domain\PlatformAccess\Models\BusinessRole;
use App\Filament\Resources\Concerns\ReadsAuthorizationCatalog;
use App\Filament\Resources\Roles\Pages\CreateRole;
use App\Filament\Resources\Roles\Pages\EditRole;
use App\Filament\Resources\Roles\Pages\ListRoles;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class RoleResource extends Resource
{
    use ReadsAuthorizationCatalog;

    protected static ?string $model = BusinessRole::class;

    protected static ?string $navigationLabel = 'Roles';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static string|\UnitEnum|null $navigationGroup = 'Users';

    protected static ?int $navigationSort = 1;

    public static function canCreate(): bool
    {
        return static::canAccess();
    }

    public static function canEdit(Model $record): bool
    {
        return static::canAccess() && StarterRole::tryFrom($record->name) === null;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Role Information')
                    ->description('Define role details and permissions')
                    ->schema([
                        Select::make('business_id')->relationship('business', 'name')->searchable()->required()
                            ->disabled(fn (string $operation) => $operation === 'edit')->dehydrated(),
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->regex('/^[a-z][a-z0-9_]*$/')
                            ->rules([Rule::notIn(array_column(StarterRole::cases(), 'value'))])
                            ->disabled(fn (string $operation) => $operation === 'edit')->dehydrated()
                            ->helperText('Custom role name within the selected business')
                            ->columnSpanFull(),

                        Select::make('_admin_permissions')->label('Permissions')->multiple()->required()
                            ->options(collect(PermissionName::cases())->mapWithKeys(fn ($permission) => [$permission->value => $permission->value])),
                        Textarea::make('_admin_reason')->label('Reason for this change')->required()->minLength(10)->maxLength(1000),
                    ])
                    ->columns(1)
                    ->collapsible(),
            ])
            ->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('business.name')->label('Business')->searchable(),
                TextColumn::make('permissions.name')->badge()->label('Permissions'),
                TextColumn::make('guard_name')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->sortable()
                    ->date(),
                TextColumn::make('updated_at')
                    ->sortable()
                    ->date(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRoles::route('/'),
            'create' => CreateRole::route('/create'),
            'edit' => EditRole::route('/{record}/edit'),
        ];
    }
}
