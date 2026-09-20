<?php

namespace App\Filament\Resources\AdminBalanceAdjustments;

use App\Filament\Resources\AdminBalanceAdjustments\Pages\ListAdminBalanceAdjustments;
use App\Filament\Resources\AdminBalanceAdjustments\Pages\ViewAdminBalanceAdjustment;
use App\Filament\Resources\AdminBalanceAdjustments\Schemas\AdminBalanceAdjustmentInfolist;
use App\Filament\Resources\AdminBalanceAdjustments\Tables\AdminBalanceAdjustmentsTable;
use App\Models\AdminBalanceAdjustment;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AdminBalanceAdjustmentResource extends Resource
{
    protected static ?string $model = AdminBalanceAdjustment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static ?string $navigationLabel = 'Корректировки баланса';

    protected static ?string $modelLabel = 'корректировка баланса';

    protected static ?string $pluralModelLabel = 'корректировки баланса';

    protected static ?int $navigationSort = 8;

    public static function infolist(Schema $schema): Schema
    {
        return AdminBalanceAdjustmentInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AdminBalanceAdjustmentsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['user', 'administrator']);
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['user.support_code', 'user.email', 'administrator.email', 'reason'];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAdminBalanceAdjustments::route('/'),
            'view' => ViewAdminBalanceAdjustment::route('/{record}'),
        ];
    }
}
