<?php

namespace App\Filament\Resources\Orders\Tables;

use App\Models\Order;
use App\Services\RefundService;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;

// Экшены берём из Filament\Actions (как в твоём UsersTable)
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\ToggleButtons;
use Illuminate\Support\Facades\Auth;

class OrdersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->label('ID')->sortable()->alignCenter(),
                TextColumn::make('user.email')->label('User')->searchable(),
                TextColumn::make('total_cents')->label('Total')->money('USD', divideBy: 100)->alignCenter(),
                TextColumn::make('total_refunded_cents')->label('Refunded')->money('USD', divideBy: 100)->alignCenter(),
                TextColumn::make('status')->badge()->label('Status')->sortable(),
                TextColumn::make('created_at')->since()->sortable(),
            ])
            ->defaultSort('id', 'desc')
            ->paginated([25, 50, 100])

            ->recordActions([
                ViewAction::make()->url(fn($record) => \App\Filament\Resources\Orders\OrdersResource::getUrl('view', ['record' => $record])),
                EditAction::make(),

                Action::make('refundOrder')
                    ->label('Refund order')
                    ->modalHeading('Refund order (amount)')
                    ->schema([ // <-- заменили form() на schema()
                        TextInput::make('amount')
                            ->label('Amount (USD)')
                            ->numeric()
                            ->minValue(0.01)
                            ->required(),
                        Textarea::make('reason')->label('Reason')->rows(2),
                    ])
                    ->requiresConfirmation()
                    ->visible(fn (Order $record): bool => $record->refundableAmountCents() > 0)
                    ->action(function (Order $record, array $data): void {
                        $amountCents = (int) round(((float) $data['amount']) * 100);
                        app(RefundService::class)->refundOrderAmount(
                            $record,
                            $amountCents,
                            $data['reason'] ?? null,
                            Auth::user()
                        );
                    }),

                Action::make('refundItem')
                    ->label('Refund item')
                    ->modalHeading('Refund order item')
                    ->schema([
                        Select::make('order_item_id')
                            ->label('Item')
                            ->options(fn (Order $record) => $record->items()->get()
                                ->mapWithKeys(fn ($i) => [
                                    $i->id => "{$i->product_name} · Qty:{$i->qty} · Paid: " . number_format($i->line_total_cents / 100, 2),
                                ])
                            )
                            ->searchable()
                            ->required(),

                        ToggleButtons::make('mode')
                            ->label('Mode')
                            ->options(['qty' => 'By qty', 'amount' => 'By amount'])
                            ->inline()
                            ->default('qty')
                            ->required(),

                        TextInput::make('qty')
                            ->label('Qty to refund')
                            ->numeric()
                            ->minValue(0.01)
                            ->visible(fn ($get): bool => $get('mode') === 'qty'),

                        TextInput::make('amount')
                            ->label('Amount (USD)')
                            ->numeric()
                            ->minValue(0.01)
                            ->visible(fn ($get): bool => $get('mode') === 'amount'),

                        Textarea::make('reason')->label('Reason')->rows(2),
                    ])
                    ->requiresConfirmation()
                    ->visible(fn (Order $record): bool => $record->refundableAmountCents() > 0)
                    ->action(function (Order $record, array $data): void {
                        $item = $record->items()->findOrFail($data['order_item_id']);
                        $qty = ($data['mode'] ?? null) === 'qty' ? (float) $data['qty'] : null;
                        $amountCents = ($data['mode'] ?? null) === 'amount'
                            ? (int) round(((float) $data['amount']) * 100)
                            : null;

                        app(RefundService::class)->refundItem(
                            $item,
                            $qty,
                            $amountCents,
                            $data['reason'] ?? null,
                            Auth::user()
                        );
                    }),
            ])

            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
