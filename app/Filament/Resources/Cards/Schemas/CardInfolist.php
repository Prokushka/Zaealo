<?php

namespace App\Filament\Resources\Cards\Schemas;

use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CardInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Карточка')
                    ->schema([
                        TextEntry::make('id')->label('ID'),
                        TextEntry::make('user.support_code')->label('Код клиента')->copyable(),
                        TextEntry::make('user.email')->label('Email')->placeholder('—'),
                        TextEntry::make('marketplace')->label('Маркетплейс')->badge(),
                        TextEntry::make('status')->label('Статус')->badge(),
                        TextEntry::make('title')->label('Название')->columnSpanFull(),
                        TextEntry::make('description')->label('Описание')->columnSpanFull()->placeholder('—'),
                        TextEntry::make('allowed_photo_slots')->label('Слоты фото'),
                        TextEntry::make('created_at')->label('Создана')->dateTime('d.m.Y H:i'),
                        KeyValueEntry::make('attributes')->label('Атрибуты')->columnSpanFull(),
                    ])
                    ->columns(3),
            ]);
    }
}
