<?php

namespace App\Filament\Admin\Resources\Vorgaenge\Pages;

use App\Filament\Admin\Resources\Vorgaenge\VorgangResource;
use App\Models\Vorgang;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditVorgang extends EditRecord
{
    protected static string $resource = VorgangResource::class;

    public function getTitle(): string
    {
        /** @var Vorgang $record */
        $record = $this->getRecord();

        return 'Vorgang '.$record->reference().' bearbeiten';
    }

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make()->label('Ansehen'),
        ];
    }

    protected function getRedirectUrl(): ?string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
