<?php

namespace App\Filament\Resources\Orders;


use App\Filament\Resources\Orders\RelationManagers\OrderItemsRelationManager;
use App\Filament\Resources\Orders\RelationManagers\ChangeLogsRelationManager;
use App\Filament\Resources\Orders\Tables\OrdersTable;
use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Filament\Resources\Orders\RelationManagers\RefundsRelationManager;
use App\Filament\Resources\Orders\RelationManagers\RefundItemsRelationManager;
use App\Models\Order;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;

class OrdersResource extends Resource
{
    protected static ?string $model = Order::class;
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedReceiptRefund; // или другой

    public static function table(\Filament\Tables\Table $table): \Filament\Tables\Table
    {
        return OrdersTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index'  => \App\Filament\Resources\Orders\Pages\ListOrders::route('/'),
            'view'   => ViewOrder::route('/{record}'),
            'create' => \App\Filament\Resources\Orders\Pages\CreateOrders::route('/create'),
            'edit'   => \App\Filament\Resources\Orders\Pages\EditOrders::route('/{record}/edit'),
        ];
    }

    public static function getRelations(): array
    {
        return [
            RefundsRelationManager::class,
            RefundItemsRelationManager::class,
            OrderItemsRelationManager::class,
            ChangeLogsRelationManager::class,
        ];
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
{
    return parent::getEloquentQuery()
        ->with([
            'user',
            'refunds.items.orderItem',  // рефанды + их позиции
            'changeLogs.actor',         // история + кто сделал
        ])
        ->latest('id');
}
}
