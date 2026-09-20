<?php

namespace App\Filament\Resources\ZarkPackages\Tables;

use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ZarkPackagesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Название')->searchable()->sortable(),
                TextColumn::make('zarks_amount')->label('ZARQ')->numeric()->sortable(),
                TextColumn::make('price_rub')->label('Цена')->money('RUB')->sortable(),
                TextColumn::make('discount_percent')->label('Скидка')->suffix('%')->sortable(),
                IconColumn::make('is_popular')->label('Популярный')->boolean(),
                IconColumn::make('is_active')->label('Активный')->boolean(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ]);
    }
}
