<?php

namespace App\Filament\Resources\AdminBalanceAdjustments\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class AdminBalanceAdjustmentInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Корректировка')
                    ->schema([
                        TextEntry::make('user.support_code')->label('Код клиента')->copyable(),
                        TextEntry::make('user.email')->label('Email клиента')->placeholder('—'),
                        TextEntry::make('amount')
                            ->label('Изменение баланса')
                            ->formatStateUsing(fn (int $state): string => ($state > 0 ? '+' : '').$state.' ZARQ')
                            ->color(fn (int $state): string => $state > 0 ? 'success' : 'danger'),
                        TextEntry::make('administrator.email')->label('Сотрудник')->placeholder('Удалён'),
                        TextEntry::make('reason')->label('Причина')->columnSpanFull(),
                        TextEntry::make('created_at')->label('Дата')->dateTime('d.m.Y H:i'),
                    ])
                    ->columns(2),
            ]);
    }
}
