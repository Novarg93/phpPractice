<?php


namespace App\Filament\Resources\PromoCodes\Tables;

namespace App\Filament\Resources\PromoCodes\Tables;


use Filament\Tables\Table;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;

use Filament\Actions\ViewAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;

class PromoCodesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->searchable()
                    ->sortable(),

                // BadgeColumn → TextColumn + badge()
                TextColumn::make('type')
                    ->badge()
                    ->color('primary')
                    ->formatStateUsing(fn ($state) => strtoupper((string) $state))
                    ->sortable(),

                TextColumn::make('value_percent')
                    ->label('%')
                    ->toggleable()
                    ->sortable(),

                TextColumn::make('value_cents')
                    ->label('Amount, ¢')
                    ->toggleable()
                    ->sortable(),

                TextColumn::make('uses_count')
                    ->label('Used')
                    ->sortable(),

                IconColumn::make('is_active')
                    ->boolean()
                    ->sortable(),

                TextColumn::make('ends_at')
                    ->label('Ends')
                    ->dateTime()
                    ->since()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active'),
            ])
            // было ->actions(...) (deprecated)
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            // было ->bulkActions(...) (deprecated)
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}