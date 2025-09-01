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
use Filament\Forms\Get;
use Illuminate\Support\Str;

use App\Models\OptionGroup;
use Filament\Schemas\Components\Grid as UiGrid;
use Filament\Forms\Components\Repeater as FRepeater;

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
                        return \App\Models\Category::query()
                            ->when($mainId, fn($q) => $q->where('id', '!=', $mainId))
                            ->pluck('name', 'id');
                    })
                    ->helperText('Выберите дополнительные категории, кроме основной')

                    // <<< ВАЖНО: кастомный sync, который всегда добавляет primary
                    ->saveRelationshipsUsing(function (\App\Models\Product $record, ?array $state) {
                        $ids = collect($state ?? []);

                        // Всегда добавляем основную категорию
                        if ($record->category_id) {
                            $ids = $ids->push($record->category_id);
                        }

                        // Сформируем атрибуты pivot с флагом is_primary
                        $sync = $ids->unique()->mapWithKeys(function ($id) use ($record) {
                            return [
                                (int) $id => ['is_primary' => (int)$id === (int)$record->category_id],
                            ];
                        })->all();

                        $record->categories()->sync($sync);
                    })

                    // (Опционально) чтобы при редактировании в мультиселекте не светилась “main”,
                    // убираем её из состояния (только для UI), но в БД она всё равно будет.
                    ->afterStateHydrated(function ($state, callable $set, ?\App\Models\Product $record) {
                        if (! $record) return;
                        if (! $state) return;
                        if (! $record->category_id) return;

                        $filtered = collect($state)->reject(fn($id) => (int)$id === (int)$record->category_id)->values();
                        $set('categories', $filtered->all());
                    }),


                TextInput::make('name')
                    ->required()
                    ->maxLength(255)
                    ->live(onBlur: true)
                    ->afterStateUpdated(function ($state, callable $set) {
                        $set('slug', \Illuminate\Support\Str::slug((string) $state));
                    }),


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

                TextInput::make('price_preview')                  // 👈 НОВОЕ
                    ->label('Price preview (text)')
                    ->placeholder('$1 per 1M gold')
                    ->helperText('Показывается в каталоге и на карточке товара, если заполнено')
                    ->maxLength(255)
                    ->columnSpan(1),

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
                            ->columns(12)
                            ->schema([

                                // ── ШАПКА ГРУППЫ: на всю ширину ─────────────────────────────
                                UiGrid::make(12)->schema([
                                    TextInput::make('title')
                                        ->label('Group title')
                                        ->required()
                                        ->columnSpan(12),

                                    Select::make('type')
                                        ->label('Type')
                                        ->options([
                                            OptionGroup::TYPE_RADIO            => 'DefaultRadiobuttonAdditive (+N)',
                                            OptionGroup::TYPE_CHECKBOX         => 'DefaultCheckboxAdditive (+N)',
                                            OptionGroup::TYPE_RADIO_PERCENT    => 'RadiobuttonPercent (+N%)',
                                            OptionGroup::TYPE_CHECKBOX_PERCENT => 'CheckboxPercent (+N%)',
                                            OptionGroup::TYPE_SLIDER           => 'QuantitySlider',
                                            OptionGroup::TYPE_RANGE            => 'DoubleRangeSlider',
                                            OptionGroup::TYPE_SELECTOR         => 'Selector (single / multi)',
                                        ])
                                        ->native(false)
                                        ->required()
                                        ->default(OptionGroup::TYPE_RADIO)
                                        ->live()
                                        ->columnSpan(12),

                                    Toggle::make('is_required')
                                        ->label('Required')
                                        ->inline(false)
                                        ->columnSpan(12),

                                    Select::make('selection_mode')
                                        ->label('Selection')
                                        ->options(['single' => 'Single', 'multi' => 'Multi'])
                                        ->visible(fn(callable $get) => $get('type') === OptionGroup::TYPE_SELECTOR)
                                        ->required()
                                        ->native(false)
                                        ->columnSpan(6),

                                    Select::make('pricing_mode')
                                        ->label('Pricing')
                                        ->options(['absolute' => 'Absolute (+N cents)', 'percent' => 'Percent (+N%)'])
                                        ->visible(fn(callable $get) => $get('type') === OptionGroup::TYPE_SELECTOR)
                                        ->required()
                                        ->live()
                                        ->native(false)
                                        ->columnSpan(6),

                                    // 👇 ОСТАВЛЯЕМ РОВНО ОДИН toggle
                                    Toggle::make('multiply_by_qty')
                                        ->label('Multiply by quantity')
                                        ->helperText('Если включено — надбавка умножается на qty; если выключено — добавляется один раз на позицию.')
                                        ->visible(fn(callable $get) => ! in_array($get('type'), [OptionGroup::TYPE_SLIDER, OptionGroup::TYPE_RANGE], true))
                                        ->default(false)
                                        ->columnSpan(12),
                                ])->columnSpanFull(),

                                // ── БЛОК НАСТРОЕК ДЛЯ double_range_slider: отдельным блоком, на всю ширину ──
                                UiGrid::make(12)
                                    ->visible(fn(callable $get) => $get('type') === OptionGroup::TYPE_RANGE)
                                    ->columnSpanFull()
                                    ->schema([
                                        TextInput::make('slider_min')->label('Min')->numeric()->minValue(1)->required()->columnSpan(3),
                                        TextInput::make('slider_max')->label('Max')->numeric()->minValue(1)->required()->columnSpan(3),
                                        TextInput::make('slider_step')->label('Step')->numeric()->minValue(1)->required()->columnSpan(3),

                                        TextInput::make('range_default_min')->label('Default min')->numeric()->nullable()->columnSpan(3),
                                        TextInput::make('range_default_max')->label('Default max')->numeric()->nullable()->columnSpan(3),

                                        Select::make('pricing_mode')
                                            ->label('Pricing mode')
                                            ->options(['flat' => 'Flat per level', 'tiered' => 'Tiered'])
                                            ->required()
                                            ->native(false)
                                            ->live()
                                            ->columnSpan(6),

                                        // FLAT
                                        TextInput::make('unit_price_cents')
                                            ->label('Unit price (cents)')
                                            ->numeric()
                                            ->minValue(0)
                                            ->visible(fn(callable $get) => $get('pricing_mode') === 'flat')
                                            ->columnSpan(4),



                                        // TIERED
                                        FRepeater::make('tiers_json')
                                            ->label('Tiers')
                                            ->visible(fn(callable $get) => $get('pricing_mode') === 'tiered')
                                            ->reactive()
                                            ->defaultItems(0)
                                            ->schema([
                                                TextInput::make('from')->numeric()->required()->label('From'),
                                                TextInput::make('to')->numeric()->required()->label('To'),
                                                TextInput::make('unit_price_cents')->numeric()->minValue(0)->required()->label('Unit (cents)'),
                                                TextInput::make('label')->label('Label')->maxLength(64)->nullable(),
                                                TextInput::make('min_block')->numeric()->minValue(1)->nullable(),
                                                TextInput::make('multiplier')->numeric()->nullable(),
                                                TextInput::make('cap_cents')->numeric()->minValue(0)->nullable(),
                                            ])
                                            ->columns(7)
                                            ->columnSpanFull(),

                                        Select::make('tier_combine_strategy')
                                            ->label('Combine strategy')
                                            ->options([
                                                'sum_piecewise'     => 'Sum (piecewise)',
                                                'highest_tier_only' => 'Apply highest tier to whole span',
                                                'weighted_average'  => 'Weighted average',
                                            ])
                                            ->visible(fn(callable $get) => $get('pricing_mode') === 'tiered')
                                            ->default('sum_piecewise')
                                            ->native(false)
                                            ->columnSpan(6),

                                        TextInput::make('base_fee_cents')->label('Base fee (cents)')->numeric()->minValue(0)->nullable()->columnSpan(3),
                                        TextInput::make('max_span')->label('Max span')->numeric()->minValue(1)->nullable()->columnSpan(3),
                                    ]),

                                // ── БЛОК НАСТРОЕК ДЛЯ quantity_slider: отдельным блоком, на всю ширину ─────
                                UiGrid::make(12)
                                    ->visible(fn(callable $get) => $get('type') === OptionGroup::TYPE_SLIDER)
                                    ->columnSpanFull()
                                    ->schema([
                                        TextInput::make('qty_min')->label('Min')->numeric()->minValue(1)->required()->columnSpan(3),
                                        TextInput::make('qty_max')->label('Max')->numeric()->minValue(1)->required()->columnSpan(3),
                                        TextInput::make('qty_step')->label('Step')->numeric()->minValue(1)->required()->columnSpan(3),
                                        TextInput::make('qty_default')->label('Default')->numeric()->minValue(1)->required()->columnSpan(3),
                                    ]),

                                // ── Values для radio/checkbox (на всю ширину) ────────────────────────────────
                                Repeater::make('values')
                                    ->relationship()
                                    ->orderColumn('position')
                                    ->defaultItems(0)
                                    ->collapsed()
                                    ->columns(12)
                                    ->visible(fn(callable $get) => ! in_array(($get('../../type') ?? $get('type')), [
                                        OptionGroup::TYPE_SLIDER,
                                        OptionGroup::TYPE_RANGE,
                                    ], true))
                                    ->schema([
                                        TextInput::make('title')
                                            ->label('Option title')
                                            ->required()
                                            ->columnSpan(6),


                                        // ----- ДЛЯ SELECTOR + ABSOLUTE -----
                                        TextInput::make('delta_cents')
                                            ->label('Value (cents)')
                                            ->numeric()
                                            ->minValue(0)
                                            ->default(0)
                                            ->visible(fn(callable $get) => $get('../../type') === OptionGroup::TYPE_SELECTOR
                                                && ($get('../../pricing_mode') ?? 'absolute') === 'absolute')
                                            ->columnSpan(3),

                                        // ----- ДЛЯ SELECTOR + PERCENT -----
                                        TextInput::make('delta_percent')
                                            ->label('Value (%)')
                                            ->numeric()
                                            ->rule('decimal:0,3')
                                            ->default(null)
                                            ->visible(fn(callable $get) => $get('../../type') === OptionGroup::TYPE_SELECTOR
                                                && ($get('../../pricing_mode') ?? 'absolute') === 'percent')
                                            ->columnSpan(3),

                                        // ----- LEGACY additive (прячетcя при selector) -----
                                        TextInput::make('price_delta_cents')
                                            ->label('Value (cents)')
                                            ->numeric()
                                            ->default(0)
                                            ->visible(fn(callable $get) => in_array(($get('../../type') ?? null), [
                                                OptionGroup::TYPE_RADIO,
                                                OptionGroup::TYPE_CHECKBOX,
                                            ], true))
                                            ->columnSpan(3),

                                        // ----- LEGACY percent (прячетcя при selector) -----
                                        TextInput::make('value_percent')
                                            ->label('Value (%)')
                                            ->numeric()
                                            ->rule('decimal:0,3')
                                            ->default(null)
                                            ->visible(fn(callable $get) => in_array(($get('../../type') ?? null), [
                                                OptionGroup::TYPE_RADIO_PERCENT,
                                                OptionGroup::TYPE_CHECKBOX_PERCENT,
                                            ], true))
                                            ->columnSpan(3),

                                        // Аддитив: +N в валюте
                                        TextInput::make('price_delta_cents')
                                            ->label('Value (cents)')
                                            ->numeric()
                                            ->default(0)
                                            ->columnSpan(3)
                                            ->visible(fn(callable $get) => in_array(($get('../../type') ?? $get('type')), [
                                                OptionGroup::TYPE_RADIO,
                                                OptionGroup::TYPE_CHECKBOX,
                                            ], true)),

                                        // Проценты: +N%
                                        TextInput::make('value_percent')
                                            ->label('Value (%)')
                                            ->numeric()
                                            ->rule('decimal:0,3')
                                            ->default(null)
                                            ->placeholder('e.g., 5 or 12.5')
                                            ->columnSpan(3)
                                            ->visible(fn(callable $get) => in_array(($get('../../type') ?? $get('type')), [
                                                OptionGroup::TYPE_RADIO_PERCENT,
                                                OptionGroup::TYPE_CHECKBOX_PERCENT,
                                            ], true)),

                                        Toggle::make('is_active')
                                            ->label('Active')
                                            ->default(true)
                                            ->inline(false)
                                            ->columnSpan(1),

                                        Toggle::make('is_default')
                                            ->label('Default')
                                            ->default(false)
                                            ->inline(false)
                                            ->columnSpan(2)
                                            ->visible(fn(callable $get) => in_array(($get('../../type') ?? $get('type')), [
                                                OptionGroup::TYPE_RADIO,
                                                OptionGroup::TYPE_RADIO_PERCENT,
                                            ], true)),
                                    ])
                                    ->columnSpanFull(),
                            ])
                    ]),
            ]);
    }
}
