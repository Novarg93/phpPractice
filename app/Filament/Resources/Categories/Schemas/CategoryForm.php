<?php

namespace App\Filament\Resources\Categories\Schemas;

use Filament\Schemas\Schema;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\FileUpload;
use Illuminate\Support\Str;

class CategoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Select::make('game_id')
                    ->relationship('game','name')
                    ->searchable()->preload()->required(),

                TextInput::make('name')
                    ->required()
                    ->maxLength(255)
                    ->live(debounce: 300)
                    ->afterStateUpdated(fn ($state, callable $set) =>
                        $set('slug', Str::slug((string) $state))
                    ),

                TextInput::make('slug')
                    ->required()
                    ->maxLength(120)
                    ->unique(ignoreRecord: true), // (при желании сузить уникальность до game_id — допилим)

                TextInput::make('type')
                    ->datalist(['items','currency','leveling'])
                    ->required(),

                Textarea::make('description')->columnSpanFull(),

                FileUpload::make('image')
                    ->label('Image')
                    ->disk('public')
                    ->directory('categories')
                    ->visibility('public')
                    ->image()
                    ->nullable(),
            ]);
    }
}