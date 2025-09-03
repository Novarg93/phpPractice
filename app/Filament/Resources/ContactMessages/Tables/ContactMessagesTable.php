<?php

namespace App\Filament\Resources\ContactMessages\Tables;

use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Forms\Components\DatePicker;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use App\Events\ContactMessageStatusChanged;
use App\Events\ContactMessageDeleted;



final class ContactMessagesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->since()
                    ->sortable(),

                TextColumn::make('name')
                    ->label('Name')
                    ->getStateUsing(fn($record) => "{$record->first_name} {$record->last_name}")
                    ->searchable(),

                TextColumn::make('email')
                    ->label('Email')
                    ->searchable(),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'new'         => 'warning',
                        'in_progress' => 'info',
                        'done'        => 'success',
                        default       => 'gray',
                    })
                    ->sortable(),

                TextColumn::make('handler.name')
                    ->label('Owner'),
            ])
            ->filters([
                // Фильтр по статусу
                \Filament\Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'new'         => 'New',
                        'in_progress' => 'In progress',
                        'done'        => 'Done',
                    ]),

                // Фильтр по дате
                \Filament\Tables\Filters\Filter::make('created_at')
                    ->label('Created date')
                    ->schema([
                        DatePicker::make('from')->label('From'),
                        DatePicker::make('to')->label('To'),
                    ])
                    ->query(function (Builder $query, array $data) {
                        return $query
                            ->when($data['from'] ?? null, fn($q, $date) => $q->whereDate('created_at', '>=', $date))
                            ->when($data['to'] ?? null, fn($q, $date) => $q->whereDate('created_at', '<=', $date));
                    }),
            ])
            ->recordActions([

                EditAction::make(),
                    

                Action::make('assignToMe')
                    ->label('Assign to me')
                    ->action(function ($record) {
                        $record->update([
                            'handled_by' => Auth::id(),
                            'status' => 'in_progress',
                        ]);
                        event(new ContactMessageStatusChanged($record->id, $record->status));
                    }),

                Action::make('markDone')
                    ->label('Mark done')
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        $record->update([
                            'status' => 'done',
                            'handled_at' => now(),
                            'handled_by'  => $record->handled_by ?: Auth::id(),
                        ]);
                        event(new ContactMessageStatusChanged($record->id, $record->status));
                    }),

                Action::make('reply')
                    ->label('Reply')
                    ->url(fn($record) => "mailto:{$record->email}?subject=Re:%20your%20message")
                    ->openUrlInNewTab(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    // стандартное удаление — повесим after() чтобы оповестить
                    DeleteBulkAction::make()
                        ->after(function ($records) {
                            foreach ($records as $record) {
                                event(new ContactMessageDeleted($record->id));
                            }
                        }),

                    BulkAction::make('markDone')
                        ->label('Mark selected as done')
                        ->action(function ($records) {
                            $records->each->update([
                                'status' => 'done',
                                'handled_at' => now(),
                            ]);
                            foreach ($records as $r) {
                                event(new ContactMessageStatusChanged($r->id, $r->status));
                            }
                        }),
                ]),
            ]);
    }
}
