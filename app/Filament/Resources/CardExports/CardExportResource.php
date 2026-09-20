<?php

namespace App\Filament\Resources\CardExports;

use App\Filament\Resources\CardExports\Pages\ListCardExports;
use App\Filament\Resources\CardExports\Pages\ViewCardExport;
use App\Filament\Resources\CardExports\Schemas\CardExportForm;
use App\Filament\Resources\CardExports\Schemas\CardExportInfolist;
use App\Filament\Resources\CardExports\Tables\CardExportsTable;
use App\Models\CardExport;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class CardExportResource extends Resource
{
    protected static ?string $model = CardExport::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowUpTray;

    protected static ?string $navigationLabel = 'Экспорты';

    protected static ?string $modelLabel = 'экспорт';

    protected static ?string $pluralModelLabel = 'экспорты';

    protected static string|\UnitEnum|null $navigationGroup = 'Операции';

    protected static ?string $recordTitleAttribute = 'id';

    public static function form(Schema $schema): Schema
    {
        return CardExportForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return CardExportInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CardExportsTable::configure($table);
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
            'index' => ListCardExports::route('/'),
            'view' => ViewCardExport::route('/{record}'),
        ];
    }
}
