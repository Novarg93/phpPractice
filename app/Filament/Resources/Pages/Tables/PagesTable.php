<?php

namespace App\Filament\Resources\Pages\Tables;

use App\Models\Page;
use Filament\Tables\Table;

// ✅ колонки берём из Tables\Columns
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;

// ✅ экшены берём из Filament\Actions (как в твоих остальных ресурсах)
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\BulkActionGroup;

class PagesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')->searchable()->badge()->copyable(),
                TextColumn::make('name')->searchable()->limit(40),
                TextColumn::make('order')->numeric()->sortable(),
                TextColumn::make('seo_title')->searchable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('seo_description')->searchable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('seo_og_title')->searchable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('seo_og_description')->searchable()->toggleable(isToggledHiddenByDefault: true),
                ImageColumn::make('seo_og_image')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')->since()->sortable(),
            ])
            ->defaultSort('order')
            // v4: recordActions
            ->recordActions([
                EditAction::make(),
                Action::make('view')
                    ->label('Open')
                    ->url(fn (Page $record) => route('legal.show', $record->code))
                    ->openUrlInNewTab(),
            ])
            // v4: toolbarActions (аналог bulkActions)
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}