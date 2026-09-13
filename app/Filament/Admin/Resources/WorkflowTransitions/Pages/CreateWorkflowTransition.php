<?php

namespace App\Filament\Admin\Resources\WorkflowTransitions\Pages;

use App\Filament\Admin\Resources\WorkflowTransitions\WorkflowTransitionResource;
use Filament\Resources\Pages\CreateRecord;

class CreateWorkflowTransition extends CreateRecord
{
    protected static string $resource = WorkflowTransitionResource::class;
}
