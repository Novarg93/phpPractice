<?php

namespace App\Filament\Resources\Posts\Schemas;

use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section as UiSection;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\DateTimePicker;
use Illuminate\Support\Str;

class PostForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            UiSection::make('Main')
                ->columns(1)
                ->columnSpanFull()
                ->schema([
                    TextInput::make('title')
                        ->required()
                        ->maxLength(255)
                        ->live(onBlur: true)
                        ->afterStateUpdated(function ($state, callable $set) {
                            
                            $set('slug', Str::slug($state));
                        }),

                    TextInput::make('slug')
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->maxLength(255),

                    Textarea::make('excerpt')
                        ->rows(3)
                        ->columnSpanFull(),

                    RichEditor::make('content')
                        ->columnSpanFull(),
                ]),

            UiSection::make('Media & Publish')
                ->columns(1)
                ->columnSpanFull()
                ->schema([
                    FileUpload::make('cover_image')
                        ->image()
                        ->directory('posts')
                        ->disk('public'),

                    Select::make('status')
                        ->options([
                            'draft' => 'Draft',
                            'published' => 'Published',
                        ])
                        ->required()
                        ->default('draft'),

                    DateTimePicker::make('published_at')
                        ->native(false)
                        ->seconds(false)
                        ->hint('Set when status is Published'),
                ]),

            // Если храните автора:
            // Select::make('user_id')
            //     ->relationship('author', 'name')
            //     ->searchable()
            //     ->preload()
            //     ->default(auth()->id()),
        ]);
    }
}