<?php

namespace App\Filament\Resources\PricingRates\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PricingRateForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Стоимость операции')
                    ->schema([
                        TextInput::make('key')->label('Ключ')->disabled()->dehydrated(false),
                        TextInput::make('title')->label('Операция')->disabled()->dehydrated(false),
                        TextInput::make('cost_zarks')
                            ->label('Стоимость')
                            ->numeric()
                            ->integer()
                            ->minValue(0)
                            ->required()
                            ->suffix('ZARQ'),
                    ])
                    ->columns(2),
            ]);
    }
}
