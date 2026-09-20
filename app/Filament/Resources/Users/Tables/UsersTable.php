<?php

namespace App\Filament\Resources\Users\Tables;

use App\Enums\AdminRole;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('support_code')->label('Код')->searchable()->copyable(),
                TextColumn::make('name')->label('Имя')->searchable()->sortable(),
                TextColumn::make('email')->label('Email')->searchable()->placeholder('—'),
                TextColumn::make('balance')->label('Баланс')->suffix(' ZARQ')->sortable(),
                TextColumn::make('admin_role')
                    ->label('Роль')
                    ->formatStateUsing(fn (?AdminRole $state): string => $state?->label() ?? 'Пользователь')
                    ->badge(),
                TextColumn::make('cards_count')->label('Карточки')->counts('cards')->sortable(),
                TextColumn::make('created_at')->label('Регистрация')->dateTime('d.m.Y H:i')->sortable(),
            ])
            ->filters([
                SelectFilter::make('admin_role')
                    ->label('Роль')
                    ->options(collect(AdminRole::cases())->mapWithKeys(
                        fn (AdminRole $role): array => [$role->value => $role->label()],
                    )->all()),
            ])
            ->defaultSort('id', 'desc')
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ]);
    }
}
