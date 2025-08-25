<?php

namespace App\Filament\Resources\Products\Schemas;

use Filament\Schemas\Schema;

// 👉 ЯВНО алиасим формы-компоненты (чтобы не путаться со Schemas-компонентами)
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Schemas\Components\Section as UiSection;

use Illuminate\Support\Str;
use App\Models\Category;

class ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Select::make('category_id')
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
                    ->afterStateUpdated(
                        fn($state, callable $set) =>
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
                    ->disabled(fn($get) => $get('track_inventory') === false),

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

                // ⬇️ Секция опций — используем алиас FormsSection
                UiSection::make('Options')
                    ->columnSpan(['lg' => 2])
                    ->description('Группы опций и варианты с аддитивной ценой')
                    ->schema([
                        Repeater::make('optionGroups')
                            ->relationship()
                            ->orderColumn('position')
                            ->defaultItems(0)
                            ->collapsed()
                            ->schema([
                                // === Верхний блок группы ===
                                // Делаем сетку на 12 колонок и задаём доли
                                // title: 6, type: 4, required: 2
                                TextInput::make('title')
                                    ->label('Group title')
                                    ->required()
                                    ->columnSpan(6),

                                Select::make('type')
                                    ->label('Type')
                                    ->options([
                                        \App\Models\OptionGroup::TYPE_RADIO    => 'DefaultRadiobuttonAdditive',
                                        \App\Models\OptionGroup::TYPE_CHECKBOX => 'DefaultCheckboxAdditive',
                                    ])
                                    ->native(false)
                                    ->required()
                                    ->columnSpan(4),

                                Toggle::make('is_required')
                                    ->label('Required')
                                    ->inline(false)
                                    ->columnSpan(2),

                                // === Список значений ===
                                Repeater::make('values')
                                    ->relationship()
                                    ->orderColumn('position')
                                    ->defaultItems(0)
                                    ->collapsed()
                                    ->columns(12)
                                    ->schema([
                                        TextInput::make('title')
                                            ->label('Option title')
                                            ->required()
                                            ->columnSpan(6),

                                        TextInput::make('price_delta_cents')
                                            ->label('cents')
                                            ->numeric()
                                            ->default(0)
                                            ->hint('additive')
                                            ->columnSpan(3),

                                        Toggle::make('is_active')
                                            ->label('Active')
                                            ->default(true)
                                            ->inline(false)
                                            ->columnSpan(1),

                                        Toggle::make('is_default')
                                            ->label('Default')
                                            ->default(false)
                                            ->inline(false)
                                            ->columnSpan(2),
                                    ])
                                    ->columnSpanFull()
                            ])
                            // включаем 12-колоночную сетку и для верхнего блока группы
                            ->columns(12),
                    ]),
            ]);
    }
}
