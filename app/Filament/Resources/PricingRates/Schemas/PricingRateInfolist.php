<?php

namespace App\Filament\Resources\PricingRates\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PricingRateInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Тариф')
                    ->schema([
                        TextEntry::make('title')->label('Операция'),
                        TextEntry::make('key')->label('Ключ'),
                        TextEntry::make('cost_zarks')->label('Стоимость')->suffix(' ZARQ'),
                        TextEntry::make('group')->label('Группа')->badge(),
                        TextEntry::make('updated_at')->label('Обновлён')->dateTime('d.m.Y H:i'),
                    ])
                    ->columns(2),
            ]);
    }
}
