<?php

namespace App\Filament\Resources\Cards;

use App\Filament\Resources\Cards\Pages\ListCards;
use App\Filament\Resources\Cards\Pages\ViewCard;
use App\Filament\Resources\Cards\Schemas\CardForm;
use App\Filament\Resources\Cards\Schemas\CardInfolist;
use App\Filament\Resources\Cards\Tables\CardsTable;
use App\Models\Card;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class CardResource extends Resource
{
    protected static ?string $model = Card::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?string $navigationLabel = 'Карточки';

    protected static ?string $modelLabel = 'карточка';

    protected static ?string $pluralModelLabel = 'карточки';

    protected static string|\UnitEnum|null $navigationGroup = 'Операции';

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Schema $schema): Schema
    {
        return CardForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return CardInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CardsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCards::route('/'),
            'view' => ViewCard::route('/{record}'),
        ];
    }
}
