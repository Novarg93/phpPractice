<?php

namespace App\Filament\Resources\PromoCodes;

use App\Filament\Resources\PromoCodes\Pages\CreatePromoCode;
use App\Filament\Resources\PromoCodes\Pages\EditPromoCode;
use App\Filament\Resources\PromoCodes\Pages\ListPromoCodes;
use App\Filament\Resources\PromoCodes\Schemas\PromoCodeForm;
use App\Filament\Resources\PromoCodes\Tables\PromoCodesTable;
use App\Models\PromoCode;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class PromoCodeResource extends Resource
{
    protected static ?string $model = PromoCode::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTicket; // можно любой heroicon
    protected static string|\UnitEnum|null   $navigationGroup = 'Sales';
    protected static ?string $recordTitleAttribute = 'code';

    public static function form(Schema $schema): Schema
    {
        return PromoCodeForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PromoCodesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index'  => ListPromoCodes::route('/'),
            'create' => CreatePromoCode::route('/create'),
            'edit'   => EditPromoCode::route('/{record}/edit'),
        ];
    }
}