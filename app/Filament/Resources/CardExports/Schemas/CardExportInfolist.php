<?php

namespace App\Filament\Resources\CardExports\Schemas;

use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CardExportInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Экспорт')
                    ->schema([
                        TextEntry::make('id')->label('ID'),
                        TextEntry::make('generation.card.user.support_code')->label('Код клиента')->copyable(),
                        TextEntry::make('card_generation_id')->label('Генерация'),
                        TextEntry::make('marketplace')->label('Маркетплейс')->badge(),
                        TextEntry::make('status')->label('Статус')->badge(),
                        TextEntry::make('cost_zarks')->label('Стоимость')->suffix(' ZARQ'),
                        TextEntry::make('external_task_id')->label('Внешняя задача')->copyable()->placeholder('—'),
                        TextEntry::make('external_product_id')->label('Внешний товар')->copyable()->placeholder('—'),
                        TextEntry::make('status_checks')->label('Проверки статуса'),
                        TextEntry::make('error')->label('Ошибка')->columnSpanFull()->placeholder('—'),
                        TextEntry::make('completed_at')->label('Завершён')->dateTime('d.m.Y H:i')->placeholder('—'),
                        KeyValueEntry::make('payload')->label('Данные')->columnSpanFull(),
                    ])
                    ->columns(3),
            ]);
    }
}
