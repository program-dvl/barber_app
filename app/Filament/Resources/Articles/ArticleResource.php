<?php

namespace App\Filament\Resources\Articles;

use App\Filament\Resources\Articles\Pages\CreateArticle;
use App\Filament\Resources\Articles\Pages\EditArticle;
use App\Filament\Resources\Articles\Pages\ListArticles;
use App\Models\Article;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
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
                            ->fileAttachmentsDisk(config('articles.disk', 'public'))
                            ->fileAttachmentsDirectory('articles/content')
                            ->fileAttachmentsVisibility('public')
                            ->fileAttachmentsAcceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                            ->fileAttachmentsMaxSize(config('articles.image_max_kb'))
                            ->required()
                            ->maxLength(100000)
                            ->columnSpanFull()
                            ->toolbarButtons([
                                ['bold', 'italic', 'underline', 'strike', 'link'],
                                ['h2', 'h3'],
                                ['blockquote', 'codeBlock', 'bulletList', 'orderedList'],
                                ['table', 'attachFiles'],
                                ['undo', 'redo'],
                            ]),

                        FileUpload::make('thumbnail')
                            ->label('Featured image')
                            ->disk(config('articles.disk', 'public'))
                            ->directory('articles')
                            ->visibility('public')
                            ->image()
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                            ->maxSize(config('articles.image_max_kb'))
                            ->preventFilePathTampering()
                            ->validationAttribute('featured image')
                            ->helperText('JPG, PNG or WebP, up to 5 MB. You can save a draft before adding an image.')
                            ->imageEditor()
                            ->imageEditorAspectRatios([
                                '16:9',
                                '4:3',
                                '1:1',
                            ])
                            ->required(fn (Get $get): bool => $get('status') === 'published')
                            ->columnSpanFull(),

                        Textarea::make('excerpt')
                            ->label('Article summary')
                            ->helperText('A short introduction for the blog listing.')
                            ->maxLength(320)
                            ->required(fn (Get $get): bool => $get('status') === 'published')
                            ->columnSpanFull(),

                        TextInput::make('topic')
                            ->label('Topic')
                            ->maxLength(64)
                            ->required(fn (Get $get): bool => $get('status') === 'published'),

                        Toggle::make('active')
                            ->label('Active')
                            ->default(true)
                            ->helperText('Turn off to hide this article. Drafts are always private.'),
                    ])
                    ->columns(2)
                    ->collapsible(),

                Section::make('Search appearance')
                    ->schema([
                        TextInput::make('seo_title')
                            ->label('Search title')
                            ->maxLength(60)
                            ->required(fn (Get $get): bool => $get('status') === 'published')
                            ->helperText('Recommended: 50-60 characters')
                            ->columnSpanFull(),

                        TextInput::make('seo_description')
                            ->label('Search description')
                            ->maxLength(160)
                            ->required(fn (Get $get): bool => $get('status') === 'published')
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

                Section::make('Publication')
                    ->description('Save as a draft while you write. Publish after checking the content, image and search details.')
                    ->schema([
                        Select::make('status')
                            ->label('Publication status')
                            ->options(['draft' => 'Draft', 'published' => 'Published or scheduled'])
                            ->default('draft')->required()->live()->native(),
                        TextInput::make('content_owner')
                            ->label('Reviewed by')
                            ->maxLength(255)
                            ->required(fn (Get $get): bool => $get('status') === 'published'),
                        DateTimePicker::make('reviewed_at')
                            ->label('Review completed at (UTC)')
                            ->timezone('UTC')->seconds(false)
                            ->required(fn (Get $get): bool => $get('status') === 'published')
                            ->beforeOrEqual('now'),
                        DateTimePicker::make('published_at')
                            ->label('Publish at (UTC)')
                            ->timezone('UTC')->seconds(false)
                            ->helperText('A future date schedules the article. It stays private until that time.')
                            ->required(fn (Get $get): bool => $get('status') === 'published'),
                    ])->columns(2),
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
                TextColumn::make('status')->label('Publication')->badge(),
                TextColumn::make('published_at')->label('Publish at')->dateTime(timezone: 'UTC'),
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
