<?php

namespace App\Filament\Resources\CardGenerations;

use App\Filament\Resources\CardGenerations\Pages\ListCardGenerations;
use App\Filament\Resources\CardGenerations\Pages\ViewCardGeneration;
use App\Filament\Resources\CardGenerations\Schemas\CardGenerationForm;
use App\Filament\Resources\CardGenerations\Schemas\CardGenerationInfolist;
use App\Filament\Resources\CardGenerations\Tables\CardGenerationsTable;
use App\Models\CardGeneration;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class CardGenerationResource extends Resource
{
    protected static ?string $model = CardGeneration::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSparkles;

    protected static ?string $navigationLabel = 'Генерации';

    protected static ?string $modelLabel = 'генерация';

    protected static ?string $pluralModelLabel = 'генерации';

    protected static string|\UnitEnum|null $navigationGroup = 'Операции';

    protected static ?string $recordTitleAttribute = 'id';

    public static function form(Schema $schema): Schema
    {
        return CardGenerationForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return CardGenerationInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CardGenerationsTable::configure($table);
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
            'index' => ListCardGenerations::route('/'),
            'view' => ViewCardGeneration::route('/{record}'),
        ];
    }
}
