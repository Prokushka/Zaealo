<?php

namespace App\Filament\Resources\AdminBalanceAdjustments\Tables;

use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AdminBalanceAdjustmentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.support_code')->label('Код клиента')->searchable()->copyable(),
                TextColumn::make('user.email')->label('Email клиента')->searchable()->placeholder('—'),
                TextColumn::make('amount')
                    ->label('Изменение')
                    ->formatStateUsing(fn (int $state): string => ($state > 0 ? '+' : '').$state.' ZARQ')
                    ->color(fn (int $state): string => $state > 0 ? 'success' : 'danger')
                    ->sortable(),
                TextColumn::make('reason')->label('Причина')->searchable()->limit(60),
                TextColumn::make('administrator.email')->label('Сотрудник')->searchable()->placeholder('Удалён'),
                TextColumn::make('created_at')->label('Дата')->dateTime('d.m.Y H:i')->sortable(),
            ])
            ->filters([])
            ->defaultSort('id', 'desc')
            ->recordActions([
                ViewAction::make(),
            ]);
    }
}
