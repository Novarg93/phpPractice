<?php

namespace App\Filament\Resources\ContactMessages\Schemas;

use Filament\Schemas\Schema;
use Filament\Schemas\Components\Grid as UiGrid;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\KeyValue;

final class ContactMessageForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->schema([
            UiGrid::make(2)->schema([
                TextInput::make('first_name')
                    ->label('First name')
                    ->disabled(),

                TextInput::make('last_name')
                    ->label('Last name')
                    ->disabled(),

                TextInput::make('email')
                    ->label('Email')
                    ->disabled(),

                Select::make('status')
                    ->label('Status')
                    ->options([
                        'new'         => 'New',
                        'in_progress' => 'In progress',
                        'done'        => 'Done',
                    ]),

                Textarea::make('message')
                    ->label('Message')
                    ->disabled()
                    ->columnSpan(2),
            ]),

            KeyValue::make('meta')
                ->label('Meta')
                ->disabled()
                ->columnSpanFull(),
        ]);
    }
}