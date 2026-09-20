<?php

namespace App\Filament\Resources\CardImages;

use App\Filament\Resources\CardImages\Pages\ListCardImages;
use App\Filament\Resources\CardImages\Pages\ViewCardImage;
use App\Filament\Resources\CardImages\Schemas\CardImageForm;
use App\Filament\Resources\CardImages\Schemas\CardImageInfolist;
use App\Filament\Resources\CardImages\Tables\CardImagesTable;
use App\Models\CardImage;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class CardImageResource extends Resource
{
    protected static ?string $model = CardImage::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPhoto;

    protected static ?string $navigationLabel = 'Изображения';

    protected static ?string $modelLabel = 'изображение';

    protected static ?string $pluralModelLabel = 'изображения';

    protected static string|\UnitEnum|null $navigationGroup = 'Операции';

    protected static ?string $recordTitleAttribute = 'id';

    public static function form(Schema $schema): Schema
    {
        return CardImageForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return CardImageInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CardImagesTable::configure($table);
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
            'index' => ListCardImages::route('/'),
            'view' => ViewCardImage::route('/{record}'),
        ];
    }
}
