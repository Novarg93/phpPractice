<?php

namespace App\Filament\Resources\PromoCodes\Schemas;

use Filament\Schemas\Schema;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\DateTimePicker;
use Filament\Schemas\Components\Section as UiSection;
use Filament\Schemas\Components\Grid as UiGrid;

class PromoCodeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                UiSection::make('General')->schema([
                    TextInput::make('code')
                        ->required()
                        ->maxLength(64)
                        ->unique(ignoreRecord: true)
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn($state, $set) => $set('code', strtoupper(trim((string)$state))))
                        ->helperText('Будет сохранён в UPPERCASE'),

                    Select::make('type')
                        ->options(['percent' => 'Percent', 'amount' => 'Amount'])
                        ->native(false)
                        ->required()
                        ->default('percent'),

                    TextInput::make('value_percent')
                        ->label('Value (%)')
                        ->numeric()->minValue(1)->maxValue(100)
                        ->visible(fn($get) => ($get('type') ?? 'percent') === 'percent')
                        ->required(fn($get) => ($get('type') ?? 'percent') === 'percent'),

                    TextInput::make('value_cents')
                        ->label('Value (cents)')
                        ->numeric()->minValue(1)
                        ->visible(fn($get) => ($get('type') ?? 'percent') === 'amount')
                        ->required(fn($get) => ($get('type') ?? 'percent') === 'amount'),

                    Toggle::make('is_active')->default(true)->inline(false),
                ])->columns(2)->columnSpan(2),

                UiGrid::make(12)->schema([
                    TextInput::make('min_order_cents')->label('Min order (cents)')->numeric()->minValue(0)->columnSpan(4),
                    TextInput::make('max_discount_cents')->label('Max discount (cents)')->numeric()->minValue(0)->columnSpan(4),
                    TextInput::make('max_uses')->numeric()->minValue(1)->nullable()->columnSpan(2),
                    TextInput::make('per_user_max_uses')->numeric()->minValue(1)->nullable()->columnSpan(2),
                ])->columnSpan(2),

                UiGrid::make(12)->schema([
                    DateTimePicker::make('starts_at')->nullable()->columnSpan(6),
                    DateTimePicker::make('ends_at')->nullable()->columnSpan(6),
                ])->columnSpan(2),
            ]);
    }
}