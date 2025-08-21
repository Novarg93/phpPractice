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

                FileUpload::make('image_url')
                    ->label('Image')
                    ->disk('public')           // ⬅️ сохраняем на диск public
                    ->directory('games')       // ⬅️ файлы пойдут в storage/app/public/games
                    ->visibility('public')     // ⬅️ будут доступны по /storage/...
                    ->image()
                    ->previewable(true)
                    ->openable()
                    ->downloadable()
                    ->acceptedFileTypes(['image/png', 'image/jpeg', 'image/webp'])
                    ->maxSize(4096)            // до 4 МБ
                    ->nullable(),
            ]);
    }
}
