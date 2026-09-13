<?php

namespace App\Filament\Admin\Resources\WorkflowTransitions\Pages;

use App\Filament\Admin\Resources\WorkflowTransitions\WorkflowTransitionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListWorkflowTransitions extends ListRecords
{
    protected static string $resource = WorkflowTransitionResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Übergang anlegen')];
    }
}
