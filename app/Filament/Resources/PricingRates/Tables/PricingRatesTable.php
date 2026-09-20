<?php

namespace App\Filament\Resources\PricingRates\Tables;

use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PricingRatesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')->label('Операция')->searchable(),
                TextColumn::make('key')->label('Ключ')->searchable(),
                TextColumn::make('cost_zarks')->label('Стоимость')->suffix(' ZARQ')->sortable(),
                TextColumn::make('group')->label('Группа')->badge(),
                TextColumn::make('updated_at')->label('Обновлён')->dateTime('d.m.Y H:i')->sortable(),
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
