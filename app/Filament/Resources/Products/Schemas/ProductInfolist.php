<?php

namespace App\Filament\Resources\Products\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class ProductInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('category.name'),
                TextEntry::make('name'),
                TextEntry::make('slug'),
                TextEntry::make('sku')
                    ->label('SKU'),
                TextEntry::make('price_cents')
                    ->numeric(),
                IconEntry::make('is_active')
                    ->boolean(),
                IconEntry::make('track_inventory')
                    ->boolean(),
                TextEntry::make('stock')
                    ->numeric(),
                ImageEntry::make('image'),
                TextEntry::make('created_at')
                    ->dateTime(),
                TextEntry::make('updated_at')
                    ->dateTime(),
            ]);
    }
}
