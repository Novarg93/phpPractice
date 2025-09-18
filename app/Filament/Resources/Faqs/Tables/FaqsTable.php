<?php

namespace App\Filament\Resources\Faqs\Tables;

use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Actions\EditAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;

class FaqsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('question')
                    ->label('Question')
                    ->searchable()
                    ->limit(60),

                TextColumn::make('page.name')
                    ->label('Page')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('order')
                    ->numeric()
                    ->sortable(),

                ToggleColumn::make('is_active')
                    ->label('Published'),

                TextColumn::make('updated_at')
                    ->since()
                    ->sortable(),
            ])
            ->defaultSort('order')
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}