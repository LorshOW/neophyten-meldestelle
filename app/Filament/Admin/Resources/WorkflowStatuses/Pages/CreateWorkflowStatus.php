<?php

namespace App\Filament\Admin\Resources\WorkflowStatuses\Pages;

use App\Filament\Admin\Resources\WorkflowStatuses\WorkflowStatusResource;
use Filament\Resources\Pages\CreateRecord;

class CreateWorkflowStatus extends CreateRecord
{
    protected static string $resource = WorkflowStatusResource::class;
}
