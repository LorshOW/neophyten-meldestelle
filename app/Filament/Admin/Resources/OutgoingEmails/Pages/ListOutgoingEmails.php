<?php

namespace App\Filament\Admin\Resources\OutgoingEmails\Pages;

use App\Filament\Admin\Resources\OutgoingEmails\OutgoingEmailResource;
use Filament\Resources\Pages\ListRecords;

class ListOutgoingEmails extends ListRecords
{
    protected static string $resource = OutgoingEmailResource::class;

    protected static ?string $title = 'E-Mail-Protokoll';
}
