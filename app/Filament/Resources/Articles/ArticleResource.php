<?php

namespace App\Filament\Resources\Articles;

use App\Filament\Resources\Articles\Pages\CreateArticle;
use App\Filament\Resources\Articles\Pages\EditArticle;
use App\Filament\Resources\Articles\Pages\ListArticles;
use App\Models\Article;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ArticleResource extends Resource
{
    protected static ?string $model = Article::class;

    protected static ?string $modelLabel = 'article';

    protected static ?string $pluralModelLabel = 'Articles';

    protected static bool $hasTitleCaseModelLabel = false;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static string|\UnitEnum|null $navigationGroup = 'Content';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Article')
                    ->schema([
                        TextInput::make('title')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->columnSpanFull(),

                        RichEditor::make('content')
                            ->fileAttachmentsDisk(config('filesystems.default'))
                            ->fileAttachmentsDirectory('article')
                            ->fileAttachmentsVisibility('public')
                            ->required()
                            ->columnSpanFull()
                            ->toolbarButtons([
                                ['bold', 'italic', 'underline', 'strike', 'link'],
                                ['h2', 'h3', 'alignStart', 'alignCenter', 'alignEnd'],
                                ['blockquote', 'codeBlock', 'bulletList', 'orderedList'],
                                ['table', 'attachFiles'],
                                ['undo', 'redo'],
                            ]),

                        FileUpload::make('thumbnail')
                            ->label('Featured image')
                            ->disk(config('filesystems.default'))
                            ->directory('articles')
                            ->visibility('public')
                            ->image()
                            ->imageEditor()
                            ->imageEditorAspectRatios([
                                '16:9',
                                '4:3',
                                '1:1',
                            ])
                            ->required()
                            ->columnSpanFull(),

                        Toggle::make('active')
                            ->label('Active')
                            ->default(true)
                            ->helperText('Public visibility also requires a complete editorial review.'),
                    ])
                    ->columns(2)
                    ->collapsible(),

                Section::make('Search appearance')
                    ->schema([
                        TextInput::make('seo_title')
                            ->label('Search title')
                            ->maxLength(60)
                            ->helperText('Recommended: 50-60 characters')
                            ->columnSpanFull(),

                        TextInput::make('seo_description')
                            ->label('Search description')
                            ->maxLength(160)
                            ->helperText('Recommended: 120-160 characters')
                            ->columnSpanFull(),

                        TextInput::make('seo_keywords')
                            ->label('Keywords')
                            ->helperText('Comma-separated keywords for this article')
                            ->columnSpanFull(),
                    ])
                    ->columns(1)
                    ->collapsible()
                    ->collapsed(),
            ])
            ->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->searchable(),
                TextColumn::make('slug'),
                IconColumn::make('active')
                    ->sortable()
                    ->boolean(),
                TextColumn::make('created_at'),
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
            ])->defaultSort('created_at', 'desc');
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
            'index' => ListArticles::route('/'),
            'create' => CreateArticle::route('/create'),
            'edit' => EditArticle::route('/{record}/edit'),
        ];
    }
}
