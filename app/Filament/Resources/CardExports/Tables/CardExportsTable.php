<?php

namespace App\Filament\Resources\CardExports\Tables;

use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class CardExportsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->label('ID')->sortable(),
                TextColumn::make('generation.card.user.support_code')->label('Код клиента')->searchable()->copyable(),
                TextColumn::make('card_generation_id')->label('Генерация')->sortable(),
                TextColumn::make('marketplace')->label('Маркетплейс')->badge(),
                TextColumn::make('status')->label('Статус')->badge(),
                TextColumn::make('external_product_id')->label('ID товара')->placeholder('—')->searchable(),
                TextColumn::make('cost_zarks')->label('Стоимость')->suffix(' ZARQ'),
                TextColumn::make('updated_at')->label('Обновлён')->dateTime('d.m.Y H:i')->sortable(),
            ])
            ->filters([
                SelectFilter::make('marketplace')->label('Маркетплейс')->options([
                    'wildberries' => 'Wildberries',
                    'ozon' => 'Ozon',
                ]),
                SelectFilter::make('status')->label('Статус')->options([
                    'not_published' => 'Не опубликован',
                    'queued' => 'В очереди',
                    'submitting' => 'Отправляется',
                    'waiting' => 'Ожидает',
                    'completed' => 'Завершён',
                    'failed' => 'Ошибка',
                ]),
            ])
            ->defaultSort('id', 'desc')
            ->recordActions([
                ViewAction::make(),
            ]);
    }
}
