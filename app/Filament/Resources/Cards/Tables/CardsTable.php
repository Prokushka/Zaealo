<?php

namespace App\Filament\Resources\Cards\Tables;

use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class CardsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->label('ID')->sortable(),
                TextColumn::make('user.support_code')->label('Код клиента')->searchable()->copyable(),
                TextColumn::make('title')->label('Название')->searchable()->limit(45),
                TextColumn::make('marketplace')->label('Маркетплейс')->badge(),
                TextColumn::make('status')->label('Статус')->badge(),
                TextColumn::make('generations_count')->label('Генерации')->counts('generations'),
                IconColumn::make('is_exported')->label('Экспортирована')->boolean(),
                TextColumn::make('created_at')->label('Создана')->dateTime('d.m.Y H:i')->sortable(),
            ])
            ->filters([
                SelectFilter::make('marketplace')->label('Маркетплейс')->options([
                    'wildberries' => 'Wildberries',
                    'ozon' => 'Ozon',
                ]),
                SelectFilter::make('status')->label('Статус')->options([
                    'draft' => 'Черновик',
                    'processing' => 'Обработка',
                    'ready' => 'Готова',
                ]),
            ])
            ->defaultSort('id', 'desc')
            ->recordActions([
                ViewAction::make(),
            ]);
    }
}
