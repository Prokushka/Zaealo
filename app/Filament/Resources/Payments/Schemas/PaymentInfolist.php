<?php

namespace App\Filament\Resources\Payments\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PaymentInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Платёж')
                    ->schema([
                        TextEntry::make('id')->label('ID'),
                        TextEntry::make('user.support_code')->label('Код клиента')->copyable(),
                        TextEntry::make('user.email')->label('Email')->placeholder('—'),
                        TextEntry::make('zarkPackage.name')->label('Пакет')->placeholder('—'),
                        TextEntry::make('payment_system')->label('Платёжная система')->badge(),
                        TextEntry::make('external_payment_id')->label('Внешний ID')->copyable()->placeholder('—'),
                        TextEntry::make('amount_rub')->label('Сумма')->money('RUB'),
                        TextEntry::make('zarks_added')->label('Начислено')->suffix(' ZARQ'),
                        TextEntry::make('status')->label('Статус')->badge(),
                        TextEntry::make('created_at')->label('Создан')->dateTime('d.m.Y H:i'),
                        TextEntry::make('updated_at')->label('Обновлён')->dateTime('d.m.Y H:i'),
                    ])
                    ->columns(3),
            ]);
    }
}
