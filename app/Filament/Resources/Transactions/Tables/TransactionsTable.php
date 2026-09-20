<?php

namespace App\Filament\Resources\Transactions\Tables;

use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class TransactionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->label('ID')->sortable(),
                TextColumn::make('user.support_code')->label('Код клиента')->searchable()->copyable(),
                TextColumn::make('amount')->label('Сумма')->suffix(' ZARQ')->sortable(),
                TextColumn::make('type')->label('Тип')->badge(),
                TextColumn::make('description')->label('Описание')->searchable()->limit(55),
                TextColumn::make('reference_type')->label('Источник')->formatStateUsing(fn (?string $state): string => $state ? class_basename($state) : '—'),
                TextColumn::make('created_at')->label('Создана')->dateTime('d.m.Y H:i')->sortable(),
            ])
            ->filters([
                SelectFilter::make('type')->label('Тип')->options([
                    'credit' => 'Начисление',
                    'debit' => 'Списание',
                ]),
            ])
            ->defaultSort('id', 'desc')
            ->recordActions([
                ViewAction::make(),
            ]);
    }
}
