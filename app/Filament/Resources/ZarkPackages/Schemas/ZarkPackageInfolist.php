<?php

namespace App\Filament\Resources\ZarkPackages\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ZarkPackageInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Пакет ZARQ')
                    ->schema([
                        TextEntry::make('name')->label('Название'),
                        TextEntry::make('zarks_amount')->label('Количество')->suffix(' ZARQ'),
                        TextEntry::make('price_rub')->label('Цена')->money('RUB'),
                        TextEntry::make('discount_percent')->label('Скидка')->suffix('%'),
                        IconEntry::make('is_popular')->label('Популярный')->boolean(),
                        IconEntry::make('is_active')->label('Активный')->boolean(),
                    ])
                    ->columns(3),
            ]);
    }
}
