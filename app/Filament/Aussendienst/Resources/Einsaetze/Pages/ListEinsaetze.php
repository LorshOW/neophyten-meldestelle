<?php

namespace App\Filament\Aussendienst\Resources\Einsaetze\Pages;

use App\Filament\Aussendienst\Resources\Einsaetze\EinsatzResource;
use Filament\Resources\Pages\ListRecords;

class ListEinsaetze extends ListRecords
{
    protected static string $resource = EinsatzResource::class;

    protected static ?string $title = 'Meine Einsätze';
}
