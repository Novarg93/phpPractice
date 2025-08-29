<?php

namespace App\Filament\Resources\Products\Schemas;

use Filament\Schemas\Schema;

// формы-компоненты
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Schemas\Components\Section as UiSection;
use Illuminate\Support\Facades\Storage;
use Filament\Forms\Get;
use Illuminate\Support\Str;
use App\Models\Category;
use App\Models\OptionGroup; // 👈 удобно заимпортить

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
                    ->required()
                    ->maxLength(255)
                    ->live(debounce: 300)
                    ->afterStateUpdated(fn($state, callable $set) => $set('slug', Str::slug((string) $state))),

                TextInput::make('slug')
                    ->required()
                    ->maxLength(120)
                    ->unique(ignoreRecord: true),

                TextInput::make('sku')->maxLength(64),

                TextInput::make('price_cents')
                    ->label('Price (cents)')
                    ->numeric()
                    ->minValue(0)
                    ->required(),

                Toggle::make('is_active')->default(true),
                Toggle::make('track_inventory')
                    ->default(false)
                    ->live(),

                TextInput::make('stock')
                    ->numeric()
                    ->minValue(0)
                    ->nullable()
                    ->disabled(fn(callable $get): bool => ! $get('track_inventory')),

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

                UiSection::make('Options')
                    ->columnSpan(['lg' => 2])
                    ->description('Группы опций и варианты с аддитивной ценой')
                    ->schema([
                        Repeater::make('optionGroups')
                            ->relationship()
                            ->orderColumn('position')
                            ->defaultItems(0)
                            ->collapsed()
                            ->columns(12) // сетка для верхнего блока группы
                            ->schema([
                                TextInput::make('title')
                                    ->label('Group title')
                                    ->required()
                                    ->columnSpan(6),

                                Select::make('type')
                                    ->label('Type')
                                    ->options([
                                        OptionGroup::TYPE_RADIO    => 'DefaultRadiobuttonAdditive',
                                        OptionGroup::TYPE_CHECKBOX => 'DefaultCheckboxAdditive',
                                        OptionGroup::TYPE_SLIDER   => 'QuantitySlider', // 👈 новый тип
                                    ])
                                    ->native(false)
                                    ->required()
                                    ->live()
                                    ->columnSpan(4),

                                Toggle::make('is_required')
                                    ->label('Required')
                                    ->inline(false)
                                    ->columnSpan(2),

                                Toggle::make('multiply_by_qty')
                                    ->label('Multiply by quantity')
                                    ->helperText('Если включено — надбавка опции умножается на qty. Если выключено — добавляется один раз за заказ.')
                                    ->visible(fn(callable $get) => $get('type') !== OptionGroup::TYPE_SLIDER)
                                    ->default(false)
                                    ->columnSpan(4),

                                // ===== Поля слайдера (только для quantity_slider) =====
                                TextInput::make('slider_min')
                                    ->label('Min')
                                    ->numeric()->minValue(1)
                                    ->required(fn(callable $get) => $get('type') === \App\Models\OptionGroup::TYPE_SLIDER)
                                    ->visible(fn(callable $get) => $get('type') === \App\Models\OptionGroup::TYPE_SLIDER),

                                TextInput::make('slider_max')
                                    ->label('Max')
                                    ->numeric()->minValue(1)
                                    ->required(fn(callable $get) => $get('type') === \App\Models\OptionGroup::TYPE_SLIDER)
                                    ->visible(fn(callable $get) => $get('type') === \App\Models\OptionGroup::TYPE_SLIDER),

                                TextInput::make('slider_step')
                                    ->label('Step')
                                    ->numeric()->minValue(1)
                                    ->required(fn(callable $get) => $get('type') === \App\Models\OptionGroup::TYPE_SLIDER)
                                    ->visible(fn(callable $get) => $get('type') === \App\Models\OptionGroup::TYPE_SLIDER),

                                TextInput::make('slider_default')
                                    ->label('Default')
                                    ->numeric()->minValue(1)
                                    ->required(fn(callable $get) => $get('type') === \App\Models\OptionGroup::TYPE_SLIDER)
                                    ->visible(fn(callable $get) => $get('type') === \App\Models\OptionGroup::TYPE_SLIDER),

                                Repeater::make('values')
                                    ->relationship()
                                    ->orderColumn('position')
                                    ->defaultItems(0)
                                    ->collapsed()
                                    ->columns(12)
                                    ->visible(fn(callable $get) => $get('type') !== \App\Models\OptionGroup::TYPE_SLIDER) // 👈
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
                                    ->columnSpanFull(),
                            ]),
                    ]),
            ]);
    }
}
