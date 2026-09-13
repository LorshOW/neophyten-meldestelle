<?php

namespace App\Filament\Admin\Resources\NotificationRules\Pages;

use App\Filament\Admin\Resources\NotificationRules\NotificationRuleResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListNotificationRules extends ListRecords
{
    protected static string $resource = NotificationRuleResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Regel anlegen')];
    }
}
