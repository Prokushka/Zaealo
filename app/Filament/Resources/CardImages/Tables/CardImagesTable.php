<?php

namespace App\Filament\Resources\CardImages\Tables;

use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class CardImagesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->label('ID')->sortable(),
                TextColumn::make('card.user.support_code')->label('Код клиента')->searchable()->copyable(),
                TextColumn::make('generation_id')->label('Генерация')->sortable()->placeholder('—'),
                TextColumn::make('type')->label('Тип')->badge(),
                TextColumn::make('generation_status')->label('Статус')->badge(),
                TextColumn::make('generation_category')->label('Категория')->placeholder('—'),
                IconColumn::make('is_main')->label('Главное')->boolean(),
                IconColumn::make('is_paid')->label('Оплачено')->boolean(),
                TextColumn::make('created_at')->label('Создано')->dateTime('d.m.Y H:i')->sortable(),
            ])
            ->filters([
                SelectFilter::make('type')->label('Тип')->options([
                    'user_upload' => 'Загружено пользователем',
                    'ai_generated' => 'Создано ИИ',
                ]),
                SelectFilter::make('generation_status')->label('Статус')->options([
                    'queued' => 'В очереди',
                    'processing' => 'Обрабатывается',
                    'completed' => 'Готово',
                    'failed' => 'Ошибка',
                ]),
            ])
            ->defaultSort('id', 'desc')
            ->recordActions([
                ViewAction::make(),
            ]);
    }
}
