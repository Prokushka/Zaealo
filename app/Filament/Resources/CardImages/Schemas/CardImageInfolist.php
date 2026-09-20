<?php

namespace App\Filament\Resources\CardImages\Schemas;

use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CardImageInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Изображение')
                    ->schema([
                        TextEntry::make('id')->label('ID'),
                        TextEntry::make('card.user.support_code')->label('Код клиента')->copyable(),
                        TextEntry::make('card_id')->label('Карточка'),
                        TextEntry::make('generation_id')->label('Генерация')->placeholder('—'),
                        TextEntry::make('type')->label('Тип')->badge(),
                        TextEntry::make('generation_status')->label('Статус')->badge(),
                        TextEntry::make('generation_category')->label('Категория')->placeholder('—'),
                        TextEntry::make('generation_subcategory')->label('Подкатегория')->placeholder('—'),
                        TextEntry::make('path')->label('Путь')->copyable()->columnSpanFull(),
                        TextEntry::make('generation_error')->label('Ошибка')->columnSpanFull()->placeholder('—'),
                        KeyValueEntry::make('generation_features')->label('Параметры')->columnSpanFull(),
                        TextEntry::make('created_at')->label('Создано')->dateTime('d.m.Y H:i'),
                    ])
                    ->columns(3),
            ]);
    }
}
