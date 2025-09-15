<?php

namespace App\Filament\Resources\Orders\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;

class RefundItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'refundItems';
    protected static ?string $title = 'Refund items';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->label('ID')->sortable(),
                TextColumn::make('refund_id')->label('Refund #')->sortable(),
                TextColumn::make('orderItem.product_name')->label('Item')->wrap(),
                TextColumn::make('qty')->label('Qty')->numeric(2)->sortable(),
                TextColumn::make('amount_cents')->label('Amount')->money('USD', divideBy: 100)->sortable(),
                TextColumn::make('created_at')->since()->sortable(),
            ])
            ->headerActions([])
            ->actions([])
            ->bulkActions([]);
    }
}