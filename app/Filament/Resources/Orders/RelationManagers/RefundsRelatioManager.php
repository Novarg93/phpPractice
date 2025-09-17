<?php

namespace App\Filament\Resources\Orders\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;

class RefundsRelationManager extends RelationManager
{
    protected static string $relationship = 'refunds';
    protected static ?string $title = 'Refunds';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                TextColumn::make('id')->label('ID')->sortable(),
                TextColumn::make('amount_cents')->label('Amount')->money('USD', divideBy: 100)->sortable(),
                TextColumn::make('status')->badge()->sortable(),
                TextColumn::make('event_type')->label('Event')->badge()->toggleable(), // 👈
                TextColumn::make('reason')->limit(60),
                TextColumn::make('creator.email')->label('By'),
                TextColumn::make('created_at')->since()->sortable(),
                TextColumn::make('provider')->label('Provider')->badge(),
                TextColumn::make('provider_id')->label('Provider ID')->limit(20),
            ])
            ->headerActions([])     // без create
            ->actions([])           // без edit/delete
            ->bulkActions([]);      // без bulk
    }
}
