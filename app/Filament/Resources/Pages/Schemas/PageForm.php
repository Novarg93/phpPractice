<?php

namespace App\Filament\Resources\Pages\Schemas;

use Filament\Schemas\Schema;
// формы v4
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\RichEditor;
// секция v4 берём из Schemas
use Filament\Schemas\Components\Section as UiSection;

class PageForm
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

                TextInput::make('name')
                    ->required()
                    ->maxLength(255),

                TextInput::make('code')
                    ->required()
                    ->maxLength(120)
                    ->unique(ignoreRecord: true)
                    ->helperText('Уникальный код: terms, privacy, refund'),

                RichEditor::make('text')
                    ->nullable()
                    ->columnSpanFull(),

                UiSection::make('SEO')->schema([
                    TextInput::make('seo_title')->maxLength(255),
                    TextInput::make('seo_description')->maxLength(255),
                    TextInput::make('seo_og_title')->maxLength(255),
                    TextInput::make('seo_og_description')->maxLength(255),
                    TextInput::make('seo_og_image')->label('OG image URL'),
                ])->columns(2),
            ]);
    }
}