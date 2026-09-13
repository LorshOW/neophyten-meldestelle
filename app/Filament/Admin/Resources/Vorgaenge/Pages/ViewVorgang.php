<?php

namespace App\Filament\Admin\Resources\Vorgaenge\Pages;

use App\Filament\Admin\Resources\Vorgaenge\VorgangResource;
use App\Models\Vorgang;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewVorgang extends ViewRecord
{
    protected static string $resource = VorgangResource::class;

    public function getTitle(): string
    {
        /** @var Vorgang $record */
        $record = $this->getRecord();

        return 'Vorgang '.$record->reference();
    }

    public function getSubheading(): ?string
    {
        /** @var Vorgang $record */
        $record = $this->getRecord();

        return $record->reported_species_raw
            ? $record->reported_species_raw.($record->geo_label ? ' · '.$record->geo_label : '')
            : null;
    }

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()->label('Bearbeiten'),
        ];
    }
}
