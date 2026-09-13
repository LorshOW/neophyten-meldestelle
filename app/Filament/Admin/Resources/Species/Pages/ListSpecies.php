<?php

namespace App\Filament\Admin\Resources\Species\Pages;

use App\Filament\Admin\Resources\Species\SpeciesResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSpecies extends ListRecords
{
    protected static string $resource = SpeciesResource::class;

    protected static ?string $title = 'Pflanzenarten';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Pflanzenart anlegen'),
        ];
    }
}
