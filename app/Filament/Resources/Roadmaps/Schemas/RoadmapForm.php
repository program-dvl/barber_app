<?php

namespace App\Filament\Resources\Roadmaps\Schemas;

use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class RoadmapForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Feature request')
                    ->schema([
                        Hidden::make('user_id')
                            ->default(fn () => auth()->id())
                            ->dehydrated()
                            ->required(),

                        TextInput::make('title')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),

                        Textarea::make('description')
                            ->required()
                            ->rows(4)
                            ->columnSpanFull(),

                        Select::make('status')
                            ->options([
                                'pending' => 'Pending',
                                'approved' => 'Approved',
                                'in_progress' => 'In progress',
                                'completed' => 'Completed',
                            ])
                            ->default('pending')
                            ->required()
                            ->columnSpan(1),

                        TextInput::make('votes_count')
                            ->label('Votes')
                            ->numeric()
                            ->default(0)
                            ->disabled()
                            ->dehydrated(false)
                            ->helperText('Calculated from user votes.')
                            ->columnSpan(1),
                    ])
                    ->columns(2)
                    ->collapsible(),
            ])
            ->columns(1);
    }
}
