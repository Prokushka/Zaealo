<?php

namespace App\Filament\Resources\CardGenerations\Schemas;

use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CardGenerationInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Генерация')
                    ->schema([
                        TextEntry::make('id')->label('ID'),
                        TextEntry::make('card.user.support_code')->label('Код клиента')->copyable(),
                        TextEntry::make('card.title')->label('Карточка'),
                        TextEntry::make('mode')->label('Режим')->badge(),
                        TextEntry::make('selected_style')->label('Стиль')->placeholder('—'),
                        TextEntry::make('status')->label('Статус')->badge(),
                        TextEntry::make('cost_zarks')->label('Стоимость')->suffix(' ZARQ'),
                        TextEntry::make('generated_title')->label('Заголовок')->columnSpanFull()->placeholder('—'),
                        TextEntry::make('generated_description')->label('Описание')->columnSpanFull()->placeholder('—'),
                        TextEntry::make('prompt_input')->label('Входные данные')->columnSpanFull()->placeholder('—'),
                        KeyValueEntry::make('attributes_data')->label('Атрибуты')->columnSpanFull(),
                        TextEntry::make('created_at')->label('Создана')->dateTime('d.m.Y H:i'),
                    ])
                    ->columns(3),
            ]);
    }
}
