<?php

namespace App\Filament\Resources\CardGenerations\Pages;

use App\Filament\Resources\CardGenerations\CardGenerationResource;
use Filament\Resources\Pages\ListRecords;

class ListCardGenerations extends ListRecords
{
    protected static string $resource = CardGenerationResource::class;
}
