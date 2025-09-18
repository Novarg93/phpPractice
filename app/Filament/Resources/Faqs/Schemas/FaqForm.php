<?php

namespace App\Filament\Resources\Faqs\Schemas;

use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section as UiSection;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Select;

class FaqForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextInput::make('order')
                    ->numeric()
                    ->minValue(0)
                    ->default(0)
                    ->required(),

                Toggle::make('is_active')
                    ->label('Published')
                    ->default(true),

                TextInput::make('question')
                    ->label('Question')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),

                RichEditor::make('answer')
                    ->label('Answer')
                    ->required()
                    ->columnSpanFull(),

                UiSection::make('Link to page (optional)')
                    ->schema([
                        Select::make('page_id')
                            ->relationship('page', 'name')
                            ->searchable()
                            ->preload()
                            ->native(false)
                            ->nullable()
                            ->helperText('Оставь пустым, если это глобальные FAQ'),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}