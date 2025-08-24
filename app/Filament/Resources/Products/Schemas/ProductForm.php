<?php

namespace App\Filament\Resources\Products\Schemas;

use Filament\Schemas\Schema;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\FileUpload;
use Illuminate\Support\Str;
use App\Models\Category;

class ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Select::make('category_id') // главная категория
                    ->relationship('category', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),

                Select::make('categories')
                    ->relationship('categories', 'name')
                    ->multiple()
                    ->preload()
                    ->searchable()
                    ->options(function (callable $get) {
                        $mainId = $get('category_id');

                        return Category::query()
                            ->when($mainId, fn($q) => $q->where('id', '!=', $mainId))
                            ->pluck('name', 'id');
                    })
                    ->helperText('Выберите дополнительные категории, кроме основной'),

                TextInput::make('name')
                    ->required()->maxLength(255)
                    ->live(debounce: 300)
                    ->afterStateUpdated(fn ($state, callable $set) =>
                        $set('slug', Str::slug((string) $state))
                    ),

                TextInput::make('slug')
                    ->required()->maxLength(120)
                    ->unique(ignoreRecord: true),

                TextInput::make('sku')->maxLength(64),

                TextInput::make('price_cents')
                    ->label('Price (cents)')
                    ->numeric()->minValue(0)->required(),

                Toggle::make('is_active')->default(true),

                Toggle::make('track_inventory')->default(false),

                TextInput::make('stock')
                    ->numeric()->minValue(0)->nullable()
                    ->disabled(fn ($get) => $get('track_inventory') === false),

                FileUpload::make('image')
                    ->label('Image')
                    ->disk('public')
                    ->directory('products')
                    ->visibility('public')
                    ->image()
                    ->nullable()
                    ->columnSpanFull(),

                Textarea::make('short')->rows(2)->columnSpanFull(),
                Textarea::make('description')->rows(6)->columnSpanFull(),
            ]);
    }
}