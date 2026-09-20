<?php

namespace App\Filament\Resources\Transactions\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class TransactionInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Транзакция')
                    ->schema([
                        TextEntry::make('id')->label('ID'),
                        TextEntry::make('user.support_code')->label('Код клиента')->copyable(),
                        TextEntry::make('user.email')->label('Email')->placeholder('—'),
                        TextEntry::make('amount')->label('Сумма')->suffix(' ZARQ'),
                        TextEntry::make('type')->label('Тип')->badge(),
                        TextEntry::make('description')->label('Описание')->columnSpanFull(),
                        TextEntry::make('reference_type')->label('Тип источника')->placeholder('—'),
                        TextEntry::make('reference_id')->label('ID источника')->placeholder('—'),
                        TextEntry::make('created_at')->label('Создана')->dateTime('d.m.Y H:i'),
                    ])
                    ->columns(3),
            ]);
    }
}
