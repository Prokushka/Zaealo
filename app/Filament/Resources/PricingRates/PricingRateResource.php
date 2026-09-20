<?php

namespace App\Filament\Resources\PricingRates;

use App\Filament\Resources\PricingRates\Pages\EditPricingRate;
use App\Filament\Resources\PricingRates\Pages\ListPricingRates;
use App\Filament\Resources\PricingRates\Pages\ViewPricingRate;
use App\Filament\Resources\PricingRates\Schemas\PricingRateForm;
use App\Filament\Resources\PricingRates\Schemas\PricingRateInfolist;
use App\Filament\Resources\PricingRates\Tables\PricingRatesTable;
use App\Models\PricingRate;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class PricingRateResource extends Resource
{
    protected static ?string $model = PricingRate::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCurrencyDollar;

    protected static ?string $navigationLabel = 'Тарифы';

    protected static ?string $modelLabel = 'тариф';

    protected static ?string $pluralModelLabel = 'тарифы';

    protected static string|\UnitEnum|null $navigationGroup = 'Настройки';

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Schema $schema): Schema
    {
        return PricingRateForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return PricingRateInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PricingRatesTable::configure($table);
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
            'index' => ListPricingRates::route('/'),
            'view' => ViewPricingRate::route('/{record}'),
            'edit' => EditPricingRate::route('/{record}/edit'),
        ];
    }
}
