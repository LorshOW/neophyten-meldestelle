<?php

namespace App\Filament\Admin\Resources\KoboSubmissions\Pages;

use App\Filament\Admin\Actions\KoboSyncAction;
use App\Filament\Admin\Resources\KoboSubmissions\KoboSubmissionResource;
use Filament\Resources\Pages\ListRecords;

class ListKoboSubmissions extends ListRecords
{
    protected static string $resource = KoboSubmissionResource::class;

    protected static ?string $title = 'Kobo-Rohdaten';

    protected function getHeaderActions(): array
    {
        return [KoboSyncAction::make()];
    }
}
