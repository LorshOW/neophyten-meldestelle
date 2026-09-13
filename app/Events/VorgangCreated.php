<?php

namespace App\Events;

use App\Models\Vorgang;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class VorgangCreated
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public readonly Vorgang $vorgang)
    {
    }
}
