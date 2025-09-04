<?php

namespace App\Filament\Resources\Users\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Table;
use Filament\Tables\Columns as TC;

final class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns(self::columns())
            ->defaultSort('id', 'asc')
            ->paginated([25, 50, 100])   // выбор размера страницы
            ->striped()                  // зебра для читабельности
            ->recordUrl(fn($record) => \App\Filament\Resources\Users\UserResource::getUrl('edit', ['record' => $record]))
            ->recordActions([
                EditAction::make(),
            ]);
    }

    public static function columns(): array
    {
        return [
            TC\TextColumn::make('id')->label('ID')->sortable(),
            TC\ImageColumn::make('avatar')->label('Avatar')->disk('public')->circular()->imageWidth(36)->imageHeight(36),
            TC\TextColumn::make('email')->label('Email')->searchable()->sortable()->alignCenter(),
            TC\TextColumn::make('role')->label('Role')->badge()->colors([
                'success' => 'admin',
                'warning' => 'support',
                'gray'    => 'user',
            ])->sortable(),

            // 👇 Новые столбцы
            TC\TextColumn::make('paid_orders_count')
                ->label('Orders')
                ->sortable()
                ->alignCenter()
                ->badge(),

            TC\TextColumn::make('paid_orders_total')
                ->money('USD', divideBy: 100)
                ->alignCenter()
        ];
    }
}
