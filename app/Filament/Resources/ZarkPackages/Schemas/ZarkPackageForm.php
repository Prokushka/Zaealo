<?php

namespace App\Filament\Resources\ZarkPackages\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ZarkPackageForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Пакет')
                    ->schema([
                        TextInput::make('name')->label('Название')->required()->maxLength(255),
                        TextInput::make('zarks_amount')
                            ->label('Количество ZARQ')->numeric()->integer()->minValue(1)->required(),
                        TextInput::make('price_rub')
                            ->label('Цена')->numeric()->minValue(0)->required()->suffix('₽'),
                        TextInput::make('discount_percent')
                            ->label('Скидка')->numeric()->integer()->minValue(0)->maxValue(100)->required()->suffix('%'),
                        Toggle::make('is_popular')->label('Популярный'),
                        Toggle::make('is_active')->label('Активный')->default(true),
                    ])
                    ->columns(2),
            ]);
    }
}
