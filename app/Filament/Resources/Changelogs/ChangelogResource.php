<?php

namespace App\Filament\Resources\Changelogs;

use App\Filament\Resources\Changelogs\Pages\CreateChangelog;
use App\Filament\Resources\Changelogs\Pages\EditChangelog;
use App\Filament\Resources\Changelogs\Pages\ListChangelogs;
use App\Models\Changelog;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ChangelogResource extends Resource
{
    protected static ?string $model = Changelog::class;

    protected static ?string $modelLabel = 'changelog entry';

    protected static ?string $pluralModelLabel = 'Changelogs';

    protected static bool $hasTitleCaseModelLabel = false;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static string|\UnitEnum|null $navigationGroup = 'Content';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Changelog entry')
                    ->schema([
                        TextInput::make('title')
                            ->maxLength(255)
                            ->required()
                            ->live(onBlur: true)
                            ->columnSpanFull(),

                        MarkdownEditor::make('description')
                            ->required()
                            ->columnSpanFull()
                            ->toolbarButtons([
                                ['bold', 'italic', 'strike', 'link'],
                                ['heading'],
                                ['blockquote', 'codeBlock', 'bulletList', 'orderedList'],
                                ['undo', 'redo'],
                            ])
                            ->helperText('Use the toolbar to format the update.'),

                        DateTimePicker::make('published_at')
                            ->label('Publication date and time')
                            ->required()
                            ->default(now())
                            ->native(false)
                            ->helperText('Shown as the publication date.')
                            ->columnSpan(1),

                        TextInput::make('tags')
                            ->maxLength(255)
                            ->placeholder('feature, bugfix, improvement')
                            ->helperText('Separate tags with commas.')
                            ->columnSpan(1),
                    ])
                    ->columns(2)
                    ->collapsible(),
            ])
            ->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->searchable(),
                TextColumn::make('published_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('tags')
                    ->searchable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
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
            ])
            ->defaultSort('published_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListChangelogs::route('/'),
            'create' => CreateChangelog::route('/create'),
            'edit' => EditChangelog::route('/{record}/edit'),
        ];
    }
}
