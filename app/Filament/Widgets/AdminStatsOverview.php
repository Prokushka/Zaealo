<?php

namespace App\Filament\Widgets;

use App\Models\CardExport;
use App\Models\CardGeneration;
use App\Models\Payment;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class AdminStatsOverview extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make('Пользователи', User::query()->count())
                ->description('За 30 дней: '.User::query()->where('created_at', '>=', now()->subDays(30))->count()),
            Stat::make(
                'Успешные платежи',
                number_format((float) Payment::query()->where('status', 'completed')->sum('amount_rub'), 0, ',', ' ').' ₽',
            )->description('Количество: '.Payment::query()->where('status', 'completed')->count()),
            Stat::make('Ошибки генераций', CardGeneration::query()->where('status', 'failed')->count())
                ->color('danger'),
            Stat::make('Ошибки экспортов', CardExport::query()->where('status', CardExport::STATUS_FAILED)->count())
                ->color('danger'),
        ];
    }
}
