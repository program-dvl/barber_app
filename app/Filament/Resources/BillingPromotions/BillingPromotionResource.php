<?php

namespace App\Filament\Resources\BillingPromotions;

use App\Domain\Billing\Models\BillingPromotion;
use App\Filament\Resources\Concerns\AuthorizesPlatformAdministrator;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class BillingPromotionResource extends Resource
{
    use AuthorizesPlatformAdministrator;

    protected static ?string $model = BillingPromotion::class;

    protected static ?string $navigationLabel = 'Discounts & coupons';

    protected static ?string $modelLabel = 'offer';

    protected static ?string $pluralModelLabel = 'Discounts & coupons';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-ticket';

    protected static string|\UnitEnum|null $navigationGroup = 'Payments';

    protected static ?int $navigationSort = 1;

    public static function canCreate(): bool
    {
        return static::canAccess();
    }

    public static function canEdit(Model $record): bool
    {
        return static::canAccess();
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Offer details')
                ->description('Offers reduce the first subscription payment, before tax. Renewals use the regular price. Turning an offer off stops new checkouts; already opened checkouts keep their reviewed price. Existing paid subscriptions stay unchanged.')
                ->schema([
                    TextInput::make('name')->label('Offer name')->required()->maxLength(100)->helperText('Customers see this name with their savings.'),
                    TextInput::make('percentage')->label('Discount')->suffix('%')->numeric()->integer()->minValue(1)->maxValue(90)->required(),
                    Toggle::make('is_active')->label('Offer enabled')->helperText('Dates below also control when customers can use it.'),
                    Toggle::make('_coupon')->label('Require a coupon code')->live()->dehydrated(false)
                        ->afterStateHydrated(fn ($component, $record) => $component->state(filled($record?->code)))
                        ->afterStateUpdated(function ($state, $set): void {
                            if (! $state) {
                                $set('code', null);
                            }
                        }),
                    TextInput::make('code')->label('Coupon code')->visible(fn ($get) => (bool) $get('_coupon'))->required(fn ($get) => (bool) $get('_coupon'))->dehydrated(true)
                        ->suffixAction(Action::make('generateCode')->label('Generate code')->button()->icon('heroicon-o-arrow-path')->action(fn ($set) => $set('code', 'WELCOME-'.Str::upper(Str::random(8)))))
                        ->mutateStateForValidationUsing(fn ($state) => filled($state) ? Str::upper(trim($state)) : null)
                        ->dehydrateStateUsing(fn ($state) => filled($state) ? Str::upper(trim($state)) : null)
                        ->maxLength(40)->unique(ignoreRecord: true)->regex('/^[a-zA-Z0-9][a-zA-Z0-9-]{2,39}$/D')
                        ->helperText('Type your own code or select Generate code. Share the saved code with customers; codes are not case-sensitive. Changing a saved code stops the old code for new subscription reviews.'),
                    Toggle::make('can_combine')->label('Combine this coupon with the seasonal offer')
                        ->visible(fn ($get) => (bool) $get('_coupon'))->helperText('For coupons only. Off: this coupon replaces the seasonal offer. On: apply it to the reduced price (20% + 10% saves 28%).'),
                    DateTimePicker::make('starts_at')->label('Starts at (UTC)')->timezone('UTC')->seconds(false)->helperText('Leave empty to start immediately.'),
                    DateTimePicker::make('ends_at')->label('Ends at (UTC)')->timezone('UTC')->seconds(false)->rules(fn ($get) => filled($get('starts_at')) ? ['after:starts_at'] : [])->helperText('Leave empty for no end date.'),
                ])->columns(2),
        ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->searchable()->description(fn ($record) => $record->code ? 'Coupon · '.$record->code : 'Automatic seasonal offer'),
            TextColumn::make('percentage')->label('Saving')->suffix('%')->sortable(),
            IconColumn::make('is_active')->label('Enabled')->boolean(),
            IconColumn::make('can_combine')->label('Combines')->boolean(),
            TextColumn::make('starts_at')->label('Starts (UTC)')->dateTime()->timezone('UTC')->placeholder('Immediately'),
            TextColumn::make('ends_at')->label('Ends (UTC)')->dateTime()->timezone('UTC')->placeholder('No end date'),
        ])->recordActions([EditAction::make()])->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListBillingPromotions::route('/'), 'create' => Pages\CreateBillingPromotion::route('/create'), 'edit' => Pages\EditBillingPromotion::route('/{record}/edit')];
    }
}
