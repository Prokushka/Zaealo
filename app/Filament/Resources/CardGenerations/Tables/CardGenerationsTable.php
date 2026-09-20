<?php

namespace App\Filament\Resources\CardGenerations\Tables;

use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class CardGenerationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->label('ID')->sortable(),
                TextColumn::make('card.user.support_code')->label('Код клиента')->searchable()->copyable(),
                TextColumn::make('card.title')->label('Карточка')->searchable()->limit(40),
                TextColumn::make('mode')->label('Режим')->badge(),
                TextColumn::make('selected_style')->label('Стиль')->placeholder('—'),
                TextColumn::make('status')->label('Статус')->badge(),
                TextColumn::make('cost_zarks')->label('Стоимость')->suffix(' ZARQ')->sortable(),
                TextColumn::make('created_at')->label('Создана')->dateTime('d.m.Y H:i')->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->label('Статус')->options([
                    'pending' => 'Ожидает',
                    'processing' => 'Обрабатывается',
                    'awaiting_answers' => 'Ожидает ответов',
                    'completed' => 'Завершена',
                    'failed' => 'Ошибка',
                ]),
            ])
            ->defaultSort('id', 'desc')
            ->recordActions([
                ViewAction::make(),
            ]);
    }
}
