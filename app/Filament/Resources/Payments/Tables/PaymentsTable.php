<?php

namespace App\Filament\Resources\Payments\Tables;

use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class PaymentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->label('ID')->sortable(),
                TextColumn::make('user.support_code')->label('Код клиента')->searchable()->copyable(),
                TextColumn::make('user.email')->label('Email')->searchable()->placeholder('—'),
                TextColumn::make('zarkPackage.name')->label('Пакет')->placeholder('—'),
                TextColumn::make('payment_system')->label('Система')->badge(),
                TextColumn::make('amount_rub')->label('Сумма')->money('RUB')->sortable(),
                TextColumn::make('zarks_added')->label('Начислено')->suffix(' ZARQ'),
                TextColumn::make('status')->label('Статус')->badge(),
                TextColumn::make('created_at')->label('Создан')->dateTime('d.m.Y H:i')->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->label('Статус')->options([
                    'pending' => 'Ожидает',
                    'completed' => 'Завершён',
                    'failed' => 'Ошибка',
                    'cancelled' => 'Отменён',
                ]),
            ])
            ->defaultSort('id', 'desc')
            ->recordActions([
                ViewAction::make(),
            ]);
    }
}
