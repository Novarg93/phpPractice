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
use Filament\Forms\Components\Select as FSelect;
use Filament\Forms\Get;
use Filament\Schemas\Components\Fieldset;


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

                            ->mutateRelationshipDataBeforeCreateUsing(function (array $data) {
                                $type = $data['type'] ?? null;

                                if ($type === \App\Models\OptionGroup::TYPE_SELECTOR) {
                                    // 1) пробуем взять из selector_pricing_mode (UI)
                                    // 2) если вдруг его нет (редактирование старых записей) — fallback на pricing_mode из БД/стейта
                                    $pm = $data['selector_pricing_mode'] ?? $data['pricing_mode'] ?? null;
                                    $data['pricing_mode'] = in_array($pm, ['absolute', 'percent'], true) ? $pm : 'absolute';
                                } elseif ($type === \App\Models\OptionGroup::TYPE_RANGE) {
                                    $pm = $data['range_pricing_mode'] ?? $data['pricing_mode'] ?? null;
                                    $data['pricing_mode'] = in_array($pm, ['flat', 'tiered'], true) ? $pm : 'flat';
                                } else {
                                    // для остальных типов это поле нам не нужно, но если пришло — не даём мусору попасть
                                    if (!in_array(($data['pricing_mode'] ?? null), ['absolute', 'percent', 'flat', 'tiered', null], true)) {
                                        $data['pricing_mode'] = null;
                                    }
                                }

                                unset($data['selector_pricing_mode'], $data['range_pricing_mode']);
                                return $data;
                            })
                            ->mutateRelationshipDataBeforeSaveUsing(function (array $data) {
                                $type = $data['type'] ?? null;

                                if ($type === \App\Models\OptionGroup::TYPE_SELECTOR) {
                                    $pm = $data['selector_pricing_mode'] ?? $data['pricing_mode'] ?? null;
                                    $data['pricing_mode'] = in_array($pm, ['absolute', 'percent'], true) ? $pm : 'absolute';
                                } elseif ($type === \App\Models\OptionGroup::TYPE_RANGE) {
                                    $pm = $data['range_pricing_mode'] ?? $data['pricing_mode'] ?? null;
                                    $data['pricing_mode'] = in_array($pm, ['flat', 'tiered'], true) ? $pm : 'flat';
                                }

                                unset($data['selector_pricing_mode'], $data['range_pricing_mode']);
                                return $data;
                            })
                            ->schema([

                                // ── ШАПКА ГРУППЫ: на всю ширину ─────────────────────────────
                                UiGrid::make(12)->schema([
                                    TextInput::make('title')
                                        ->label('Group title')
                                        ->required()
                                        ->columnSpan(12),

                                    TextInput::make('code')
                                        ->label('Rare code')
                                        ->placeholder('class | slot | affix')
                                        ->datalist(['class', 'slot', 'affix'])
                                        ->maxLength(32)
                                        ->columnSpan(6)
                                        ->reactive(),

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
                                            \App\Models\OptionGroup::TYPE_BUNDLE => 'Bundle (currency mix)',
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
                                        ->required(fn($get) => $get('type') === OptionGroup::TYPE_SELECTOR)
                                        ->native(false)
                                        ->columnSpan(6),

                                    Select::make('selector_pricing_mode')
                                        ->label('Pricing')
                                        ->options([
                                            'absolute' => 'Absolute (+N cents)',
                                            'percent'  => 'Percent (+N%)',
                                        ])
                                        ->default('absolute')
                                        ->visible(fn($get) => $get('type') === \App\Models\OptionGroup::TYPE_SELECTOR)
                                        ->required(fn($get) => $get('type') === \App\Models\OptionGroup::TYPE_SELECTOR)
                                        ->reactive()
                                        ->live()
                                        ->afterStateHydrated(function ($state, $set, $get) {
                                            if ($get('type') === \App\Models\OptionGroup::TYPE_SELECTOR) {
                                                $pm = $get('pricing_mode');
                                                $set('selector_pricing_mode', in_array($pm, ['absolute', 'percent'], true) ? $pm : 'absolute');
                                            }
                                        })
                                        ->native(false)
                                        ->columnSpan(6),
                                    Select::make('ui_variant')
                                        ->label('UI variant')
                                        ->options([
                                            'list'     => 'List (radio / checkbox)',
                                            'dropdown' => 'Dropdown (single)',
                                        ])
                                        ->visible(fn($get) => $get('type') === OptionGroup::TYPE_SELECTOR)
                                        ->default('list')
                                        ->native(false)
                                        ->columnSpan(6),

                                    // 👇 ОСТАВЛЯЕМ РОВНО ОДИН toggle
                                    Toggle::make('multiply_by_qty')
                                        ->label('Multiply by quantity')
                                        ->helperText('Если включено — надбавка умножается на qty; если выключено — добавляется один раз на позицию.')
                                        ->visible(fn(callable $get) => ! in_array($get('type'), [OptionGroup::TYPE_SLIDER, OptionGroup::TYPE_RANGE], true))
                                        ->default(true) // ← включаем по умолчанию
                                        ->afterStateHydrated(function ($state, callable $set, callable $get) {
                                            // При создании (state === null) — включаем, но не перетираем сохранённое false
                                            if ($state === null && ! in_array($get('type'), [OptionGroup::TYPE_SLIDER, OptionGroup::TYPE_RANGE], true)) {
                                                $set('multiply_by_qty', true);
                                            }
                                        })
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

                                        Select::make('range_pricing_mode')
                                            ->label('Pricing mode')
                                            ->reactive()
                                            ->options([
                                                'flat'   => 'Flat per level',
                                                'tiered' => 'Tiered',
                                            ])
                                            ->default('flat')
                                            ->visible(fn($get) => $get('type') === \App\Models\OptionGroup::TYPE_RANGE)
                                            ->required(fn($get) => $get('type') === \App\Models\OptionGroup::TYPE_RANGE)
                                            ->reactive()
                                            ->live()
                                            ->afterStateHydrated(function ($state, $set, $get) {
                                                if ($get('type') === \App\Models\OptionGroup::TYPE_RANGE) {
                                                    $pm = $get('pricing_mode');
                                                    $set('range_pricing_mode', in_array($pm, ['flat', 'tiered'], true) ? $pm : 'flat');
                                                }
                                            })
                                            ->native(false)
                                            ->columnSpan(6),

                                        // FLAT
                                        TextInput::make('unit_price_cents')
                                            ->label('Unit price (cents)')
                                            ->numeric()
                                            ->minValue(0)
                                            ->visible(fn($get) => ($get('range_pricing_mode') ?? 'flat') === 'flat')
                                            ->columnSpan(4),



                                        // TIERED
                                        FRepeater::make('tiers_json')
                                            ->label('Tiers')
                                            ->visible(fn($get) => ($get('range_pricing_mode') ?? 'flat') === 'tiered')
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
                                            ->visible(fn($get) => ($get('range_pricing_mode') ?? 'flat') === 'tiered')
                                            ->default('sum_piecewise')
                                            ->native(false)
                                            ->columnSpan(6),

                                        TextInput::make('base_fee_cents')->label('Base fee (cents)')->numeric()->minValue(0)->nullable()->columnSpan(3),
                                        TextInput::make('max_span')->label('Max span')->numeric()->minValue(1)->nullable()->columnSpan(3),
                                    ]),

                                // ── Блок для BUNDLE: список «каких готовых товаров можно добавлять» ──
                                \Filament\Schemas\Components\Grid::make(12)
                                    ->visible(fn(callable $get) => $get('type') === \App\Models\OptionGroup::TYPE_BUNDLE)
                                    ->columnSpanFull()
                                    ->schema([
                                        \Filament\Forms\Components\Repeater::make('bundleItems')
                                            ->relationship()
                                            ->label('Bundle items (allowed products)')
                                            ->orderColumn('position')
                                            ->defaultItems(0)
                                            ->columns(12)
                                            ->schema([
                                                \Filament\Forms\Components\Select::make('product_id')
                                                    ->label('Product')
                                                    ->required()
                                                    ->searchable()
                                                    ->preload()
                                                    // отдаём все товары, без ограничений
                                                    ->options(
                                                        fn() => \App\Models\Product::query()
                                                            ->orderBy('name')
                                                            ->pluck('name', 'id')
                                                            ->all()
                                                    )
                                                    // чтобы корректно показывалиcь лейблы у уже сохранённых значений
                                                    ->getOptionLabelUsing(fn($value) => \App\Models\Product::find($value)?->name ?? "#{$value}")
                                                    ->columnSpan(6),

                                                \Filament\Forms\Components\TextInput::make('position')
                                                    ->numeric()->minValue(0)->default(0)->columnSpan(2),

                                                Fieldset::make('Qty overrides (optional)')
                                                    ->columns(4)
                                                    ->schema([
                                                        \Filament\Forms\Components\TextInput::make('qty_min')->numeric()->minValue(1)->label('Min'),
                                                        \Filament\Forms\Components\TextInput::make('qty_max')->numeric()->minValue(1)->label('Max'),
                                                        \Filament\Forms\Components\TextInput::make('qty_step')->numeric()->minValue(1)->label('Step'),
                                                        \Filament\Forms\Components\TextInput::make('qty_default')->numeric()->minValue(1)->label('Default'),
                                                    ])
                                                    ->columnSpan(12),
                                            ])
                                            ->columnSpan(12),
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

                                    // ⬇️ ВСЯ запись значений — только тут
                                    ->mutateRelationshipDataBeforeCreateUsing(function (array $data, callable $get) {
                                        $type = $get('../../type');
                                        if ($type !== OptionGroup::TYPE_SELECTOR) return $data; // legacy не трогаем

                                        $mode = $get('../../selector_pricing_mode') ?? 'absolute';
                                        if ($mode === 'percent') {
                                            $data['delta_percent']     = $data['delta_percent'] ?? null;
                                            $data['delta_cents']       = null;
                                            $data['price_delta_cents'] = null;
                                            $data['value_percent']     = $data['delta_percent'];
                                        } else {
                                            $data['delta_cents']       = $data['delta_cents'] ?? 0;
                                            $data['delta_percent']     = null;
                                            $data['value_percent']     = null;
                                        }
                                        return $data;
                                    })
                                    ->mutateRelationshipDataBeforeSaveUsing(function (array $data, callable $get) {
                                        $type = $get('../../type');
                                        if ($type !== OptionGroup::TYPE_SELECTOR) return $data; // legacy не трогаем

                                        $mode = $get('../../selector_pricing_mode') ?? 'absolute';
                                        if ($mode === 'percent') {
                                            $data['delta_cents']       = null;
                                            $data['price_delta_cents'] = null;
                                            $data['value_percent']     = $data['delta_percent'];
                                        } else {
                                            $data['delta_cents']       = $data['delta_cents'] ?? 0;
                                            $data['delta_percent']     = null;
                                            $data['value_percent']     = null;
                                        }
                                        return $data;
                                    })

                                    ->schema([
                                        TextInput::make('title')
                                            ->label('Option title')
                                            ->required()
                                            ->columnSpan(6),

                                        // ABSOLUTE
                                        TextInput::make('delta_cents')
                                            ->label('Value (cents)')
                                            ->numeric()
                                            ->minValue(0)
                                            ->default(0)
                                            ->visible(
                                                fn($get) =>
                                                $get('../../type') === OptionGroup::TYPE_SELECTOR
                                                    && ($get('../../selector_pricing_mode') ?? 'absolute') === 'absolute'
                                            )
                                            ->dehydrated(
                                                fn($get) => // 👈 дехидрируем только когда поле реально активно
                                                $get('../../type') === OptionGroup::TYPE_SELECTOR
                                                    && ($get('../../selector_pricing_mode') ?? 'absolute') === 'absolute'
                                            )
                                            ->columnSpan(3),

                                        // PERCENT
                                        TextInput::make('delta_percent')
                                            ->label('Value (%)')
                                            ->numeric()
                                            ->rule('decimal:0,3')
                                            ->default(null)
                                            ->visible(
                                                fn($get) =>
                                                $get('../../type') === OptionGroup::TYPE_SELECTOR
                                                    && ($get('../../selector_pricing_mode') ?? 'absolute') === 'percent'
                                            )
                                            ->dehydrated(
                                                fn($get) => // 👈 дехидрируем только в percent
                                                $get('../../type') === OptionGroup::TYPE_SELECTOR
                                                    && ($get('../../selector_pricing_mode') ?? 'absolute') === 'percent'
                                            )
                                            ->columnSpan(3),

                                        // ----- LEGACY additive (radio/checkbox) -----
                                        TextInput::make('price_delta_cents')
                                            ->label('Value (cents)')
                                            ->numeric()
                                            ->default(0)
                                            ->visible(fn(callable $get) => in_array(($get('../../type') ?? null), [
                                                OptionGroup::TYPE_RADIO,
                                                OptionGroup::TYPE_CHECKBOX,
                                            ], true))
                                            ->columnSpan(3),

                                        // ----- LEGACY percent (radio_percent/checkbox_percent) -----
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

                                        // ── ограничения ДЛЯ slot и affix: какие классы разрешены ──
                                        FSelect::make('allow_class_value_ids')
                                            ->label('Allowed classes')
                                            ->multiple()
                                            ->preload()
                                            ->searchable()
                                            ->reactive()
                                            ->options(function (callable $get) {
                                                // id родительской OptionGroup
                                                $groupId = $get('../../id');
                                                if (!$groupId) return [];

                                                // Узнаём product_id текущей группы
                                                $productId = \App\Models\OptionGroup::query()
                                                    ->whereKey($groupId)->value('product_id');
                                                if (!$productId) return [];

                                                // Ищем группу классов внутри того же продукта:
                                                $classGroup = \App\Models\OptionGroup::query()
                                                    ->where('product_id', $productId)
                                                    ->where(function ($q) {
                                                        $q->whereRaw('LOWER(code) = ?', ['class'])
                                                            ->orWhereRaw('LOWER(code) LIKE ?', ['%class%'])
                                                            ->orWhereRaw('LOWER(title) LIKE ?', ['%class%'])
                                                            ->orWhereRaw('LOWER(title) LIKE ?', ['%класс%']);
                                                    })
                                                    ->with(['values' => fn($q) => $q->select('id', 'option_group_id', 'title')->orderBy('position')])
                                                    ->first();

                                                return $classGroup
                                                    ? $classGroup->values->pluck('title', 'id')->all()
                                                    : [];
                                            })
                                            // Показываем у slot- и affix-групп:
                                            ->visible(fn(callable $get) => str_contains((string) strtolower($get('../../code') ?? ''), 'slot')
                                                || str_contains((string) strtolower($get('../../code') ?? ''), 'affix'))
                                            ->helperText(fn(callable $get) => $get('../../id') ? null : 'Сохраните группу (и продукт), затем вернитесь.')
                                            ->columnSpan(6),

                                        // ----- Allowed slots (ТОЛЬКО для AFFIX) -----
                                        FSelect::make('allow_slot_value_ids')
                                            ->label('Allowed slots')
                                            ->multiple()
                                            ->preload()
                                            ->searchable()
                                            ->reactive()
                                            ->options(function (callable $get) {
                                                $groupId = $get('../../id');
                                                if (!$groupId) return [];

                                                $productId = \App\Models\OptionGroup::query()
                                                    ->whereKey($groupId)->value('product_id');
                                                if (!$productId) return [];

                                                // Ищем slot-группу в этом же продукте
                                                $slotGroup = \App\Models\OptionGroup::query()
                                                    ->where('product_id', $productId)
                                                    ->where(function ($q) {
                                                        $q->whereRaw('LOWER(code) = ?', ['slot'])
                                                            ->orWhereRaw('LOWER(code) LIKE ?', ['%slot%'])
                                                            ->orWhereRaw('LOWER(title) LIKE ?', ['%slot%'])
                                                            ->orWhereRaw('LOWER(title) LIKE ?', ['%слот%'])
                                                            ->orWhereRaw('LOWER(title) LIKE ?', ['%предмет%']);
                                                    })
                                                    ->with(['values' => fn($q) => $q->select('id', 'option_group_id', 'title')->orderBy('position')])
                                                    ->first();

                                                return $slotGroup
                                                    ? $slotGroup->values->pluck('title', 'id')->all()
                                                    : [];
                                            })
                                            ->visible(fn(callable $get) => str_contains((string) strtolower($get('../../code') ?? ''), 'affix'))
                                            ->helperText(fn(callable $get) => $get('../../id') ? null : 'Сохраните группу (и продукт), затем вернитесь.')
                                            ->columnSpan(6),
                                    ])
                                    ->columnSpanFull(),
                            ])
                    ]),
            ]);
    }
}
