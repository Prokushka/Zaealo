<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Enums\AdminRole;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class UserInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Пользователь')
                    ->schema([
                        TextEntry::make('support_code')->label('Код клиента')->copyable(),
                        TextEntry::make('name')->label('Имя'),
                        TextEntry::make('email')->label('Email')->placeholder('Не указан'),
                        TextEntry::make('balance')->label('Баланс')->suffix(' ZARQ'),
                        TextEntry::make('admin_role')
                            ->label('Роль')
                            ->formatStateUsing(fn (?AdminRole $state): string => $state?->label() ?? 'Пользователь')
                            ->badge(),
                        TextEntry::make('created_at')->label('Регистрация')->dateTime('d.m.Y H:i'),
                    ])
                    ->columns(3),
                Section::make('Активность')
                    ->schema([
                        TextEntry::make('cards_count')->label('Карточки'),
                        TextEntry::make('card_generations_count')->label('Генерации'),
                        TextEntry::make('payments_count')->label('Платежи'),
                        TextEntry::make('transactions_count')->label('Транзакции'),
                    ])
                    ->columns(4),
                Section::make('Социальные аккаунты')
                    ->schema([
                        RepeatableEntry::make('socialAccounts')
                            ->label('')
                            ->schema([
                                TextEntry::make('provider')->label('Провайдер')->badge(),
                                TextEntry::make('provider_id')->label('ID у провайдера')->copyable(),
                                TextEntry::make('created_at')->label('Подключён')->dateTime('d.m.Y H:i'),
                            ])
                            ->columns(3),
                    ])
                    ->collapsible(),
                Section::make('Интеграции маркетплейсов')
                    ->schema([
                        RepeatableEntry::make('marketplaceApiKeys')
                            ->label('')
                            ->schema([
                                TextEntry::make('marketplace')->label('Маркетплейс')->badge(),
                                TextEntry::make('created_at')->label('Добавлена')->dateTime('d.m.Y H:i'),
                                TextEntry::make('updated_at')->label('Обновлена')->dateTime('d.m.Y H:i'),
                            ])
                            ->columns(3),
                    ])
                    ->collapsible(),
            ]);
    }
}
