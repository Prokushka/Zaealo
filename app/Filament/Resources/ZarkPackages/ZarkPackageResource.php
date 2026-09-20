<?php

namespace App\Filament\Resources\ZarkPackages;

use App\Filament\Resources\ZarkPackages\Pages\CreateZarkPackage;
use App\Filament\Resources\ZarkPackages\Pages\EditZarkPackage;
use App\Filament\Resources\ZarkPackages\Pages\ListZarkPackages;
use App\Filament\Resources\ZarkPackages\Pages\ViewZarkPackage;
use App\Filament\Resources\ZarkPackages\Schemas\ZarkPackageForm;
use App\Filament\Resources\ZarkPackages\Schemas\ZarkPackageInfolist;
use App\Filament\Resources\ZarkPackages\Tables\ZarkPackagesTable;
use App\Models\ZarkPackage;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ZarkPackageResource extends Resource
{
    protected static ?string $model = ZarkPackage::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingBag;

    protected static ?string $navigationLabel = 'Пакеты ZARQ';

    protected static ?string $modelLabel = 'пакет ZARQ';

    protected static ?string $pluralModelLabel = 'пакеты ZARQ';

    protected static string|\UnitEnum|null $navigationGroup = 'Настройки';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return ZarkPackageForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ZarkPackageInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ZarkPackagesTable::configure($table);
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
            'index' => ListZarkPackages::route('/'),
            'create' => CreateZarkPackage::route('/create'),
            'view' => ViewZarkPackage::route('/{record}'),
            'edit' => EditZarkPackage::route('/{record}/edit'),
        ];
    }
}
