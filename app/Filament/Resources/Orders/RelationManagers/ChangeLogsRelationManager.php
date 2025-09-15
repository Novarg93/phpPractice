<?php

namespace App\Filament\Resources\Orders\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;
use Filament\Tables\Columns as TC;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Illuminate\Database\Eloquent\Builder;
use Filament\Forms\Components as FC;
use Illuminate\Support\HtmlString;
use Filament\Schemas\Components\Grid as UiGrid;


class ChangeLogsRelationManager extends RelationManager
{
    protected static string $relationship = 'changeLogs';
    protected static ?string $title = 'Change history';

    protected function getTableQuery(): Builder
    {
        return $this->getRelationship()->getQuery()->with(['actor', 'item']);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('action')
            ->columns([
                TC\TextColumn::make('created_at')
                    ->label('When')
                    ->since()
                    ->sortable(),

                TC\TextColumn::make('item.product_name')
                    ->label('Item')
                    ->wrap()
                    ->toggleable(),

                TC\TextColumn::make('actor.email')
                    ->label('Who')
                    ->placeholder('system')
                    ->toggleable(),

                TC\TextColumn::make('action')
                    ->label('Action')
                    ->badge()
                    ->formatStateUsing(fn($state) => str_replace('_', ' ', (string) $state)),

                TC\TextColumn::make('field')
                    ->label('Field')
                    ->placeholder('—')
                    ->toggleable(),

                // Money old → new
                TC\TextColumn::make('money_pair')
                    ->label('Money (old → new)')
                    ->html()
                    ->state(function ($record) {
                        $old = $record?->old_cents;
                        $new = $record?->new_cents;

                        if ($old === null && $new === null) {
                            return '—';
                        }

                        $oldTxt = $old !== null ? number_format($old / 100, 2) : '—';
                        $newTxt = $new !== null ? number_format($new / 100, 2) : '—';

                        return new HtmlString(
                            '<div class="flex items-center justify-end gap-2">
                                <span class="text-muted-foreground">' . $oldTxt . '</span>
                                <span>→</span>
                                <span class="font-medium">' . $newTxt . '</span>
                            </div>'
                        );
                    })
                    ->visible(fn($r) => $r?->old_cents !== null || $r?->new_cents !== null)
                    ->alignRight(),

                // Values old → new
                TC\TextColumn::make('value_pair')
                    ->label('Value (old → new)')
                    ->html()
                    ->state(function ($record) {
                        $old = $record?->old_value;
                        $new = $record?->new_value;

                        if ($old === null && $new === null) {
                            return '—';
                        }

                        $oldTxt = $old !== null ? e($old) : '—';
                        $newTxt = $new !== null ? e($new) : '—';

                        return new HtmlString(
                            '<div class="flex items-center gap-2">
                                <span class="text-muted-foreground break-words">' . $oldTxt . '</span>
                                <span>→</span>
                                <span class="font-medium break-words">' . $newTxt . '</span>
                            </div>'
                        );
                    })
                    ->visible(fn($r) => $r?->old_value !== null || $r?->new_value !== null),

                TC\TextColumn::make('order_item_id')
                    ->label('Item #')
                    ->visible(fn($record) => $record?->order_item_id !== null),

                TC\TextColumn::make('note')
                    ->label('Note')
                    ->limit(120)
                    ->placeholder('—')
                    ->toggleable(),
            ])
            ->defaultSort('id', 'desc')
            ->headerActions([])
            ->recordActions([
                ViewAction::make()
                    ->modalHeading('Change details')
                    ->schema([
                        FC\Placeholder::make('action')
                            ->label('Action')
                            ->content(fn($record) => str_replace('_', ' ', (string) $record->action)),

                        FC\Placeholder::make('field')
                            ->label('Field')
                            ->content(fn($record) => $record->field ?? '—'),

                        // ✅ две колонки через Group
                        UiGrid::make(2)
                            ->schema([
                                FC\Placeholder::make('money_pair_inline')
                                    ->label('Money')
                                    ->content(function ($record) {
                                        $old = $record?->old_cents;
                                        $new = $record?->new_cents;

                                        if ($old === null && $new === null) {
                                            return '—';
                                        }

                                        $oldTxt = $old !== null ? number_format($old / 100, 2) . ' USD' : '—';
                                        $newTxt = $new !== null ? number_format($new / 100, 2) . ' USD' : '—';

                                        return new HtmlString("
            <div class='flex items-center gap-3'>
                <span class='text-xs text-muted-foreground'>Old:</span>
                <span class='font-medium text-red-500'>{$oldTxt}</span>
                <span class='text-muted-foreground'>→</span>
                <span class='text-xs text-muted-foreground'>New:</span>
                <span class='font-medium text-green-500'>{$newTxt}</span>
            </div>
        ");
                                    }),

                                FC\Placeholder::make('value_pair_inline')
                                    ->label('Values')
                                    ->content(function ($record) {
                                        $old = $record?->old_value;
                                        $new = $record?->new_value;

                                        if ($old === null && $new === null) {
                                            return '—';
                                        }

                                        $oldTxt = $old !== null ? e($old) : '—';
                                        $newTxt = $new !== null ? e($new) : '—';

                                        return new HtmlString("
            <div class='flex items-center gap-3'>
                <span class='text-xs text-muted-foreground'>Old:</span>
                <span class='font-medium text-red-500 break-words'>{$oldTxt}</span>
                <span class='text-muted-foreground'>→</span>
                <span class='text-xs text-muted-foreground'>New:</span>
                <span class='font-medium text-green-500 break-words'>{$newTxt}</span>
            </div>
        ");
                                    }),

                            ])
                            ->columns(2),

                        FC\Placeholder::make('note')
                            ->label('Note')
                            ->content(fn($record) => $record->note ?? '—'),

                        FC\Placeholder::make('actor.email')
                            ->label('Actor')
                            ->content(fn($record) => $record->actor?->email ?? 'system'),

                        FC\Placeholder::make('created_at')
                            ->label('When')
                            ->content(fn($record) => $record->created_at?->toDateTimeString()),
                    ]),

                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
