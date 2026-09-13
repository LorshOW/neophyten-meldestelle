<?php

namespace App\Filament\Admin\Resources\WorkflowStatuses\Pages;

use App\Filament\Admin\Resources\WorkflowStatuses\WorkflowStatusResource;
use Filament\Resources\Pages\EditRecord;

class EditWorkflowStatus extends EditRecord
{
    protected static string $resource = WorkflowStatusResource::class;
}
