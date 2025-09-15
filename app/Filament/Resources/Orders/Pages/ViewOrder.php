<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Filament\Resources\Orders\OrdersResource;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components as IC;
use Filament\Resources\Pages\Concerns\HasRelationManagers;


/**
 * View page for Order resource
 *
 * @property-read \App\Models\Order $record
 */
class ViewOrder extends ViewRecord
{

    use HasRelationManagers;

    

    protected static string $resource = OrdersResource::class;

    /**
     * Build the Infolist for viewing order details.
     *
     * @param \Filament\Infolists\Infolist $infolist
     * @return \Filament\Infolists\Infolist
     */
    public function getInfolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            IC\Section::make('Order Info')
                ->columns(2)
                ->schema([
                    IC\TextEntry::make('id')->label('ID'),
                    IC\TextEntry::make('user.email')->label('User'),
                    IC\TextEntry::make('status')->label('Status')->badge(),
                    IC\TextEntry::make('total_cents')
                        ->label('Total')
                        ->money('USD', divideBy: 100),
                    IC\TextEntry::make('total_refunded_cents')
                        ->label('Refunded')
                        ->money('USD', divideBy: 100),
                    IC\TextEntry::make('refunded_at')
                        ->label('Refunded at')
                        ->dateTime(),
                ]),

            IC\Section::make('Refunds')
                ->collapsible()
                ->schema([
                    IC\RepeatableEntry::make('refunds') // связь $order->refunds()
                        ->label('Refunds')
                        ->schema([
                            IC\TextEntry::make('id')->label('ID')->badge(),
                            IC\TextEntry::make('amount_cents')->label('Amount')->money('USD', divideBy: 100),
                            IC\TextEntry::make('status')->badge()->label('Status'),
                            IC\TextEntry::make('reason')->label('Reason')->placeholder('—'),
                            IC\TextEntry::make('creator.email')->label('By')->placeholder('system'),
                            IC\TextEntry::make('created_at')->label('Created')->dateTime(),

                            // вложенные позиции рефанда
                            IC\RepeatableEntry::make('items') // связь $refund->items()
                                ->label('Items')
                                ->columns(4)
                                ->schema([
                                    IC\TextEntry::make('orderItem.product_name')->label('Item'),
                                    IC\TextEntry::make('qty')->label('Qty')->numeric(),
                                    IC\TextEntry::make('amount_cents')->label('Amount')->money('USD', divideBy: 100),
                                    IC\TextEntry::make('note')->label('Note')->placeholder('—'),
                                ]),
                        ])
                        ->columns(6)
                        ->grid( // компактнее сетка
                            fn() => true
                        ),
                ]),

            

            IC\Section::make('Refund Summary')
                ->columns(2)
                ->schema([
                    IC\TextEntry::make('refundableAmountCents')
                        ->label('Refundable left')
                        ->state(fn($record) => $record->refundableAmountCents())
                        ->money('USD', divideBy: 100),

                    IC\TextEntry::make('refunded_items_count')
                        ->label('Items refunded')
                        ->state(fn($record) => $record->items()->where('status', \App\Models\OrderItem::STATUS_REFUND)->count()),
                ]),
        ]);
    }

    protected function mutateRecord($record)
{
    // Filament v4: этот хук уже имеет модель, просто догружаем недостающее
    return $record->loadMissing([
        'user',
        'refunds.items.orderItem',
        'changeLogs.actor',
    ]);
}
}
