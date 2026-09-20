<?php

namespace App\Filament\Resources\CardImages\Pages;

use App\Filament\Resources\CardImages\CardImageResource;
use Filament\Resources\Pages\ListRecords;

class ListCardImages extends ListRecords
{
    protected static string $resource = CardImageResource::class;
}
