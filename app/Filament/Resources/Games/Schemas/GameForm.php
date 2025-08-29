<?php

namespace App\Filament\Resources\Games\Schemas;

use Filament\Schemas\Schema;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\FileUpload;


class GameForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255)
                    ->live(debounce: 300)
                    ->afterStateUpdated(function ($state, callable $set) {
                        $set('slug', \Illuminate\Support\Str::slug((string) $state));
                    }),

                TextInput::make('slug')
                    ->required()
                    ->maxLength(120)
                    ->unique(ignoreRecord: true),

                Textarea::make('description')
                    ->columnSpanFull(),

                FileUpload::make('image')
                    ->label('Image')
                    ->disk('public')
                    ->directory('games')
                    ->visibility('public')
                    ->image()
                    ->previewable(true)
                    ->openable()
                    ->downloadable()
                    ->acceptedFileTypes(['image/png', 'image/jpeg', 'image/webp'])
                    ->maxSize(4096)
                    ->nullable(),
            ]);
    }
}
