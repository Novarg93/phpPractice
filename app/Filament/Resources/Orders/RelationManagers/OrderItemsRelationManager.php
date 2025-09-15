<?php

namespace App\Filament\Resources\Orders\RelationManagers;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager; // 👈 v4
use App\Services\OrderAuditLogger;
use Illuminate\Support\Facades\App;
use Filament\Tables\Table;
use Filament\Tables\Columns as TC;
use Filament\Actions\Action; // модалка-экшен из Filament\Actions
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class OrderItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items'; // Order::items()

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('product_name')
            ->columns([
                TC\TextColumn::make('id')->label('#')->sortable()->toggleable(),
                TC\TextColumn::make('product_name')->label('Item')->wrap(),
                TC\TextColumn::make('qty')->label('Qty')->alignCenter(),
                TC\TextColumn::make('unit_price_cents')->label('Unit')
                    ->money('USD', divideBy: 100)->alignRight()->toggleable(),
                TC\TextColumn::make('line_total_cents')->label('Line total')
                    ->money('USD', divideBy: 100)->alignRight(),
                TC\TextColumn::make('cost_cents')->label('Cost')
                    ->money('USD', divideBy: 100)->alignRight(),
                TC\TextColumn::make('profit_cents')->label('Profit')
                    ->money('USD', divideBy: 100)->alignRight()->toggleable(),
                TC\TextColumn::make('margin_bp')->label('Margin %')
                    ->formatStateUsing(fn($state) => $state !== null ? number_format($state / 100, 2) . '%' : '—')
                    ->alignRight()->toggleable(),
                TC\TextColumn::make('status')->badge()->label('Item status')->sortable(),
            ])
            ->defaultSort('id', 'asc')
            ->recordActions([
                Action::make('editCost')
                    ->label('Edit cost')
                    ->icon('heroicon-o-currency-dollar')
                    ->modalHeading('Edit item cost')
                    ->schema([
                        TextInput::make('cost_price')
                            ->label('Cost (USD)')
                            ->numeric()
                            ->step('0.01')
                            ->minValue(0)
                            ->required()
                            ->default(fn(OrderItem $record) => $record->cost_cents !== null ? round($record->cost_cents / 100, 2) : null),
                    ])
                    // Видимость: админ — всегда; остальные — только когда заказ paid/in_progress
                    ->visible(function (OrderItem $record): bool {
                        $user = Auth::user();
                        $orderStatus = $record->order?->status;
                        if ($user && $user->role === User::ROLE_ADMIN) {
                            return true;
                        }
                        return in_array($orderStatus, [Order::STATUS_PAID, Order::STATUS_IN_PROGRESS], true);
                    })
                    ->action(function (OrderItem $record, array $data): void {
                        /** @var Order $order */
                        $order = $record->order()->first();
                        $user  = Auth::user();

                        $old = $record->cost_cents;
                        $record->cost_cents = (int) round(((float) $data['cost_price']) * 100);
                        $record->recalcProfit();
                        $record->save();

                        // лог
                        App::make(OrderAuditLogger::class)
                            ->costUpdated($record, $old, $record->cost_cents, $user);

                        $order->recalcTotals();
                        $order->syncStatusFromItems();

                        event(new \App\Events\OrderWorkflowUpdated($order->id));
                    })
            ])
            ->paginated(false);
    }
}
