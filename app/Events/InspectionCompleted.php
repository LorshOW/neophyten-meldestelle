<?php

namespace App\Events;

use App\Models\Inspection;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class InspectionCompleted
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public readonly Inspection $inspection)
    {
    }
}
